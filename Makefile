SHELL = /bin/bash
# Single entry point for all dev/test ops. PHP/Node run in Docker (override the images if needed).

PUID := $(shell id -u)
PGID := $(shell id -g)
PHP_IMAGE  ?= lfu-test:latest
NODE_IMAGE ?= node:22-alpine

# Containers run as the current user (PUID:PGID) so files they create are not root-owned.
# Repo mounted at /repo; caches kept under /tmp (writable for a non-root, home-less uid).
DOCKER_USER = --user $(PUID):$(PGID) -e HOME=/tmp
DOCKER_PHP  = docker run --rm $(DOCKER_USER) -v "$(CURDIR)":/repo -e COMPOSER_HOME=/tmp/c -e COMPOSER_CACHE_DIR=/tmp/cc
DOCKER_NODE = docker run --rm $(DOCKER_USER) -v "$(CURDIR)":/repo -e npm_config_cache=/tmp/npm

.DEFAULT_GOAL := help
.PHONY: help install test test-core test-js coverage conformance phpstan pint pint-fix clean verify-binding

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

coverage:  ## js coverage with thresholds (regenerates package-lock.json if needed)
	$(DOCKER_NODE) -w /repo/js $(NODE_IMAGE) sh -lc 'npm install && npm run test:coverage'

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
	docker run --rm $(DOCKER_USER) -v "$(dir $(CURDIR))":/work -e COMPOSER_HOME=/tmp/c -e COMPOSER_CACHE_DIR=/tmp/cc \
	  -w /work/$(notdir $(DIR)) $(PHP_IMAGE) sh -lc '\
	    cp composer.json /tmp/cj.bak; \
	    composer config repositories.coredev path ../file-uploader; \
	    composer require "xakki/file-uploader:*@dev" --no-update -W -q; \
	    composer update --no-interaction; \
	    composer phpunit; st=$$?; \
	    cp /tmp/cj.bak composer.json; rm -f composer.lock; \
	    exit $$st'

test-binding:  ## Full CI for a sibling binding against published core (phpunit+phpstan+cs-check). DIR=../file-uploader-laravel
	docker run --rm $(DOCKER_USER) -v "$(dir $(CURDIR))":/work -e COMPOSER_HOME=/tmp/c -e COMPOSER_CACHE_DIR=/tmp/cc \
	  -w /work/$(notdir $(DIR)) $(PHP_IMAGE) sh -lc '\
	    mkdir -p /tmp/b && tar --exclude=vendor --exclude=.git --exclude=composer.lock -cf - . | tar -xf - -C /tmp/b && \
	    cd /tmp/b && composer update --no-interaction && \
	    composer phpunit && composer phpstan && composer cs-check'

pint-fix-binding:  ## Auto-fix code style (pint) in a sibling binding (edits host files). DIR=../file-uploader-laravel
	docker run --rm $(DOCKER_USER) -v "$(dir $(CURDIR))":/work -e COMPOSER_HOME=/tmp/c -e COMPOSER_CACHE_DIR=/tmp/cc \
	  -w /work/$(notdir $(DIR)) $(PHP_IMAGE) sh -lc 'composer global require -q laravel/pint && $$(composer global config home)/vendor/bin/pint src tests'

##@ Maintenance
clean:  ## Remove installed deps and build artifacts
	rm -rf vendor js/node_modules js/dist
