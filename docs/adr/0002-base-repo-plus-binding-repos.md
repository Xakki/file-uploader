# 2. Base repo (core) + separate binding repos

- Status: Accepted
- Date: 2026-06-11
- Supersedes: [ADR 0001](0001-repo-topology.md)

## Context

ADR 0001 kept a **monorepo of record** and published the PHP packages through **generated read-only
mirror repos** (subtree split), because Packagist maps one package to one repo root and cannot install
from a subdirectory. In practice the mirror machinery (split workflow, `MIRROR_PUSH_TOKEN`,
never-hand-edit mirrors, repointing the old Packagist package) was more operational overhead than the
maintainer wanted, and the bindings rarely need to change in lockstep with the protocol.

## Decision

Drop the monorepo-of-record + mirrors model in favour of a small **base repo + standalone binding repos**:

- **This repo (`Xakki/file-uploader`)** is the base package `xakki/file-uploader` (the PHP core),
  published to Packagist **directly from the repo root**. It also hosts the shared `protocol/`
  (spec + conformance fixtures) and the `js/` package (`@xakki/file-uploader`).
- The framework bindings move to **their own repos**, each its own Packagist package published from its
  root:
  - `Xakki/file-uploader-laravel` → `xakki/file-uploader-laravel`
  - `Xakki/file-uploader-symfony` → `xakki/file-uploader-symfony`
- Bindings `require xakki/file-uploader` from Packagist — no `path` repositories, no mirrors.
- Conformance fixtures stay canonical in this repo's `protocol/fixtures` and **ship inside the core
  package**; bindings read them from `vendor/xakki/file-uploader/protocol/fixtures`.

## Consequences

Positive:

- No mirror infrastructure: every package is a normal repo with a root `composer.json`; Packagist's
  GitHub hook publishes on a `vX.Y.Z` tag. The core release tag simplifies from `php-core/vX.Y.Z` to
  plain `vX.Y.Z`.
- Bindings evolve and version independently; smaller blast radius per change.

Negative / accepted:

- **Conformance is no longer atomic.** A `protocol/fixtures` change in this repo reaches the bindings
  only after `composer update xakki/file-uploader` in each binding repo — drift is possible between a
  core release and a binding adopting it. Mitigation: bindings pin `xakki/file-uploader:^1` and run the
  vendored fixtures in their own CI; a protocol change ships as a core release the bindings then adopt.
- The core package dist **must keep `protocol/fixtures`** (do not `export-ignore` `protocol/`).
- A coordinated protocol + binding change is now N PRs across N repos instead of one.

## Migration

- `php/core/*` hoisted to the repo root; the `php/` directory removed.
- `php/laravel` → `Xakki/file-uploader-laravel`; `php/symfony` → `Xakki/file-uploader-symfony` (clean
  copy, single initial commit, conformance fixtures path repointed to the vendored core).
- `.github/workflows/release.yml` reduced to npm publishing; the PHP mirror-split job removed.

## Revisit triggers

- Conformance drift between core and a binding causes real breakage → consider a dedicated fixtures
  package required as a dev-dependency, or tighter release coupling.
