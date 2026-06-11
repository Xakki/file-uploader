# Upload Protocol v1

The vendor-neutral contract for chunked file upload, shared by every `file-uploader`
implementation. This directory is the **source of truth**; PHP/JS (and future Go/Python)
implementations conform to it.

| File | Purpose |
|------|---------|
| [`SPEC.md`](./SPEC.md) | Normative human-readable specification |
| [`openapi.yaml`](./openapi.yaml) | OpenAPI 3.1 — endpoint contract for client codegen |
| [`schemas/`](./schemas/) | JSON Schemas: chunk request, response envelope, file response |
| [`fixtures/`](./fixtures/) | Cross-language conformance scenarios (run by PHP + JS suites) |
| [`CHANGELOG.md`](./CHANGELOG.md) | Protocol version history |

Versioned independently of the packages. Current: **1.0.0**.
