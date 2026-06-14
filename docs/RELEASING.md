# Releasing

Each package is a **normal repo with a root `composer.json`** and is its own Packagist package,
published by the Packagist GitHub hook on a `vX.Y.Z` tag. npm publishes from `js/` in this repo via the
[`Release`](../.github/workflows/release.yml) workflow. Rationale:
[ADR 0002](adr/0002-base-repo-plus-binding-repos.md) (supersedes [ADR 0001](adr/0001-repo-topology.md)).

## Packages and tags

| Package | Repo | Tag | Publishes to |
|---|---|---|---|
| `xakki/file-uploader` (core) | `Xakki/file-uploader` (this) | `vX.Y.Z` | Packagist (root, GitHub hook) |
| `@xakki/file-uploader` (js) | `Xakki/file-uploader` (this), `js/` | `js/vX.Y.Z` | npm (release workflow) |
| `xakki/file-uploader-laravel` | `Xakki/file-uploader-laravel` | `vX.Y.Z` | Packagist (root, GitHub hook) |
| `xakki/file-uploader-symfony` | `Xakki/file-uploader-symfony` | `vX.Y.Z` | Packagist (root, GitHub hook) |
| `go` (planned) | tbd | `go/vX.Y.Z` | Go module proxy |
| `python` (planned) | tbd | `python/vX.Y.Z` | PyPI |

Each manifest carries an explicit `version` kept in sync with its release tag — core `composer.json`
`0.3.0` ↔ tag `v0.3.0`, `js/package.json` `0.3.0` ↔ tag `js/v0.3.0`, and likewise for each binding.
Bump the manifest version in the commit you tag.

## One-time setup

1. **Packagist** — submit each repo URL (`Xakki/file-uploader`, `…-laravel`, `…-symfony`) on
   packagist.org and enable its GitHub auto-update hook. The package name comes from each repo's root
   `composer.json` `name`.
2. **Secrets** (this repo, for npm): `NPM_TOKEN` — an npm automation token.

## Conformance fixtures across repos

The conformance fixtures live canonically in this repo's `protocol/fixtures` and **ship inside the core
package** (do not `export-ignore` `protocol/`). The binding repos consume them via the core dependency
and run them from `vendor/xakki/file-uploader/protocol/fixtures` — so a protocol change reaches a
binding after `composer update xakki/file-uploader` there (the drift tradeoff accepted in ADR 0002).

## Release order

The bindings require `xakki/file-uploader`. On a **first** release or a core **breaking** change,
release the core first and let Packagist index it, then release the bindings.

## Cutting a release

```bash
# core (this repo): bump composer.json "version", commit, then:
git tag v0.3.0 && git push origin v0.3.0          # Packagist hook publishes xakki/file-uploader

# js (this repo): bump js/package.json "version", commit, then:
git tag js/v0.3.0 && git push origin js/v0.3.0    # release workflow runs npm publish

# a binding (its own repo): bump composer.json "version", commit, then:
git tag v0.3.0 && git push origin v0.3.0          # Packagist hook publishes the binding
```

## Go / Python

Released by tag prefix (`go/vX.Y.Z`, `python/vX.Y.Z`) once implemented, each from its own repo/module
(Go via the module proxy, Python via a PyPI build). They run the same `protocol/fixtures` conformance
suite before release.
