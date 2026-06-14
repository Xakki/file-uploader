# Upload Protocol v1

A language- and framework-neutral specification of the chunked file-upload wire protocol
used by `xakki/file-uploader`. Any client (browser, Node, Go, Python) that speaks this
protocol interoperates with any server (Laravel, Symfony, custom PHP, or another language)
that implements it.

- **Protocol version:** `1.0.0` (semver; see [CHANGELOG.md](./CHANGELOG.md)).
- **Status:** stable. This document is the source of truth; reference implementations
  (PHP core, JS client) MUST conform to it and to [`fixtures/`](./fixtures/).
- **Transport:** HTTP/1.1+, `multipart/form-data` requests, `application/json` responses.

> This is a vendor-neutral spec in the spirit of a PSR, not a PHP-FIG PSR. It is designed so
> that Go/Python implementations can be added later against the same `schemas/` and `fixtures/`.

---

## 1. Concepts

A **file** is uploaded as an ordered sequence of **chunks** of at most `chunkSize` bytes.
Each chunk is POSTed independently and identified by a per-upload `uploadId`. The server
buffers chunks, and when the last chunk arrives it **assembles** the file, optionally
verifies its hash, persists **metadata**, and returns the assembled file's metadata.

A completed file is addressed by its content **hash** (sha256), which is used as the public
`id` for listing, deletion, restore.

```
client                                   server
  │  POST /chunks  (idx=0, total=N)  ──▶   buffer chunk 0           ◀── {completed:false}
  │  POST /chunks  (idx=1, total=N)  ──▶   buffer chunk 1           ◀── {completed:false}
  │            ...                                                   ...
  │  POST /chunks  (idx=N-1,total=N)──▶   assemble + verify + meta  ◀── {completed:true, metadata}
```

---

## 2. Chunk request

`POST {prefix}/chunks` with `Content-Type: multipart/form-data`.

| Field              | Type            | Required | Constraints                                              |
|--------------------|-----------------|----------|----------------------------------------------------------|
| `fileChunk`        | binary          | yes      | the chunk payload; ≤ `chunkSize` bytes                   |
| `chunkIndex`       | integer         | yes      | ≥ 0; 0-based index of this chunk                         |
| `totalChunks`      | integer         | yes      | ≥ 1; total number of chunks for this file               |
| `fileSize`         | integer (bytes) | yes      | ≥ 1; ≤ server `maxSize`; total assembled size           |
| `uploadId`         | string          | yes      | ≤ 60; matches `^upload-[0-9]{13}-[a-z0-9]{8}$`          |
| `fileName`         | string          | yes      | ≤ 255                                                    |
| `mimeType`         | string          | yes      | ≤ 150; client-declared MIME                             |
| `fileLastModified` | integer (ms)    | yes      | ≥ 0; source file mtime, epoch milliseconds              |
| `fileHash`         | string          | no       | ≤ 128; lowercase sha256 hex of the whole file           |
| `locale`           | string          | no       | one of the server's configured locales (e.g. `en`,`ru`) |

### `uploadId` format

`upload-{epochMillis13}-{rand8}` where `epochMillis13` is a 13-digit millisecond timestamp
and `rand8` is 8 chars from `[a-z0-9]`. Reference generator:

```js
`upload-${Date.now()}-${Math.random().toString(36).slice(2, 10).padEnd(8, '0')}`
```

### Chunk ordering & resume

- The server determines completion solely by index: **`completed = (chunkIndex + 1) >= totalChunks`**.
  The final chunk (highest index) triggers assembly. Chunks MAY be sent in parallel, but the
  client MUST ensure every index `0..totalChunks-1` is delivered, and the final-index chunk is
  what returns `completed:true`.
- **Dedup / resume short-circuit:** if `fileHash` is supplied and the server already has a
  completed file with that hash, the server MAY immediately return that file's metadata with
  `completed:true` without expecting further chunks.

### Integrity

If `fileHash` is supplied, after assembly the server computes sha256 of the assembled bytes and
MUST reject the upload (see §4 errors) if it does not match.

---

## 3. Response envelope

Every response is a JSON object:

**Success** (HTTP 200):
```json
{ "success": true, "data": <object>, "message": "human-readable string" }
```

**Error** (HTTP 4xx/5xx):
```json
{ "success": false, "message": "human-readable string", "errors": { "field": ["msg", "..."] } }
```
`errors` is present only for field-validation failures (HTTP 422) and maps field → messages.

### 3.1 Chunk store `data`

```jsonc
// intermediate chunk
{ "completed": false }
// final chunk (file assembled)
{ "completed": true, "metadata": <FileResponse> }
```

> `completed` MUST be compared strictly (`=== true`). Some clients have been bitten by
> truthiness when a producer mistypes it as a string — implementations MUST emit a real
> JSON boolean.

### 3.2 `FileResponse`

The public projection of a stored file:

