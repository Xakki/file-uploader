# ADR 0003 — Core-owned, cross-language message i18n catalog

**Status:** Accepted (design) — implementation pending (see `.claude/kanban`)
**Date:** 2026-06-14
**Supersedes scope of:** the binding-level grooming card `localize-core-exception-messages.md`
(scope shifted from "localize in each binding" to "localize once in the core/protocol").

## Context

The core (`xakki/file-uploader`) throws user-facing messages as **hard-coded English**
strings (`AttentionException`, `AuthorizationException`, `Protocol\ChunkValidator`), and the
success/action messages live as **per-binding** translations (Laravel `lang/*/messages.php`;
Symfony has none — English only). Consequences:

- Inconsistent i18n: the JS widget UI is localized (`js/src/widget/strings.ts`, 8 locales) but
  server-produced messages are not (Symfony) or only partially (Laravel localizes its own
  prefixes but concatenates the raw English core message).
- Every new core guard message (e.g. the 0.3.2 `max_files` one) ships untranslated.
- Future Go / Python implementations would each re-invent their own translations, drifting
  from PHP/JS.

The protocol **already** carries a per-request `locale` field (`SPEC.md §4`, validated by
`ChunkValidator`, exposed via `ChunkPayload::locale()`) — it is simply unused server-side.

## Decision

Localize **once, in the protocol/core**, with the catalogs as cross-language data so every
implementation (PHP, JS, Go, Python) reads the **same** files and produces identical messages.

### 1. Catalogs live in the protocol (source of truth), as JSON

`protocol/i18n/<locale>.json` — one file per locale, a **flat map of `code → template`**.
Ships inside the core package (like `protocol/fixtures/`), so bindings read it from
`vendor/xakki/file-uploader/protocol/i18n/` and Go/Python read the same files.

```json
// protocol/i18n/en.json
{
  "error.max_files_reached": "Maximum number of files reached.",
  "error.max_size_exceeded": "File exceeds the maximum allowed size.",
  "error.extension_mime_mismatch": "Extension {ext} is not allowed for MIME type {mime}.",
  "message.cleanup_done": {
    "one": "Removed {count} file from trash.",
    "other": "Removed {count} files from trash."
  }
}
```

```json
// protocol/i18n/ru.json
{
  "error.max_files_reached": "Достигнуто максимальное число файлов.",
  "error.extension_mime_mismatch": "Расширение {ext} недопустимо для MIME-типа {mime}.",
  "message.cleanup_done": {
    "one":  "Удалён {count} файл из корзины.",
    "few":  "Удалено {count} файла из корзины.",
    "many": "Удалено {count} файлов из корзины.",
    "other":"Удалено {count} файла из корзины."
  }
}
```

**Format = JSON** (not env/YAML): consistent with the rest of `protocol/` (JSON Schemas +
fixtures), stdlib parsing in every target language (`encoding/json`, `json`, `JSON.parse`,
`json_decode`), and clean UTF-8 / escaping for `ru` out of the box.

### 2. Stable message codes (the language-agnostic contract)

Documented in a new `protocol/SPEC.md` §Errors. Namespaces: `error.*` (exceptions),
`validation.*` (field errors in the `errors` map), `message.*` (success/action). Initial set:

| Code | Params | Origin (PHP) | HTTP |
|---|---|---|---|
| `error.max_files_reached` | — | `FileUploader` guardFileCount | 422 |
| `error.max_size_exceeded` | `max` | `FileUploader` guardFile | 422 |
| `error.extension_not_allowed` | `ext` | `FileUploader` guardFile | 422 |
| `error.extension_mime_mismatch` | `ext`,`mime` | `FileUploader` guardFile | 422 |
| `error.mime_not_allowed` | `mime` | `FileUploader` guardFile | 422 |
| `error.chunk_persist_failed` | — | `FileUploader` | 422 |
| `error.not_authorized_delete` | — | `FileManager` | 403 |
| `error.not_authorized_restore` | — | `FileManager` | 403 |
| `validation.uploadid_invalid` | — | `ChunkValidator` | 422 |
| `validation.filehash_invalid` | — | `ChunkValidator` | 422 |
| `validation.locale_invalid` | — | `ChunkValidator` | 422 |
| `validation.field_required` | `field` | `ChunkValidator` | 422 |
| `message.upload_completed` | `name` | binding ctrl → core | 200 |
| `message.chunk_received` | `current`,`total` | binding ctrl → core | 200 |
| `message.moved_to_trash` | — | binding ctrl → core | 200 |
| `message.restored` | — | binding ctrl → core | 200 |
| `message.cleanup_done` | `count` (plural) | binding ctrl → core | 200 |
| `message.not_found` | — | binding ctrl → core | 404 |

> The table is the starting catalog; the implementation PR enumerates the complete set
> (every `throw` / message site) and the SPEC lists it normatively. `LogicException`
> (HTTP 500 — internal bugs, e.g. "Failed to open temporary stream.") is **out of scope**:
> these are not user-facing and stay English.

### 3. Placeholder + plural conventions (portable, no ICU dependency)

