# Executables (local)
DOCKER_COMP = docker compose
PODMAN_COMP = podman-compose

# Container repo
BUILD_REPO = benoitvignal/narvik-back

# Version extraction from composer.json
VERSION_FULL = $(shell cat composer.json | grep version | grep -o '\([0-9]\+\.\?\)\{3\}')
VERSION_MAJOR = $(shell echo $(VERSION_FULL) | grep -o '^[0-9]\+')
VERSION_MINOR = $(shell echo $(VERSION_FULL) | grep -o '^[0-9]\+\.[0-9]\+')

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php
DB_CONT = $(DOCKER_COMP) exec database

# Podman containers (for podman-based setups)
PODMAN_PHP_CONT = $(PODMAN_COMP) exec php
PODMAN_DB_CONT = $(PODMAN_COMP) exec database

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc rector rector-dry-run buildah-build buildah-build-prod buildah-build-multiplatform podman-up podman-down podman-start build-multiplatform build-multiplatform-local

# Capture the first argument as `file`
file=$(word 2,$(MAKECMDGOALS))

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

build-cloud-local:
	@docker buildx build . --builder cloud-benoitvignal-narvik-cloud --pull --no-cache -t narvik-php --target frankenphp_dev

build-prod: ## Build production image with version tags
	@docker build --pull --no-cache \
		-t $(BUILD_REPO):latest \
		-t $(BUILD_REPO):$(VERSION_MAJOR) \
		-t $(BUILD_REPO):$(VERSION_MINOR) \
		-t $(BUILD_REPO):$(VERSION_FULL) \
		--target frankenphp_prod .

build-cloud-prod:
	@docker buildx build . --builder cloud-benoitvignal-narvik-cloud --pull --no-cache \
		-t $(BUILD_REPO):latest \
		-t $(BUILD_REPO):$(VERSION_MAJOR) \
		-t $(BUILD_REPO):$(VERSION_MINOR) \
		-t $(BUILD_REPO):$(VERSION_FULL) \
		--target frankenphp_prod

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

up-prod: ## Start the docker in prod mode, be sure to have remove docker images if you run them in dev before
	IMAGES_PREFIX=prod- $(DOCKER_COMP) --env-file .env.prod.local -f compose.yaml -f compose.prod.yaml up --detach

start: build up ## Build and start the containers

start-prod: build-prod up-prod ## Build and start the containers in prod environment

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

stop: down

restart: stop up

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the PHP FPM container
	@$(PHP_CONT) bash

## —— Buildah/Podman 🦭 ———————————————————————————————————————————————————————
buildah-build: ## Build the dev image using Buildah (Podman compatible)
	@buildah build --pull --no-cache -t narvik-php:latest --target frankenphp_dev -f Containerfile .

buildah-build-prod: ## Build the prod image using Buildah (Podman compatible)
	@buildah build --pull --no-cache \
		-t $(BUILD_REPO):latest \
		-t $(BUILD_REPO):$(VERSION_MAJOR) \
		-t $(BUILD_REPO):$(VERSION_MINOR) \
		-t $(BUILD_REPO):$(VERSION_FULL) \
		--target frankenphp_prod \
		-f Containerfile .

buildah-build-multiplatform: ## Build multi-platform prod images using Buildah (amd64 + arm64)
	@echo "Building for linux/amd64..."
	@buildah build --pull --no-cache \
		--platform linux/amd64 \
		-t $(BUILD_REPO):latest-amd64 \
		--target frankenphp_prod \
		-f Containerfile .
	@echo "Building for linux/arm64..."
	@buildah build --pull --no-cache \
		--platform linux/arm64 \
		-t $(BUILD_REPO):latest-arm64 \
		--target frankenphp_prod \
		-f Containerfile .
	@echo "Creating manifest..."
	@buildah manifest create $(BUILD_REPO):latest \
		$(BUILD_REPO):latest-amd64 \
		$(BUILD_REPO):latest-arm64

podman-up: ## Start containers using podman-compose
	@$(PODMAN_COMP) up --detach

podman-down: ## Stop containers using podman-compose
	@$(PODMAN_COMP) down

podman-start: buildah-build podman-up ## Build with Buildah and start with podman-compose

podman-sh: ## Connect to the PHP container via podman-compose
	@$(PODMAN_PHP_CONT) bash

## —— Multi-platform Docker builds 🏗️ ————————————————————————————————————————
build-multiplatform: ## Build multi-platform prod images using Docker buildx (amd64 + arm64)
	@docker buildx build --pull --no-cache \
		--platform linux/amd64,linux/arm64 \
		-t $(BUILD_REPO):latest \
		-t $(BUILD_REPO):$(VERSION_MAJOR) \
		-t $(BUILD_REPO):$(VERSION_MINOR) \
		-t $(BUILD_REPO):$(VERSION_FULL) \
		--target frankenphp_prod \
		--push .

build-multiplatform-local: ## Build multi-platform prod images locally (no push)
	@docker buildx build --pull --no-cache \
		--platform linux/amd64,linux/arm64 \
		-t $(BUILD_REPO):latest \
		--target frankenphp_prod \
		--load .

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-scripts --no-interaction
vendor: composer

vendor-prod: ## Install vendors according to the current composer.lock file
vendor-prod: c=install --prefer-dist --no-dev --no-scripts --no-interaction
vendor-prod: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

cc-test: ## Clear the test cache
	@$(MAKE) --no-print-directory sf c='c:c --env=test'

cc-prod: ## Clear the prod cache
	@$(MAKE) --no-print-directory sf c='c:c --env=prod'

reload-fixture: ## Reload the database based on the default fixtures
	@$(COMPOSER) reload-fixture

test: ## Run the test suit on the app, add f=<filepath> to run the tests only in that specific file
	@$(eval f ?=)
	@if [ -z "$(f)" ]; then\
		echo "\033[42m    Running test globally    \033[m";\
	fi

	@$(COMPOSER) test $(f)

test-with-coverage: ## Run the test suit on the app with coverage report
	@$(COMPOSER) test-with-coverage

## —— Database 📦 ——————————————————————————————————————————————————————————————
db-dump: ## Dump the current database
	@$(DB_CONT) sh -c 'pg_dumpall -c -U $$POSTGRES_USER | gzip' > ./dump/dump_`date +%Y-%m-%d"_"%H_%M_%S`.sql.gz

db-restore: ## Restore a database dump. The file must be called './dump/dump.sql.gz'
	docker compose exec database sh -c 'psql -d $$POSTGRES_DB -U $$POSTGRES_USER -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"'
	gunzip < ./dump/dump.sql.gz | docker compose exec -T database sh -c 'psql -d $$POSTGRES_DB -U $$POSTGRES_USER'

## —— Code quality 🚀 ————————————————————————————————————————————————————————————————
rector: ## Run rector to fix code issues
	@$(PHP_CONT) ./vendor/bin/rector process

rector-dry-run: ## Run rector in dry-run mode to see what would be changed
	@$(PHP_CONT) ./vendor/bin/rector process --dry-run