| Key            | Type            | Notes                                            |
|----------------|-----------------|--------------------------------------------------|
| `id`           | string          | the file's sha256 hash; used as `{id}` path param|
| `name`         | string          |                                                  |
| `size`         | integer (bytes) |                                                  |
| `mime`         | string          |                                                  |
| `url`          | string \| null  | public URL if resolvable by the storage/binding  |
| `createdAt`    | string (ISO8601)|                                                  |
| `deletedAt`    | string \| null  | ISO8601 if soft-deleted (in trash)               |
| `lastModified` | integer \| null | epoch ms                                         |

Listing additionally includes `own` (boolean): whether the current user owns the file.

---

## 4. Endpoints

All paths are relative to a configurable `prefix` (default `file-upload`). `{id}` is the
file's sha256 hash.

| Method   | Path                   | Purpose                          | Success `data`                  |
|----------|------------------------|----------------------------------|---------------------------------|
| `POST`   | `{prefix}/chunks`      | upload one chunk                 | `{completed, metadata?}` (§3.1) |
| `GET`    | `{prefix}/files`       | list non-deleted files           | `{files: FileResponse[]}` (+`own`) |
| `DELETE` | `{prefix}/files/{id}`  | soft-delete to trash (or hard)   | `{id}`                          |
| `POST`   | `{prefix}/files/{id}/restore` | restore from trash        | `{id}`                          |
| `DELETE` | `{prefix}/trash/cleanup` | purge expired trash (by TTL)   | `{count}`                       |

Listing returns files sorted by `createdAt` descending.

### HTTP status codes

| Status | Meaning                                                              |
|--------|---------------------------------------------------------------------|
| `200`  | success                                                             |
| `422`  | validation error / disallowed extension / hash mismatch / bad input |
| `403`  | not allowed (ownership / access control / cleanup disabled)        |
| `404`  | file not found                                                      |
| `500`  | unexpected server error                                            |

### Retry guidance (client)

Clients SHOULD retry on network errors and `5xx`, with backoff. Clients MUST NOT retry `4xx`
(the request is malformed/forbidden and will not succeed on retry).

---

## 5. Error & message codes

Every server-produced `message` (and every per-field error in the `errors` map) is derived from
a stable, language-agnostic **`code`** plus optional **`params`**, resolved against per-locale
**catalogs** shipped in the core package at `protocol/i18n/<locale>.json`. The catalogs are the
single source of truth reused by **every** implementation (PHP, JS, Go, Python), so the same
`code` produces identical text everywhere.

A catalog is a flat map of `code → template`. The `code` namespaces are: `error.*` (failed
operations / exceptions), `validation.*` (per-field input errors), and `message.*`
(success / action results).

