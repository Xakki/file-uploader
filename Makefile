SHELL = /bin/bash
### https://makefiletutorial.com/

-include ./.env
export

docker := docker run -it -v $(PWD):/app ${DOCKER_USER}/${TAG}
composer := $(docker) composer

run: docker-build clear composer-u test

clear:
	$(docker) rm -rf vendor composer.lock .phpunit.result.cache

docker-login:
	docker login ${HOST} -u ${DOCKER_USER} -p ${DOCKER_PASS}

docker-push:
	docker push ${DOCKER_USER}/${TAG}

docker-build:
	docker pull ${PHP_IMAGE}
	docker build -t ${DOCKER_USER}/${TAG} --build-arg PHP_IMAGE=${PHP_IMAGE} .

bash:
	$(docker) bash

composer-i:
	$(composer) i --prefer-dist --no-scripts

composer-u:
	$(composer) u --prefer-dist $(name)

composer-r:
	$(composer) r --prefer-dist $(name)

cs-fix:
	$(composer) cs-fix

cs-check:
	$(composer) cs-check

phpstan:
	$(composer) phpstan

phpunit:
	$(composer) phpunit

test:
	$(composer) cs-check
	$(composer) phpstan
	$(composer) phpunit
