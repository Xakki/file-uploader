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

## 5. Authentication (binding-defined)

Auth and CSRF are imposed by the server binding, not by the protocol. The Laravel/Symfony
bindings sit behind configurable middleware (default: session auth). For cookie-session CSRF
the de-facto convention (and what existing clients send) is:

- read cookie `XSRF-TOKEN`, send header `X-XSRF-TOKEN: <decoded value>`;
- send `Accept: application/json` and credentials/cookies with each chunk POST.

Token-based bindings MAY instead require an `Authorization` header. Clients are configurable to
supply arbitrary headers per request.

---

## 6. Server configuration knobs (informative)

These affect protocol behaviour but are server-side config, surfaced to clients via the widget
bootstrap where relevant: `chunkSize` (default 1 MiB), `maxSize`, allowed extensions/MIME map,
`prefix`, trash TTL, soft-delete on/off, locales, and feature flags (`allowList`, `allowDelete`,
`allowDeleteAllFiles`, `allowCleanup`).

---

## 7. Conformance

An implementation conforms to Upload Protocol v1 if:

1. its request parsing accepts/validates the fields in §2 with the stated constraints;
2. its responses match the envelope and `data` shapes in §3 (validate against
   [`schemas/`](./schemas/));
3. it passes every scenario in [`fixtures/`](./fixtures/) (shared by the PHP and JS test suites).

See [`fixtures/README.md`](./fixtures/README.md) for the harness contract.
