.DEFAULT_GOAL: help

INTERACTIVE := $(shell [ -t 0 ] && echo 1 || echo 0)
ifeq ($(INTERACTIVE), 1)
	DOCKER_FLAGS += -t
else
	DOCKER_COMPOSE_RUN_FLAGS += -T
endif

COMPOSER_HOME ?= ${HOME}/.composer
COMPOSER_CLI = docker run $(DOCKER_FLAGS) -i --rm \
	--env COMPOSER_HOME=${COMPOSER_HOME} \
	--volume ${COMPOSER_HOME}:${COMPOSER_HOME} \
	--volume ${PWD}:/app \
	--user $(shell id -u):$(shell id -g) \
	--workdir /app \
	composer:2.8.9

DOCKER_COMPOSE_FILE ?= compose.yaml
DOCKER_COMPOSE_RUN = docker compose -f $(DOCKER_COMPOSE_FILE) run --rm

.PHONY: $(filter-out vendor, $(shell awk -F: '/^[a-zA-Z0-9_%-]+:/ { print $$1 }' $(MAKEFILE_LIST) | sort | uniq))

help: ## Display this help
	@awk 'BEGIN {FS = ":.* ##"; printf "\n\033[1mUsage:\033[0m\n  make \033[32m<target>\033[0m\n"} /^[a-zA-Z_-]+:.* ## / { printf "  \033[33m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Installation
install: vendor ## Install all necessary things

vendor: composer.json composer.lock
	$(COMPOSER_CLI) install --ignore-platform-reqs

##@ CLI
composer-cli: ## Composer runtime. See https://getcomposer.org/doc/03-cli.md
	$(COMPOSER_CLI) /bin/sh

php-cli: ## PHP runtime
	$(DOCKER_COMPOSE_RUN) --no-deps php sh

##@ Doctrine
migrations-generate: ## Generate empty migration file
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console doctrine:migrations:generate

migrations-migrate: ## Run migrations against database
	$(DOCKER_COMPOSE_RUN) --no-deps php bin/console doctrine:migrations:migrate

##@ Code analysis
static-code-analysis: ## Code analysis
	$(DOCKER_COMPOSE_RUN) --no-deps php ./vendor/bin/phpstan analyse --memory-limit=512M

apply-cs: ## Apply coding standards with PHP CS Fixer
	$(DOCKER_COMPOSE_RUN) -e PHP_CS_FIXER_IGNORE_ENV=1 --no-deps php vendor/bin/php-cs-fixer fix --show-progress=dots --diff --config=.php-cs-fixer.dist.php

##@ Application
start: ## Start the app
	docker compose -f $(DOCKER_COMPOSE_FILE) up -d --remove-orphans

stop: ## Stop the app
	docker compose -f $(DOCKER_COMPOSE_FILE) down --remove-orphans $(R)

##@ Tests
test: DOCKER_COMPOSE_FILE=compose.test.yaml
test:
	@echo "Running database migrations for test environment..."
	$(DOCKER_COMPOSE_RUN) php-test bin/console doctrine:migrations:migrate --no-interaction --quiet || true
	@echo "Running tests..."
	$(DOCKER_COMPOSE_RUN) php-test ./bin/phpunit $(R)
	docker compose -f $(DOCKER_COMPOSE_FILE) down -v

##@ OAuth utils
key-generate: ## generate key pair. R one of rsa, ecdsa
	$(DOCKER_COMPOSE_RUN) php bin/console oauth2:key:generate $(R)

admin-generate: ## generate quickly first admin user
	$(DOCKER_COMPOSE_RUN) php bin/console oauth2:user:create --email=admin@example.org --password=admin --role=ROLE_ADMIN
