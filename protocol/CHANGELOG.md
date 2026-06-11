# Upload Protocol — Changelog

The protocol is versioned independently of the implementation packages (semver). Breaking wire
changes bump the major.

## 1.0.0 — unreleased

- Initial formalization of the de-facto protocol shipped in `xakki/laravel-file-uploader` 0.x.
- Chunk request fields, `uploadId` format, completion semantics (`(chunkIndex+1) >= totalChunks`).
- Response envelope `{success, data, message}` / `{success, message, errors?}`.
- Endpoints: `chunks`, `files`, `files/{id}`, `files/{id}/restore`, `trash/cleanup`.
- JSON Schemas, OpenAPI 3.1 document, and conformance fixtures.
