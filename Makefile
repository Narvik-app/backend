# Executables (local)
DOCKER_COMP = docker compose

# Container repo
BUILD_REPO = benoitvignal/narvik-back

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php
DB_CONT = $(DOCKER_COMP) exec database

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc rector rector-dry-run

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

build-prod:
	@docker build --pull --no-cache -t $(BUILD_REPO):latest -t $(BUILD_REPO):`cat composer.json | grep version | grep '\([0-9]\+\.\?\)\{3\}' -o | grep '^[0-9]\+' -o` -t $(BUILD_REPO):`cat composer.json | grep version | grep '\([0-9]\+\.\?\)\{3\}' -o | grep '^[0-9]\+\.[0-9]\+' -o` -t $(BUILD_REPO):`cat composer.json | grep version | grep '\([0-9]\+\.\?\)\{3\}' -o` --target frankenphp_prod .

build-cloud-prod:
	@docker buildx build . --builder cloud-benoitvignal-narvik-cloud --pull --no-cache -t $(BUILD_REPO):latest -t $(BUILD_REPO):`cat composer.json | grep version | grep '\([0-9]\+\.\?\)\{3\}' -o | grep '^[0-9]\+' -o` -t $(BUILD_REPO):`cat composer.json | grep version | grep '\([0-9]\+\.\?\)\{3\}' -o | grep '^[0-9]\+\.[0-9]\+' -o` -t $(BUILD_REPO):`cat composer.json | grep version | grep '\([0-9]\+\.\?\)\{3\}' -o` --target frankenphp_prod

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

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

cc-test: ## Clear the test cache
	@$(MAKE) --no-print-directory sf c='c:c --env=test'

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
