SHELL = /bin/bash
### https://makefiletutorial.com/ — single entry point for all dev/test operations.
### PHP/Node run in Docker (no native toolchain required); override the images if needed.
### This repo is the base package (core) + protocol + js; the framework bindings live in their own repos.

PHP_IMAGE  ?= lfu-test:latest
NODE_IMAGE ?= node:22-alpine

# Reusable docker prefixes: repo mounted at /repo, composer caches in /tmp.
DOCKER_PHP  = docker run --rm -v "$(CURDIR)":/repo -e COMPOSER_HOME=/tmp/c -e COMPOSER_CACHE_DIR=/tmp/cc
DOCKER_NODE = docker run --rm -v "$(CURDIR)":/repo

.DEFAULT_GOAL := help
.PHONY: help install test test-core test-js conformance phpstan pint pint-fix clean verify-binding

##@ Help
help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) }' $(MAKEFILE_LIST)

##@ Install
install:  ## Install deps (core + js)
	$(DOCKER_PHP) -w /repo $(PHP_IMAGE) sh -lc 'composer install --no-interaction'
	$(DOCKER_NODE) -w /repo/js $(NODE_IMAGE) sh -lc 'npm ci'

##@ Test
test: test-core test-js  ## Run the core + js suites

test-core:  ## core (repo root): phpunit + phpstan
	$(DOCKER_PHP) -w /repo $(PHP_IMAGE) sh -lc 'composer install -q --no-interaction && composer phpunit && composer phpstan'

test-js:  ## js: typecheck + vitest + build
	$(DOCKER_NODE) -w /repo/js $(NODE_IMAGE) sh -lc 'npm ci && npm run typecheck && npm test && npm run build'

conformance:  ## Run the shared protocol/fixtures suite (js client; anti-drift gate)
	$(DOCKER_NODE) -w /repo/js $(NODE_IMAGE) sh -lc 'npm ci && npm test -- conformance'

##@ Quality
phpstan:  ## phpstan on the core
	$(DOCKER_PHP) -w /repo $(PHP_IMAGE) sh -lc 'composer install -q --no-interaction && composer phpstan'

pint:  ## Pint style check (src tests)
	$(DOCKER_PHP) -w /repo $(PHP_IMAGE) sh -lc 'composer global require -q laravel/pint && $$(composer global config home)/vendor/bin/pint --test src tests'

pint-fix:  ## Pint auto-fix (src tests)
	$(DOCKER_PHP) -w /repo $(PHP_IMAGE) sh -lc 'composer global require -q laravel/pint && $$(composer global config home)/vendor/bin/pint src tests'

##@ Bindings
verify-binding:  ## Smoke-test a sibling binding repo against this working-tree core (DIR=../file-uploader-laravel)
	docker run --rm -v "$(dir $(CURDIR))":/work -e COMPOSER_HOME=/tmp/c -e COMPOSER_CACHE_DIR=/tmp/cc \
	  -w /work/$(notdir $(DIR)) $(PHP_IMAGE) sh -lc '\
	    cp composer.json /tmp/cj.bak; \
	    composer config repositories.coredev path ../file-uploader; \
	    composer require "xakki/file-uploader:*@dev" --no-update -W -q; \
	    composer update --no-interaction; \
	    composer phpunit; st=$$?; \
	    cp /tmp/cj.bak composer.json; rm -f composer.lock; \
	    exit $$st'

##@ Maintenance
clean:  ## Remove installed deps and build artifacts
	rm -rf vendor js/node_modules js/dist
