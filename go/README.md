# file-uploader — Go client (planned)

Reference Go implementation of **[Upload Protocol v1](../protocol/SPEC.md)**.

> Status: **stub / not yet implemented.** This package reserves the module path and
> documents intent. It will conform to the same wire contract and pass the shared
> [`protocol/fixtures/`](../protocol/fixtures) as the PHP and JS implementations.

Module path: `github.com/Xakki/file-uploader/go`

When implemented, the client will provide a backend-agnostic chunked uploader mirroring
[`js/`](../js): configurable endpoint, field names, completion predicate and result
extraction; whole-file sha256; retry on network/5xx but never 4xx.
