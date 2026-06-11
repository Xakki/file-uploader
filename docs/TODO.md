# Roadmap / remaining work

Status of the "make the library universal" effort. **Topology changed 2026-06-11** — see
[ADR 0002](adr/0002-base-repo-plus-binding-repos.md) (supersedes 0001): this repo is now the **base
package** `xakki/file-uploader` (core at the root) + `protocol/` + `js/`; the Laravel/Symfony bindings
moved to their own repos (`Xakki/file-uploader-laravel`, `Xakki/file-uploader-symfony`), each its own
Packagist package consuming the published core. The historical "Done" entries below describe the
earlier monorepo-of-record + mirrors model.

## Done

- **Protocol v1** — `protocol/` (SPEC.md, OpenAPI 3.1, JSON Schemas, conformance fixtures).
- **PHP core** — `php/core` (`Xakki\FileUploader\`), framework-agnostic over `Storage`/`UserResolver`/
  PSR-3 logger / PSR-20 clock seams; `FlysystemStorage`; `Protocol\ResponseFactory`. Unit-tested.
- **Laravel binding** — `php/laravel` (v1.0), thin wrapper over core; existing Testbench suite green.
- **Symfony binding** — `php/symfony` (`xakki/symfony-file-uploader`), `AbstractBundle` over core
  (Storage/UserResolver/Clock/Logger seams, controllers, widget renderer, console). Kernel test
  round-trips an upload + delete + widget render. Chunk field validation lives in the shared core
  `Protocol\ChunkValidator` (one rule set for Symfony + the future conformance gate).
- **npm `@xakki/file-uploader`** — `js/`, protocol-conformant headless core (`./core`) + DOM widget
  (`./widget`); ESM/CJS + UMD; vitest. Vendored UMD replaces the old hand-written JS in PHP.
- **Standalone demo** — extracted to a separate repo, `Xakki/file-uploader-demo` (plain-PHP core
  usage, fs + S3/MinIO, serves the widget; the custom/PSR usage example). fs path verified end-to-end.
- **Reorg + rename** — `protocol/ · php/{core,laravel} · js/ · go/ · python/ · demo/`; repo renamed to
  `Xakki/file-uploader`; `go/`+`python/` conformance-target stubs.
- **Documentation** — monorepo root `README.md` (overview + framework→package matrix), per-package
  READMEs (core/laravel/symfony/js), `MIGRATION.md` 0.2→1.0. Removed the stale single-package root
  files (`composer.json`, `Makefile`, `Dockerfile`, `phpstan*`, `phpunit.xml.dist`, `.env_dist`,
  `.gitattributes`) — the root is the monorepo of record, not a Composer package.
- **CI + static analysis** — `.github/workflows/ci.yml`: `dorny/paths-filter` matrix, phpunit +
  phpstan level 6 per PHP package (core/symfony plain, laravel via larastan; Symfony matrix includes a
  **6.4 LTS** cell via flex `SYMFONY_REQUIRE`), JS typecheck/test/build, repo-wide Pint. Root
  `pint.json`; per-package `phpstan.neon.dist`.
- **Conformance gate** — `protocol/fixtures` now run by all three suites: Symfony + Laravel servers
  (all scenarios, through a booted kernel / HTTP client) and the JS client (success scenarios, over a
  mock transport). Confirms the Laravel FormRequest and the core `ChunkValidator` emit the same
  `errors`-keyed-by-field shape. See `protocol/fixtures/README.md` §Runners.
- **Publishing (scaffolded)** — `.github/workflows/release.yml` + [`docs/RELEASING.md`](RELEASING.md):
  per-package tags (`php-core/* · php-laravel/* · php-symfony/* · js/*`), copy-tree publish to read-only
  mirrors with a `jq` strip of the `repositories:path` block (composer errors on a missing path repo,
  so the strip is required), npm publish from `js/`. Tagging scheme + the one-time maintainer setup
  (create mirror repos, add `MIRROR_PUSH_TOKEN`/`NPM_TOKEN`, register mirrors on Packagist, repoint the
  old `xakki/laravel-file-uploader` Packagist package) are documented. Execution is the maintainer's.

- **Widget DOM tests** — `js/test/widget.test.ts` (happy-dom): mount, style injection, `allowList`
  toggle, `destroy()`, `mountFromGlobalConfig`.
- **Unit-test coverage** — broadened across every package: core 9→45 (FlysystemStorage, clock/resolver
  seams, guard/allow-list, lifecycle/auth/dedup, multi-chunk, hash-mismatch, `syncMetadata`), Laravel
  12→24 (FileController endpoints, `LaravelUserResolver`, cleanup command), Symfony 7→14 (restore/cleanup
  endpoints, `SymfonyUserResolver`, console commands), JS 14→32 (`xhrTransport`, hash, errors, locale
  parity, widget rendering/gating).
- **Bug fix** — `FileManager::restore()` had an inverted `move()` check (reported a successful restore
  as failure and never cleared `deletedAt`); fixed + regression-tested.

## Remaining (maintainer)

- **Coverage gate in CI** — wire a coverage report + threshold into `ci.yml` (phpunit `--coverage`,
  vitest `--coverage`); needs pcov/xdebug in the `lfu-test` image (`XDEBUG_MODE=off` is forced in the
  composer scripts today). Deferred low-ROI unit targets, better exercised through the HTTP/conformance
  flows: thin adapters `LaravelStorage`/`LaravelClock`, Symfony
  `JsonEnvelope`/`StorageFactory`/`SymfonyChunkPayload`, and `widget.ts` paths beyond render/config.
- **Publish (new model, [RELEASING.md](RELEASING.md)):** register `Xakki/file-uploader`,
  `…-laravel`, `…-symfony` on Packagist (GitHub hook) and tag core `v1.0.0`; the bindings now release
  from their own repos. The old mirror workflow/secrets (`MIRROR_PUSH_TOKEN`, `file-uploader-*` mirrors)
  are no longer used.
- **Demo repo — `Xakki/file-uploader-demo`**: expand from the single plain-PHP/PSR demo to **all
  variants** (Laravel, Symfony, plain-PHP/PSR, JS widget); require the *published* `xakki/file-uploader`
  from Packagist (drop the interim `path` repo); add its own GitHub CI that boots each variant; verify
  the **S3** path e2e (`STORAGE=s3 docker compose up`).