- **Placeholders:** `{key}` is replaced by `params[key]` (stringified). Neutral on purpose —
  not Laravel `:name`, not Symfony/ICU `%name%`. The count param is conventionally `{count}`.
- **Plurals:** a catalog value is **either** a `string` (no plural) **or** an object keyed by
  **CLDR plural categories** (`zero`,`one`,`two`,`few`,`many`,`other`; `other` is required as
  the fallback). The implementation computes the CLDR category for the count in the target
  locale, picks that key, falls back to `other`. The SPEC documents the shipped-locale rules inline so
  each implementation can ship them without a CLDR library:
  - **en:** `n == 1 → one`, else `other`.
  - **ru:** by `n % 10` / `n % 100` — `one` (1, but not 11), `few` (2–4, but not 12–14),
    `many` (0, 5–9, 11–14), `other` (fractions).

### 4. Envelope gains a stable `code` (+ `params`) — protocol evolution

Because `response-envelope.schema.json` is `additionalProperties: false`, exposing the code is
a schema change. We do it now (per decision): add **optional** `code` (string) and `params`
(object of scalars) to **both** envelope branches. `message` stays — it remains the
ready-to-display localized string for humans and back-compat; `code`+`params` let clients
re-localize and let **cross-language conformance assert on `code`, not on prose**.

```json
{ "success": false, "message": "Достигнуто максимальное число файлов.",
  "code": "error.max_files_reached", "params": {} }
```

Touched protocol artifacts: `protocol/schemas/response-envelope.schema.json`,
`protocol/openapi.yaml`, `protocol/fixtures/*` (error fixtures pin `code`, not the message
text). `Protocol\ResponseFactory::success()/error()` gain `code`/`params` arguments.

### 5. Core runtime + locale resolution

- A small core resolver, `Protocol\MessageCatalog` (working name):
  `resolve(string $code, array $params = [], ?string $locale = null): string` — loads
  `protocol/i18n/{locale}.json` (memoized), falls back to `en`, applies plural + `{}`
  interpolation. No framework dependency; mirrored by the JS/Go/Python implementations.
- Exceptions carry `code` + `params` (not baked English). The `guardFile()` concatenations are
  refactored into discrete coded errors.
- **Locale resolution order** (uniform across implementations): explicit `$locale` arg →
  request `locale` field (validated ∈ configured `locales`) → server default `config['locale']`
  → `en`.

### 6. Binding impact

Bindings become **pass-through** for core messages — they stop shipping/maintaining their own
copies of core-message translations and stop concatenating English core text.

- **Laravel:** drop the `attention:`/`error:` + raw-core-message concatenation; the core returns
  the full localized message. ⚠ Behavior change: server messages currently follow
  `app()->getLocale()`; to preserve that, the binding passes `app()->getLocale()` as the explicit
  locale into the resolver. The binding may keep a `lang/*` file only for truly binding-specific
  strings (if any remain).
- **Symfony:** gains localized messages for the first time (no catalog work needed — reads the
  protocol catalogs via the core resolver).
- **JS widget:** unchanged in Phase 1 (its UI strings stay in `js/src/widget/strings.ts`); a
  follow-up may unify the widget's `strings.ts` onto the same `protocol/i18n` catalogs and have
  it consume the response `code` (ties into the `widget-consume-max-files` card).

## Consequences

**Positive:** one source of truth for all server messages across PHP/JS/Go/Python; new core
messages are localized everywhere for free; Symfony reaches parity; conformance can assert on
stable `code`s; the unused protocol `locale` field becomes meaningful.

**Costs / risks:**
- `en` catalog **must reproduce current English strings verbatim** or core/binding phpunit tests
  that assert on message text (e.g. `tests/MaxFilesTest`) break. This is the load-bearing
  correctness rule for implementation.
- Refactor of `FileUploader::guardFile()` (concatenation → coded params) and `ChunkValidator`
  (currently `static` — threading locale/catalog in changes its signature).
- Plural handling adds a small per-language CLDR rule set (the 8 shipped locales).
- Protocol/schema/openapi/fixtures change — a v1 **additive** evolution (`code`/`params` optional,
  so existing clients keep validating); bump `protocol/CHANGELOG.md`.

## Implementation outline (per target)

1. **protocol/**: add `i18n/<locale>.json` for the 8 widget locales (en, ru, es, pt, zh, fr, de, sr);
   SPEC §Errors (code table + placeholder +
   plural rules); envelope schema + openapi + error fixtures gain `code`/`params`; CHANGELOG.
2. **core (PHP)**: `Protocol\MessageCatalog` resolver; exceptions carry `code`+`params`;
   `guardFile()`/`ChunkValidator` refactor; `ResponseFactory` adds `code`/`params`; en strings
   verbatim; tests.
3. **bindings**: Laravel — pass `app()->getLocale()`, drop concat; Symfony — wire resolver; both
   re-run `make test-binding`; READMEs document the now-localized server messages.
4. **JS / Go / Python** (later): consume `code`; optionally reuse `protocol/i18n` catalogs.
