COMPOSE ?= docker compose
ARGS ?=

.PHONY: docker-build docker-up docker-down docker-init docker-configure docker-import docker-setup docker-cli docker-update docker-shell docker-logs docker-composer docker-test

docker-build:
	$(COMPOSE) build --pull
docker-up:
	$(COMPOSE) up -d
docker-down:
	$(COMPOSE) down
docker-init:
	$(COMPOSE) run --rm init init
docker-configure:
	$(COMPOSE) run --rm init configure
docker-import:
	$(COMPOSE) run --rm cli php /usr/local/lib/aowow/import.php
docker-setup:
	$(COMPOSE) run --rm cli php aowow --setup $(ARGS)
docker-cli:
	$(COMPOSE) run --rm cli php aowow $(ARGS)
docker-update:
	$(COMPOSE) run --rm cli php aowow --update $(ARGS)
docker-shell:
	$(COMPOSE) run --rm cli bash
docker-logs:
	$(COMPOSE) logs --tail=100 -f
docker-composer:
	$(COMPOSE) run --rm --no-deps --entrypoint composer cli check-platform-reqs --no-dev
docker-test:
	bash tests/docker/smoke.sh

.PHONY: dev-init dev-build dev-up dev-watch dev-down dev-setup dev-cli dev-shell dev-test
dev-init:
	bash docker/development/dev.sh init
dev-build:
	bash docker/development/dev.sh build
dev-up:
	bash docker/development/dev.sh up
dev-watch:
	bash docker/development/dev.sh watch
dev-down:
	bash docker/development/dev.sh down
dev-setup:
	bash docker/development/dev.sh cli --setup $(ARGS)
dev-cli:
	bash docker/development/dev.sh cli $(ARGS)
dev-shell:
	bash docker/development/dev.sh shell
dev-test:
	bash docker/development/dev.sh test $(ARGS)
