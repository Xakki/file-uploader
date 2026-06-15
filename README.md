# File Uploader

> Universal **chunked file uploader**: one wire contract — **[Upload Protocol v1](protocol/SPEC.md)** —
> with conforming implementations for **Laravel**, **Symfony**, **any PHP** project, and the **browser**.

[![License](https://img.shields.io/badge/License-GPL--3.0-green)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777)
![Node](https://img.shields.io/badge/Node-18%2B-393)

Chunked uploads (configurable chunk size, default 1 MB), Drag & Drop widget, file list with
public-link copy, soft-delete to trash with TTL auto-cleanup, role-based access, works with any
`league/flysystem` disk (local, **S3/CloudFront**, …).

**This repository is the base package** [`xakki/file-uploader`](https://packagist.org/packages/xakki/file-uploader)
— the framework-agnostic PHP **core** — and also hosts the shared **[protocol](protocol/)** spec and the
**[JS client + widget](js/)** (`@xakki/file-uploader`). The framework bindings live in their own repos.

## The protocol

The upload behaviour (chunk fields, `uploadId` format, the `{success, data, message}` envelope,
retry-not-on-4xx) is a **wire contract**, not framework code. It lives once in [`protocol/`](protocol/)
as a language-neutral spec + OpenAPI + JSON Schemas + **conformance fixtures** that every
implementation runs, so the implementations cannot drift apart. Topology rationale:
[ADR 0002](docs/adr/0002-base-repo-plus-binding-repos.md) (supersedes [ADR 0001](docs/adr/0001-repo-topology.md)).

## Pick your stack

| Your stack | Package | Install |
|---|---|---|
| **Any PHP** (Slim, Mezzio, plain) | [`xakki/file-uploader`](https://packagist.org/packages/xakki/file-uploader) (this repo, core) | `composer require xakki/file-uploader` |
| **Laravel** 10–12 | [`xakki/file-uploader-laravel`](https://github.com/Xakki/file-uploader-laravel) | `composer require xakki/file-uploader-laravel` |
| **Symfony** 6.4 / 7 | [`xakki/file-uploader-symfony`](https://github.com/Xakki/file-uploader-symfony) | `composer require xakki/file-uploader-symfony` |
| **Browser / SPA** | [`@xakki/file-uploader`](js) | `npm i @xakki/file-uploader` |
| **No Node build** | vendored UMD widget | ships inside the PHP bindings — no npm needed |

The browser side is split: a **headless core** (`@xakki/file-uploader/core`, tree-shakeable, no DOM)
and a **DOM widget** (`@xakki/file-uploader/widget`). PHP projects without a Node toolchain get the
same widget as a pre-built UMD bundle in the bindings' assets.

## Core — use it directly

```bash
composer require xakki/file-uploader
```

The core depends on four seams a host wires up:

| Seam | Interface | Provided |
|---|---|---|
| Storage | `Contracts\Storage` | `Storage\FlysystemStorage` over any `league/flysystem` adapter |
| Identity | `Contracts\UserResolver` | `Auth\NullUserResolver` (guest) or your own |
| Clock | `Psr\Clock\ClockInterface` | `Clock\SystemClock` |
| Logger | `Psr\Log\LoggerInterface` | any PSR-3, e.g. `Psr\Log\NullLogger` |

A request adapts to `Contracts\ChunkPayload` (one chunk = a readable stream + scalars).

```php
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\NullLogger;
use Xakki\FileUploader\Auth\NullUserResolver;
use Xakki\FileUploader\Clock\SystemClock;
use Xakki\FileUploader\FileManager;
use Xakki\FileUploader\Storage\FlysystemStorage;

$manager = new FileManager(
    config: [
        'disk' => 'local',
        'directory' => 'uploads',
        'max_size' => 50 * 1024 * 1024,
        'max_files' => 0,             // 0 = unlimited; caps active (non-deleted) files
        'allowed_extensions' => [],   // empty = allow any
        'soft_delete' => true,
        'trash_ttl_days' => 30,
    ],
    storage: new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter('/var/uploads'))),
    users: new NullUserResolver(),
    logger: new NullLogger(),
    clock: new SystemClock(),
);
```

Adapt your request to `ChunkPayload`, then call the core; build the response with the shared
`Protocol\ResponseFactory` and validate scalar fields with `Protocol\ChunkValidator`:

```php
use Xakki\FileUploader\Dto\FileMetadata;
use Xakki\FileUploader\Protocol\ChunkValidator;
use Xakki\FileUploader\Protocol\ResponseFactory;

$errors = ChunkValidator::validate($_POST);
if ($errors !== []) {
    http_response_code(422);
    echo json_encode(ResponseFactory::error('Validation failed.', $errors));
    return;
}

$result = $manager->handleChunk($payload);          // $payload implements ChunkPayload
$completed = $result instanceof FileMetadata;
$data = ['completed' => $completed];
if ($completed) {
    $data['metadata'] = $manager->formatFileForResponse($result);
}
echo json_encode(ResponseFactory::success($data, 'ok'));
```

`FileManager` also provides `list()`, `delete($id)`, `restore($id)`, `cleanupTrash()` and
`syncMetadata()`. A complete runnable example (plain-PHP, filesystem **and** S3, serving the widget)
is the **[demo repo](https://github.com/Xakki/file-uploader-demo)**.

### Per-user / per-session isolation

The core has no notion of HTTP, cookies or sessions — identity is the host's job. But all four
storage directories are plain per-instance config, so to keep every user's files under their own
folder you just construct the manager with those keys scoped to the user/session id. For example,
to land everything under `uploads/<session_id>/`:

```php
$sid = preg_replace('/[^A-Za-z0-9_-]/', '', $sessionId);   // REQUIRED: a path segment, sanitize it
if ($sid === '') {
    throw new RuntimeException('Invalid session id.');
}

config: [
    'disk'                => 'local',
    'directory'           => "uploads/$sid",          // → uploads/<sid>/<file>
    'metadata_directory'  => "uploads/$sid/.meta",
    'temporary_directory' => "uploads/$sid/.chunks",
    'trash_directory'     => "uploads/$sid/.trash",
    // …rest unchanged
],
```

Because `list()`, dedup and `cleanupTrash()` all scan the configured metadata/trash dirs, scoping
them per session gives full isolation for free — each session only ever sees its own files.

> **Sanitize the id.** It becomes a filesystem path segment; an unsanitized value like `../../etc`
> is path traversal. Allow only a safe charset (e.g. `[A-Za-z0-9_-]`) and reject empties.

Where the `$sessionId` comes from — an auth user id, or a cookie auto-issued to guests — is the
host's concern: wire it through `Contracts\UserResolver` (so ownership checks line up) and set the
cookie in your controller/framework. The **[demo](https://github.com/Xakki/file-uploader-demo)**
shows the guest-cookie variant end to end.

## Layout

```
file-uploader/                  ← this repo = xakki/file-uploader (core) + protocol + js
├── php/         Xakki\FileUploader\   framework-agnostic PHP core
├── tests/       core test suite
├── protocol/    Upload Protocol v1 — SPEC.md, openapi.yaml, schemas/, fixtures/   ← source of truth
├── js/          @xakki/file-uploader  headless TS client + DOM widget (ESM/CJS/UMD)
├── go/ python/  conformance-target stubs (planned)
└── docs/        ADRs + roadmap

Bindings (separate repos, consume the published core):
  Laravel  → github.com/Xakki/file-uploader-laravel   (xakki/file-uploader-laravel)
  Symfony  → github.com/Xakki/file-uploader-symfony    (xakki/file-uploader-symfony)
  Demo     → github.com/Xakki/file-uploader-demo
```

## Develop

```bash
make install        # core (root) + js deps
make test           # core + js suites
make test-core      # phpunit + phpstan at the repo root
make test-js        # js unit + conformance
make conformance    # js client against protocol/fixtures
make phpstan        # static analysis · make pint / pint-fix
```

Bindings are developed in their own repos and consume the published core; they run the same
`protocol/fixtures` (vendored via the core package) as their conformance gate.

## Documentation

- **[Upload Protocol v1](protocol/SPEC.md)** — the wire contract (fields, envelope, endpoints, errors).
- **[docs/RELEASING.md](docs/RELEASING.md)** — tagging + publishing (core, js, bindings).
- **[docs/adr/](docs/adr/)** — architecture decisions. **[docs/TODO.md](docs/TODO.md)** — roadmap.
- Binding repos: [Laravel](https://github.com/Xakki/file-uploader-laravel) ·
  [Symfony](https://github.com/Xakki/file-uploader-symfony) · [demo](https://github.com/Xakki/file-uploader-demo).

## License

[GPL-3.0-or-later](LICENSE).
