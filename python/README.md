# file-uploader — Python client (planned)

Reference Python implementation of **[Upload Protocol v1](../protocol/SPEC.md)**.

> Status: **stub / not yet implemented.** This package reserves the name and documents
> intent. It will conform to the same wire contract and pass the shared
> [`protocol/fixtures/`](../protocol/fixtures) as the PHP and JS implementations.

When implemented, the client will provide a backend-agnostic chunked uploader mirroring
[`js/`](../js): configurable endpoint, field names, completion predicate and result
extraction; whole-file sha256; retry on network/5xx but never 4xx.
