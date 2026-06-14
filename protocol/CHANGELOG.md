# Upload Protocol — Changelog

The protocol is versioned independently of the implementation packages (semver). Breaking wire
changes bump the major.

## 1.0.0 — unreleased

- Initial formalization of the de-facto protocol shipped in `xakki/laravel-file-uploader` 0.x.
- Chunk request fields, `uploadId` format, completion semantics (`(chunkIndex+1) >= totalChunks`).
- Response envelope `{success, data, message}` / `{success, message, errors?}`.
- Endpoints: `chunks`, `files`, `files/{id}`, `files/{id}/restore`, `trash/cleanup`.
- JSON Schemas, OpenAPI 3.1 document, and conformance fixtures.
- **Additive:** cross-language i18n message/error **code catalog** (`protocol/i18n/<locale>.json`,
  8 locales: `en`, `ru`, `es`, `pt`, `zh`, `fr`, `de`, `sr` — matching the JS widget) — a flat
  `code → template` map reused by every implementation (PHP/JS/Go/Python).
  Adds the `{key}` placeholder convention and CLDR-category plural rules (`zero`/`one`/`two`/`few`/
  `many`/`other`, `other` required), with the shipped-locale plural rules documented inline. The
  normative list of stable codes (`error.*` / `validation.*` / `message.*`, with params + HTTP)
  is in SPEC §5 (Error & message codes).
