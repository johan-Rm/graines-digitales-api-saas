SHELL := /bin/sh

DOCKER_COMPOSE ?= docker compose
API_SERVICE ?= api
POSTGRES_SERVICE ?= postgres

EXEC = $(DOCKER_COMPOSE) exec $(API_SERVICE)
EXEC_T = $(DOCKER_COMPOSE) exec -T $(API_SERVICE)

.DEFAULT_GOAL := help

-include Makefile.dev
-include Makefile.preprod
-include Makefile.prod

.PHONY: help
help: ## List available commands.
	@awk 'BEGIN {FS = ":.*##"; printf "\nAvailable commands:\n"} /^[a-zA-Z0-9_.-]+:.*##/ {printf "  %-28s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

.PHONY: ps up up-build down restart logs logs-api shell console composer
ps: ## Show Docker Compose services.
	$(DOCKER_COMPOSE) ps

up: ## Start Docker Compose services.
	$(DOCKER_COMPOSE) up -d

up-build: ## Build and start Docker Compose services.
	$(DOCKER_COMPOSE) up -d --build

down: ## Stop Docker Compose services.
	$(DOCKER_COMPOSE) down

restart: down up ## Restart Docker Compose services.

logs: ## Follow all Docker Compose logs.
	$(DOCKER_COMPOSE) logs -f

logs-api: ## Follow API container logs.
	$(DOCKER_COMPOSE) logs -f $(API_SERVICE)

shell: ## Open a shell in the API container.
	$(EXEC) sh

console: ## Run a Symfony console command. Usage: make console CMD="debug:router"
	@test -n "$(CMD)" || (echo 'Missing CMD. Usage: make console CMD="debug:router"' && exit 1)
	$(EXEC) php bin/console $(CMD)

composer: ## Run a Composer command. Usage: make composer CMD="show --direct"
	@test -n "$(CMD)" || (echo 'Missing CMD. Usage: make composer CMD="show --direct"' && exit 1)
	$(EXEC) composer $(CMD)

.PHONY: install update-lock cache-clear jwt-keypair migrate schema-validate lint-container
install: ## Install Composer dependencies in the API container.
	$(EXEC) composer install

update-lock: ## Refresh composer.lock metadata without changing package versions.
	$(EXEC) composer update --lock --no-scripts

cache-clear: ## Clear Symfony cache.
	$(EXEC) php bin/console cache:clear

jwt-keypair: ## Generate JWT keypair if missing.
	$(EXEC) php bin/console lexik:jwt:generate-keypair --skip-if-exists

migrate: ## Run Doctrine migrations.
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction

schema-validate: ## Validate Doctrine mapping and database schema.
	$(EXEC) php bin/console doctrine:schema:validate

lint-container: ## Validate Symfony service container.
	$(EXEC) php bin/console lint:container

.PHONY: test test-file test-filter test-debug
test: ## Run the PHPUnit suite.
	$(EXEC) php bin/phpunit

test-file: ## Run one PHPUnit file. Usage: make test-file FILE=tests/Functional/AuthTest.php
	@test -n "$(FILE)" || (echo 'Missing FILE. Usage: make test-file FILE=tests/Functional/AuthTest.php' && exit 1)
	$(EXEC) php bin/phpunit $(FILE)

test-filter: ## Run PHPUnit tests matching a filter. Usage: make test-filter FILTER=ProjectIsolation
	@test -n "$(FILTER)" || (echo 'Missing FILTER. Usage: make test-filter FILTER=ProjectIsolation' && exit 1)
	$(EXEC) php bin/phpunit --filter "$(FILTER)"

test-debug: ## Run PHPUnit with the debug configuration.
	$(EXEC) php bin/phpunit --configuration=phpunit_debug.xml

.PHONY: analyse cs-check cs-fix deptrac rector-check rector-fix infection audit quality quality-fast diff-check
analyse: ## Run PHPStan.
	$(EXEC) composer analyse

cs-check: ## Check PHP style with PHP-CS-Fixer.
	$(EXEC) composer cs:check

cs-fix: ## Fix PHP style with PHP-CS-Fixer.
	$(EXEC) composer cs:fix

deptrac: ## Check architecture dependency rules.
	$(EXEC) composer deptrac

rector-check: ## Preview Rector refactors.
	$(EXEC) composer rector:check

rector-fix: ## Apply Rector refactors.
	$(EXEC) composer rector:fix

infection: ## Run Infection mutation testing.
	$(EXEC) composer infection

audit: ## Run Composer security audit.
	$(EXEC) composer audit

quality-fast: ## Run fast non-destructive quality checks.
	$(EXEC) composer quality:fast

quality: ## Run full project quality script, including PHPUnit.
	$(EXEC) composer quality

diff-check: ## Check Git diff for whitespace errors.
	git diff --check
