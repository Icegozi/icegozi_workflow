# =============================================================================
#  icegozi_workflow — Makefile
#  Laravel 10 · PHP 8.2-FPM · Nginx · Supervisor · MySQL 8 (Docker Compose)
#
#  Quy ước:
#    - Mọi lệnh đều đi qua Docker Compose; không cần PHP/Composer/Node trên host.
#    - `make help` liệt kê toàn bộ target (nguồn duy nhất của sự thật).
#    - Tham số động: `make artisan c="route:list"`, `make composer c="require ..."`
# =============================================================================

# ----- Cấu hình shell: fail nhanh, fail rõ ----------------------------------
SHELL := /usr/bin/bash
.SHELLFLAGS := -euo pipefail -c
.ONESHELL:
.DEFAULT_GOAL := help

# ----- Biến cấu hình ---------------------------------------------------------
COMPOSE      ?= docker compose
APP_SERVICE  ?= app
DB_SERVICE   ?= db

# Chạy artisan/composer trong container app (đã có sẵn PHP + extension)
EXEC         := $(COMPOSE) exec $(APP_SERVICE)
EXEC_T       := $(COMPOSE) exec -T $(APP_SERVICE)   # -T: không cấp TTY (CI/pipe)

# Nạp biến từ .env nếu tồn tại (cho DB_*, APP_PORT...)
ifneq (,$(wildcard ./.env))
include .env
export
endif

# ----- Màu sắc cho output ----------------------------------------------------
CYAN  := \033[36m
GREEN := \033[32m
YELL  := \033[33m
RESET := \033[0m

# =============================================================================
#  Trợ giúp
# =============================================================================
.PHONY: help
help: ## Hiển thị danh sách target (mặc định)
	@printf "$(CYAN)icegozi_workflow$(RESET) — các lệnh khả dụng:\n\n"
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) \
		| sort \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-18s$(RESET) %s\n", $$1, $$2}'
	@printf "\n$(YELL)Ví dụ:$(RESET) make artisan c=\"migrate:status\"\n"

# =============================================================================
#  Vòng đời môi trường
# =============================================================================
.PHONY: init
init: ## Khởi tạo lần đầu: .env + build + up + key
	@test -f .env || (cp .env.example .env && printf "$(GREEN)[init]$(RESET) Đã tạo .env từ .env.example\n")
	@$(MAKE) build
	@$(MAKE) up
	@$(MAKE) key

.PHONY: build
build: ## Build image (multi-stage, có cache)
	$(COMPOSE) build

.PHONY: rebuild
rebuild: ## Build lại không dùng cache
	$(COMPOSE) build --no-cache --pull

.PHONY: up
up: ## Khởi động toàn bộ service (nền)
	$(COMPOSE) up -d

.PHONY: down
down: ## Dừng và gỡ container (giữ volume)
	$(COMPOSE) down

.PHONY: restart
restart: ## Khởi động lại các service
	$(COMPOSE) restart

.PHONY: stop
stop: ## Tạm dừng service (không gỡ)
	$(COMPOSE) stop

.PHONY: ps
ps: ## Trạng thái container
	$(COMPOSE) ps

.PHONY: logs
logs: ## Theo dõi log app (Ctrl-C để thoát)
	$(COMPOSE) logs -f --tail=100 $(APP_SERVICE)

.PHONY: logs-all
logs-all: ## Theo dõi log toàn bộ service
	$(COMPOSE) logs -f --tail=100

# =============================================================================
#  Truy cập container
# =============================================================================
.PHONY: shell
shell: ## Mở bash trong container app
	$(EXEC) bash

.PHONY: db-shell
db-shell: ## Mở mysql client trong container db
	$(COMPOSE) exec $(DB_SERVICE) sh -c 'mysql -u$$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'

# =============================================================================
#  Laravel / Artisan / Composer / Node
# =============================================================================
.PHONY: artisan
artisan: ## Chạy artisan tùy ý: make artisan c="route:list"
	$(EXEC) php artisan $(c)

.PHONY: composer
composer: ## Chạy composer tùy ý: make composer c="install"
	$(EXEC) composer $(c)

.PHONY: key
key: ## Sinh APP_KEY nếu thiếu
	$(EXEC) php artisan key:generate --force

.PHONY: tinker
tinker: ## Mở Laravel Tinker (REPL)
	$(EXEC) php artisan tinker

# =============================================================================
#  Cơ sở dữ liệu
# =============================================================================
.PHONY: migrate
migrate: ## Chạy migration
	$(EXEC) php artisan migrate --force

.PHONY: migrate-status
migrate-status: ## Trạng thái migration
	$(EXEC) php artisan migrate:status

.PHONY: rollback
rollback: ## Rollback batch migration gần nhất
	$(EXEC) php artisan migrate:rollback --force

.PHONY: seed
seed: ## Chạy seeder
	$(EXEC) php artisan db:seed --force

.PHONY: fresh
fresh: ## Drop toàn bộ bảng + migrate lại + seed (⚠ mất dữ liệu)
	@printf "$(YELL)[fresh]$(RESET) Toàn bộ dữ liệu DB sẽ bị xóa.\n"
	$(EXEC) php artisan migrate:fresh --seed --force

# =============================================================================
#  Cache / Tối ưu
# =============================================================================
.PHONY: optimize
optimize: ## Cache config/route/view (production)
	$(EXEC) php artisan config:cache
	$(EXEC) php artisan route:cache
	$(EXEC) php artisan view:cache

.PHONY: clear
clear: ## Xóa toàn bộ cache (config/route/view/app)
	$(EXEC) php artisan optimize:clear

# =============================================================================
#  Chất lượng mã: test & lint
# =============================================================================
.PHONY: test
test: ## Chạy test suite (PHPUnit) — không TTY
	$(EXEC_T) php artisan test

.PHONY: lint
lint: ## Kiểm tra code style (Laravel Pint, dry-run)
	$(EXEC_T) ./vendor/bin/pint --test

.PHONY: lint-fix
lint-fix: ## Tự sửa code style (Laravel Pint)
	$(EXEC) ./vendor/bin/pint

# =============================================================================
#  Tài nguyên tĩnh (minify)
# =============================================================================
# Chạy bằng PHP trên host để các file .min.* được sinh ngay trong mã nguồn
# (commit được + build sẵn vào image). Đổi qua PHP khác bằng: make minifyjs PHP=php8.3
PHP ?= php

.PHONY: minifyjs
minifyjs: ## Minify toàn bộ JS trong public/assets/js -> *.min.js
	@printf "$(CYAN)[minify]$(RESET) JS trong public/assets/js\n"
	$(PHP) scripts/minify-assets.php js

.PHONY: minifycss
minifycss: ## Minify toàn bộ CSS trong public/assets/css -> *.min.css
	@printf "$(CYAN)[minify]$(RESET) CSS trong public/assets/css\n"
	$(PHP) scripts/minify-assets.php css

.PHONY: minify
minify: minifyjs minifycss ## Minify cả JS và CSS

# =============================================================================
#  Dọn dẹp
# =============================================================================
.PHONY: clean
clean: ## Gỡ container + volume (⚠ mất dữ liệu DB)
	@printf "$(YELL)[clean]$(RESET) Gỡ container và volume (gồm dữ liệu DB).\n"
	$(COMPOSE) down -v --remove-orphans

.PHONY: prune
prune: ## Dọn image/build cache treo của Docker (toàn hệ thống)
	docker system prune -f
