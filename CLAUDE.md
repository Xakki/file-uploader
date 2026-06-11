# CLAUDE.md — File Uploader

Universal chunked file uploader: one wire contract (**Upload Protocol v1**, `protocol/`). **This repo is
the base package** `xakki/file-uploader` (framework-agnostic PHP core) + the protocol spec + the JS
client/widget. Framework bindings live in **separate repos**. Overview and usage live in the READMEs —
read those, don't restate them here.

## Architecture map

- **Repo root = `xakki/file-uploader` core** (`Xakki\FileUploader\`): `src/`, `tests/`, `composer.json`
  at the root. All upload logic lives here; bindings are thin adapters over its seams
  (Storage / UserResolver / Clock / Logger).
- `protocol/` — **source of truth**: SPEC.md, OpenAPI, JSON Schemas, conformance `fixtures/`. Ships
  inside the core package so bindings read it from `vendor/xakki/file-uploader/protocol/fixtures`.
- `js/` — `@xakki/file-uploader`: tree-shakeable core (`./core`) + DOM widget (`./widget`); built to
  ESM/CJS/UMD. The UMD is vendored into the PHP bindings' assets (`npm run vendor:php`).
- `go/`, `python/` — conformance-target stubs (planned).
- **Bindings (separate repos)** that `require xakki/file-uploader` from Packagist:
  [`Xakki/file-uploader-laravel`](https://github.com/Xakki/file-uploader-laravel) (`xakki/laravel-file-uploader`),
  [`Xakki/file-uploader-symfony`](https://github.com/Xakki/file-uploader-symfony) (`xakki/symfony-file-uploader`).
- **Demo (separate repo):** [`Xakki/file-uploader-demo`](https://github.com/Xakki/file-uploader-demo)
  — standalone plain-PHP backend (fs + S3); the custom/PSR usage example.
  **No demo coupling in this repo:** the demo is driven entirely from its own Makefile/Docker; do not
  add `demo-*` targets or any reference to `../file-uploader-demo` here. The demo (a consumer) may
  depend on this core; this core must not depend on the demo.

## Commands — everything goes through `make`

Run `make help` for the full list. Key targets:

- `make test` — core + js · `make test-core | test-js`
- `make conformance` — the shared `protocol/fixtures` (js client; anti-drift gate in this repo)
- `make phpstan` · `make pint` / `make pint-fix` · `make install` · `make clean`

**Hard rule: all dev/test operations run via `make <target>`.** Don't call `composer`, `npm`, or
`docker run` directly — if an operation has no target, **add one to the `Makefile`**, then use it.
(PHP/Node aren't installed natively; the targets wrap Docker — `lfu-test` for PHP, `node:22-alpine`
for JS. Override with `PHP_IMAGE=` / `NODE_IMAGE=`.)

## Process

- **This repo publishes `xakki/file-uploader` from the root** (Packagist GitHub hook on a `vX.Y.Z` tag);
  keep the `composer.json` `version` in sync with the release tag. Bindings are separate repos that
  consume the published core.
- **Minimal duplication**: logic lives once in the core (`src/`, PHP) and `js/src/core` (TS); bindings
  only wire framework seams. The response envelope (`Protocol\ResponseFactory`) and chunk-field
  validation (`Protocol\ChunkValidator`) are shared in the core — don't reimplement protocol behaviour
  in a binding.
- **Conformance is a gate**: keep `make conformance` green (js client runs `protocol/fixtures`). The
  binding repos run the same fixtures (vendored via the core package) in their own CI.
- **Commits** (see `~/.claude/CLAUDE.md` for style): short imperative subject ≤72 chars, no trailers.
  Commit per logical phase. **Commit/push only when explicitly asked.**
- Releases are tag-driven (core `vX.Y.Z`, `js/vX.Y.Z`; bindings tag in their own repos) — `docs/RELEASING.md`.

## Protected / don't-touch

- Never write to or hand-edit: `.env*`, secrets, `**/vendor/`, `**/node_modules/`, `js/dist/`.
  (In the Symfony binding repo, `config/reference.php` is generated — don't hand-edit it.)

## Don't index

`**/vendor/`, `**/node_modules/`, `js/dist/` are deps/build output — skip them.

## Pointers

- `protocol/SPEC.md` — the wire contract. `docs/adr/0002-base-repo-plus-binding-repos.md` — current
  topology (supersedes 0001).
- `docs/TODO.md` — roadmap / remaining (maintainer) work.
- `docs/RELEASING.md` — tagging + publishing. `protocol/fixtures/README.md` — conformance runners.
