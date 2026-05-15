PHP_VERSION ?= 8.3.10
DISTRO ?= debian

.DEFAULT_GOAL : help

help: ## Show this help
	@printf "\033[33m%s:\033[0m\n" 'Available commands'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[32m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
all: build-image build test ## Build image, build, test
build-image: ## Build docker image
	PHP_VERSION=$(PHP_VERSION) docker compose build $(DISTRO)
shell: ## Shell
	docker compose run $(DISTRO) bash
build: ## Build extension
	docker compose run $(DISTRO) ./build.sh
test: ## Run tests
	docker compose run $(DISTRO) make test
clean: ## Clean
	docker compose run $(DISTRO) make clean
distclean: ## Clean
	docker compose run $(DISTRO) make distclean
bench: ## Run benchmark: make bench [FILE=tests/bench_require.php] [ARGS=with_attrs]
ifdef FILE
	docker compose run $(DISTRO) php -d extension=modules/apa.so $(FILE) $(ARGS)
else
	@echo "=== Flat requires ===" && \
	docker compose run $(DISTRO) php -d extension=modules/apa.so tests/bench_require.php no_attrs && \
	docker compose run $(DISTRO) php -d extension=modules/apa.so tests/bench_require.php with_attrs && \
	echo "=== Hierarchy ===" && \
	docker compose run $(DISTRO) php -d extension=modules/apa.so tests/bench_hierarchy.php no_attrs && \
	docker compose run $(DISTRO) php -d extension=modules/apa.so tests/bench_hierarchy.php with_attrs && \
	echo "=== 1M function calls ===" && \
	docker compose run $(DISTRO) php -d extension=modules/apa.so tests/bench_no_attrs.php && \
	echo "=== APA vs Reflection ===" && \
	docker compose run $(DISTRO) php -d extension=modules/apa.so tests/bench_vs_reflection.php
endif
integration: ## Run integration test (HTTP server)
	docker compose run $(DISTRO) bash tests/integration/run_test.sh
valgrind: ## Run tests with valgrind (memory leak check)
	docker compose run $(DISTRO) php -d extension=modules/apa.so run-tests.php -d extension=modules/apa.so -m tests/
pie: ## Test PIE install (builds from clean source)
	docker compose build --no-cache pie
.PHONY: clean build test bench integration valgrind pie all help
