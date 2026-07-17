# =============================================================================
#  icegozi_workflow — Makefile
#  Laravel 10 · PHP 8.2-FPM · Nginx · Supervisor · MySQL 8 · Docker Compose
#
#  Kiến trúc:
#    - Laravel + Inertia + Vue 3 + Vite.
#    - Mọi thao tác đều thực hiện trong Docker.
#    - Composer, Node.js và Vite đều chạy trong container app.
#
#  Quy ước:
#    - make help                     : Hiển thị toàn bộ lệnh.
#    - make artisan c="route:list"  : Chạy artisan.
#    - make composer c="update"     : Chạy composer.
#    - make npm c="run build"       : Chạy npm.
# =============================================================================

# ----- Cấu hình shell --------------------------------------------------------
SHELL := /usr/bin/bash
.SHELLFLAGS := -euo pipefail -c
.ONESHELL:
.DEFAULT_GOAL := help

# ----- Biến cấu hình ---------------------------------------------------------
COMPOSE     ?= docker compose
APP_SERVICE ?= app
DB_SERVICE  ?= db

EXEC   := $(COMPOSE) exec $(APP_SERVICE)
EXEC_T := $(COMPOSE) exec -T $(APP_SERVICE)

# Nạp biến từ .env nếu tồn tại
ifneq (,$(wildcard ./.env))
include .env
export
endif

# ----- Màu sắc output --------------------------------------------------------
CYAN  := \033[36m
GREEN := \033[32m
YELL  := \033[33m
RESET := \033[0m

# =============================================================================
#  Trợ giúp
# =============================================================================
.PHONY: help
help: ## Hiển thị danh sách target
	@printf "$(CYAN)icegozi_workflow$(RESET) — các lệnh khả dụng:\n\n"
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) \
		| sort \
		| awk 'BEGIN {FS=":.*?## "}; {printf "  $(GREEN)%-18s$(RESET) %s\n", $$1, $$2}'
	@printf "\n$(YELL)Ví dụ:$(RESET) make artisan c=\"route:list\"\n"

# =============================================================================
#  Vòng đời môi trường
# =============================================================================
.PHONY: build
build: ## Build Docker image
	$(COMPOSE) build

.PHONY: rebuild
rebuild: ## Build lại image (không dùng cache)
	$(COMPOSE) build --no-cache

.PHONY: up
up: ## Khởi động toàn bộ service
	$(COMPOSE) up -d

.PHONY: down
down: ## Dừng và gỡ container
	$(COMPOSE) down

.PHONY: restart
restart: ## Khởi động lại service
	$(COMPOSE) restart

.PHONY: stop
stop: ## Dừng service
	$(COMPOSE) stop

.PHONY: ps
ps: ## Hiển thị trạng thái container
	$(COMPOSE) ps

.PHONY: logs
logs: ## Theo dõi log
	$(COMPOSE) logs -f --tail=100

# =============================================================================
#  Truy cập container
# =============================================================================
.PHONY: shell
shell: ## Mở bash trong container app
	$(EXEC) bash

.PHONY: db-shell
db-shell: ## Mở MySQL shell
	$(COMPOSE) exec $(DB_SERVICE) sh -c 'mysql -u$$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'

# =============================================================================
#  Laravel / Composer / NPM
# =============================================================================
.PHONY: artisan
artisan: ## Chạy artisan: make artisan c="route:list"
	$(EXEC) php artisan $(c)

.PHONY: composer
composer: ## Chạy composer: make composer c="update"
	$(EXEC) composer $(c)

.PHONY: npm
npm: ## Chạy npm: make npm c="run build"
	$(EXEC) npm $(c)

.PHONY: dev
dev: ## Chạy Vite dev server
	$(EXEC) npm run dev

# =============================================================================
#  Database
# =============================================================================
.PHONY: migrate
migrate: ## Chạy migration
	$(EXEC) php artisan migrate --force

.PHONY: migrate-status
migrate-status: ## Kiểm tra trạng thái migration
	$(EXEC) php artisan migrate:status

.PHONY: rollback
rollback: ## Rollback migration gần nhất
	$(EXEC) php artisan migrate:rollback --force

.PHONY: seed
seed: ## Chạy database seeder
	$(EXEC) php artisan db:seed --force

.PHONY: fresh
fresh: ## Xóa DB và migrate lại (kèm seed)
	@printf "$(YELL)[fresh]$(RESET) Toàn bộ dữ liệu sẽ bị xóa.\n"
	$(EXEC) php artisan migrate:fresh --seed --force

# =============================================================================
#  Cache
# =============================================================================
.PHONY: clear
clear: ## Xóa toàn bộ cache
	$(EXEC) php artisan optimize:clear

.PHONY: optimize
optimize: ## Cache config/route/view
	$(EXEC) php artisan config:cache
	$(EXEC) php artisan route:cache
	$(EXEC) php artisan view:cache

# =============================================================================
#  Test
# =============================================================================
.PHONY: test
test: ## Chạy PHPUnit an toàn trên SQLite tạm (dùng c="--filter=TenTest" để lọc)
	$(EXEC_T) env \
		APP_ENV=testing \
		APP_CONFIG_CACHE=/tmp/laravel-test-config.php \
		DB_CONNECTION=sqlite \
		DB_DATABASE=:memory: \
		CACHE_DRIVER=array \
		SESSION_DRIVER=array \
		QUEUE_CONNECTION=sync \
		php artisan test $(c)

# =============================================================================
#  Chất lượng mã / Git hooks
# =============================================================================
.PHONY: quality
quality: ## Kiểm tra cú pháp, Pint, PHPMD và PHPCS
	./scripts/php-quality.sh check

.PHONY: quality-fix
quality-fix: ## Tự sửa style PHP bằng Pint và PHPCBF, sau đó kiểm tra lại
	./scripts/php-quality.sh fix

.PHONY: pre-push
pre-push: ## Chạy toàn bộ kiểm tra bắt buộc trước khi push
	./scripts/pre-push.sh

.PHONY: hooks
hooks: ## Cài Git hooks dùng chung của repository
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-push scripts/pre-push.sh scripts/php-quality.sh
	@printf "$(GREEN)[hooks]$(RESET) Đã cài pre-push hook.\n"

# =============================================================================
#  Dọn dẹp
# =============================================================================
.PHONY: clean
clean: ## Gỡ container và volume (⚠ mất dữ liệu DB)
	@printf "$(YELL)[clean]$(RESET) Gỡ container và volume.\n"
	$(COMPOSE) down -v --remove-orphans

.PHONY: prune
prune: ## Dọn Docker cache
	docker system prune -f