Catalogs ship for these **8 locales** (matching the JS widget's `strings.ts`): `en`, `ru`,
`es`, `pt`, `zh`, `fr`, `de`, `sr`. `en` is the guaranteed fallback. Additional locales are
added by dropping a new `protocol/i18n/<locale>.json` with the same code set.

### 5.1 Locale resolution

The locale used to render a response is resolved in this order (uniform across implementations):

1. an explicit locale supplied by the caller (server-side API), else
2. the request `locale` field (§2), if present and valid (∈ the server's configured `locales`), else
3. the server's configured default locale, else
4. `en` (the guaranteed fallback locale).

### 5.2 Placeholder convention

A template may contain placeholders of the form `{key}`. Each `{key}` is substituted by
`params[key]`, stringified. The convention is deliberately framework-neutral — **not** Laravel's
`:name`, **not** Symfony/ICU `%name%` — so the catalogs are portable across languages.

```
template: "Extension {ext} is not allowed for MIME type {mime}."
params:   { "ext": "exe", "mime": "image/png" }
result:   "Extension exe is not allowed for MIME type image/png."
```

### 5.3 Plural convention

A catalog value is **either** a plain string (no pluralization) **or** an object keyed by
**CLDR plural categories**: `zero`, `one`, `two`, `few`, `many`, `other`. `other` is **required**
as the fallback. The implementation computes the plural category for the count (conventionally
the `{count}` param) in the **target locale**, selects that key, and falls back to `other` when
the category is absent. The integer-cardinal rules for the shipped locales are reproduced here
so an implementation needs no CLDR library:

- **en**, **de**, **es:** `n == 1 → one`; otherwise `other`.
- **pt**, **fr:** `n == 0 || n == 1 → one`; otherwise `other`.
- **zh:** no plural distinction — a single form (stored as a plain string, not an object).
- **ru**, **sr** (using `i = n % 10` and `j = n % 100`):
  - `one`  — `i == 1 && j != 11`
  - `few`  — `i` in `2..4` && `j` **not** in `12..14`
  - `many` — `i == 0` || `i` in `5..9` || `j` in `11..14` *(ru only; `sr` folds this into `other`)*
  - `other` — everything else (incl. fractional `n`)

```jsonc
// catalog value for message.cleanup_done
"en": { "one": "Removed {count} file from trash.", "other": "Removed {count} files from trash." }
"ru": { "one":  "Удалён {count} файл из корзины.",
        "few":  "Удалено {count} файла из корзины.",
        "many": "Удалено {count} файлов из корзины.",
        "other":"Удалено {count} файла из корзины." }
```

### 5.4 Code catalog

The following codes are normative. The `Params` column lists the placeholder keys each template
may use; `HTTP` is the response status the code is emitted with. The human text lives in the
catalog files (`protocol/i18n/<locale>.json`) and is **not** duplicated here — the `Note` is a
short description only.

| Code | Params | HTTP | Note |
|------|--------|------|------|
| `error.chunk_persist_failed`    | —            | 422 | failed to persist a chunk                |
| `error.max_size_exceeded`       | —            | 422 | file exceeds `max_size`                  |
| `error.extension_mime_mismatch` | `ext`, `mime`| 422 | extension not allowed for that MIME      |
| `error.extension_not_allowed`   | `ext`        | 422 | extension not in allow-list              |
| `error.mime_not_allowed`        | `mime`       | 422 | MIME not in allow-list                   |
| `error.mime_not_allowed_unknown`| —            | 422 | MIME could not be determined             |
| `error.max_files_reached`       | —            | 422 | active-file cap reached                  |
| `error.not_authorized_delete`   | —            | 403 | caller may not delete this file          |
| `error.not_authorized_restore`  | —            | 403 | caller may not restore this file         |
| `validation.field_required`     | `field`      | 422 | required field missing (errors map)      |
| `validation.field_string`       | `field`      | 422 | field must be a string                   |
| `validation.field_max_chars`    | `field`, `max`| 422 | field too long                          |
| `validation.field_integer`      | `field`      | 422 | field must be an integer                 |
| `validation.field_min`          | `field`, `min`| 422 | field below minimum                     |
| `validation.uploadid_invalid`   | —            | 422 | `uploadId` pattern mismatch              |
| `validation.filehash_invalid`   | —            | 422 | `fileHash` invalid                       |
| `validation.locale_invalid`     | —            | 422 | `locale` not in configured set           |
| `message.chunk_received`        | `current`, `total`| 200 | non-final chunk stored              |
| `message.upload_completed`      | `name`       | 200 | final chunk; file assembled              |
| `message.moved_to_trash`        | —            | 200 | soft-deleted                             |
| `message.restored`              | —            | 200 | restored from trash                      |
| `message.cleanup_done`          | `count` (plural)| 200 | expired trash purged                  |
| `message.not_found`             | —            | 404 | file not found                           |
| `message.not_allow`             | —            | 403 | operation not allowed                    |

### 5.5 Mapping to the envelope

- `validation.*` codes populate the per-field **`errors`** map (§3): each offending field maps to
  the rendered message(s) for its code.
- `error.*` and `message.*` codes render the envelope-level **`message`** (§3).

In addition to the human-readable `message`, the envelope carries an optional **`code`** (the
stable code that produced the message) and **`params`** (the placeholder values used to render
it) on both the success and error branches — so clients can re-localize and cross-language
conformance can assert on the stable `code` rather than on prose. Both fields are **optional**
(an implementation that has not adopted codes still validates); they are reflected in
`schemas/response-envelope.schema.json` and `openapi.yaml`, and positive `fixtures/` pin the
expected `code`.

Per-field **error codes** (machine codes for each entry in the `errors` map) are **not** exposed
on the wire yet — the `errors` map stays `field → rendered message(s)`. Conformance already
asserts on field *names* (prose-independent), so this is a possible future extension, not a gap.

---

## 6. Authentication (binding-defined)

Auth and CSRF are imposed by the server binding, not by the protocol. The Laravel/Symfony
bindings sit behind configurable middleware (default: session auth). For cookie-session CSRF
the de-facto convention (and what existing clients send) is:

- read cookie `XSRF-TOKEN`, send header `X-XSRF-TOKEN: <decoded value>`;
- send `Accept: application/json` and credentials/cookies with each chunk POST.

Token-based bindings MAY instead require an `Authorization` header. Clients are configurable to
supply arbitrary headers per request.

---

## 7. Server configuration knobs (informative)

These affect protocol behaviour but are server-side config, surfaced to clients via the widget
bootstrap where relevant: `chunkSize` (default 1 MiB), `maxSize`, allowed extensions/MIME map,
`prefix`, trash TTL, soft-delete on/off, locales, and feature flags (`allowList`, `allowDelete`,
`allowDeleteAllFiles`, `allowCleanup`).

---

## 8. Conformance

An implementation conforms to Upload Protocol v1 if:

1. its request parsing accepts/validates the fields in §2 with the stated constraints;
2. its responses match the envelope and `data` shapes in §3 (validate against
   [`schemas/`](./schemas/));
3. it passes every scenario in [`fixtures/`](./fixtures/) (shared by the PHP and JS test suites).

See [`fixtures/README.md`](./fixtures/README.md) for the harness contract.
