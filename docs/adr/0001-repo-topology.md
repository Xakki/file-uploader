# 1. Repository topology: monorepo of record + generated split mirrors

- Status: Superseded by [ADR 0002](0002-base-repo-plus-binding-repos.md)
- Date: 2026-06-09

## Context

`file-uploader` is a protocol-centric, multi-language project: one wire contract
(**Upload Protocol v1**, in `protocol/`) with several conforming implementations —
PHP (`php/core`, `php/laravel`, `php/symfony`), JS (`js/`), and planned Go (`go/`)
and Python (`python/`). The protocol ships shared conformance fixtures
(`protocol/fixtures/`) that every implementation's test suite must pass so the
implementations cannot drift apart.

"Split the repo into parts?" is not binary, because **PHP/Packagist forces a split
regardless**: Packagist maps one package to one repository (root `composer.json`) and
cannot install a package from a subdirectory. So PHP packages will always have separate
repositories on Packagist — the only question is whether those are *generated read-only
mirrors* (subtree-split from a monorepo) or *hand-maintained separate repos*.

Publishing realities:

| Ecosystem | Publishable from a subdir? | Requirement |
|-----------|----------------------------|-------------|
| PHP / Packagist | No | git-subtree split into mirror repos (unavoidable) |
| npm | Yes | `package.json` `repository.directory` |
| PyPI | Yes | build backend targets `python/src` |
| Go | Yes (submodule) | `go.mod` in subdir; tag prefix `go/vX.Y.Z`; `…/go/vN` for v2+ |

## Decision

Keep a **single monorepo as the source of truth**. For distribution:

- PHP packages are pushed to **generated read-only mirror repos** for Packagist
  (e.g. `monorepo-split` action), never developed there.
- npm and PyPI publish directly from their subdirectories.
- Go is a submodule released via tag prefixes.

## Consequences

Positive:

- **Cross-language conformance is atomic.** Every suite reads `protocol/fixtures/`
  directly; a protocol change plus all implementation updates land in one commit where
  CI runs every suite together. A breaking protocol change cannot be merged half-done.
- One issue tracker, one CI config (matrix + `paths:` filters), one clone — right-sized
  for a small/solo maintainer.
- Whole system (protocol + all impls) is visible and onboardable in one place; runnable demos
  were later split into a separate consumer repo (`Xakki/file-uploader-demo`).
- Users still get separate Packagist packages via the generated mirrors.

Negative / things to manage (deferred to the CI phase):

- Subtree-split infrastructure (mirror repos, tokens) must be set up and not hand-edited.
- The `repositories: path` block in `php/laravel/composer.json` (`../core`) must be
  stripped/rewritten in split output, or the mirror's composer.json fails. `monorepo-builder`
  handles this; a bare copy-subdir split does not.
- Independent per-package versions share one tag namespace → adopt a tagging scheme
  (`php-core/*`, `js/*`, `go/*`, `python/*`).
- Go monorepo modules carry the `go/vX.Y.Z` tag-prefix and `…/go/vN` v2+ path friction.
- No single cross-language release orchestrator; release steps are wired per ecosystem.
- CI must use `paths:` filters so a push to one language does not rebuild all.

## Alternatives considered

- **Multi-repo (one repo per language/package).** Rejected: the protocol would have to
  become a published/submoduled artifact, protocol changes become N coordinated PRs with
  no atomic "all green" gate (conformance drift), and a solo maintainer pays N× the
  tracker/CI/branch-protection overhead. Revisit only if a language gains a separate
  maintainer team or release cadences diverge sharply.

## Revisit triggers

- A separate maintainer team forms around one language.
- Release cadences diverge hard enough that shared tagging is painful.
- The repository grows unwieldy for contributors.
