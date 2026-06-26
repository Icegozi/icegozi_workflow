# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`icegozi_workflow` is a Laravel 10 (PHP 8.2) Trello/Kanban-style work management app. The domain is **Boards → Columns → Tasks**, where Tasks carry Comments, Attachments, Checklists, Assignees, and a TaskHistory audit trail. Boards are shared with other users via signed-URL invitations and a board-level role system. Code comments and UI strings are in Vietnamese.

## Commands

### Docker (canonical way to run)
The app runs as a single image (Nginx + PHP-FPM + Supervisor) plus a MySQL container, orchestrated by `docker-compose.yml` and wrapped by the `Makefile`. Run `make help` for the full list.

```bash
make init        # first run: .env + build + up + key:generate
make up / down   # start / stop the stack
make logs        # follow app logs
make shell       # bash inside the app container
make artisan c="route:list"     # run any artisan command
make composer c="require ..."   # run composer in the container
make migrate / make fresh       # migrations; fresh = drop+migrate+seed
make test        # PHPUnit (no TTY)
make lint        # Laravel Pint --test (style check)
make lint-fix    # Laravel Pint (apply)
```
App is served on `http://localhost:${APP_PORT}` (→ container :80); MySQL on `127.0.0.1:${DB_PORT}`. Both ports come from `.env`.

The container `entrypoint.sh` auto-runs on every boot: copies `.env.example` if missing, `key:generate`, waits for the DB, `migrate --force` (gated by `RUN_MIGRATIONS`), optional `db:seed` (gated by `RUN_SEEDERS`), then `config:cache` + `route:cache` + `view:cache`.

### Without Docker (classic Laravel)
`composer install` → configure `.env` DB → `php artisan migrate` → `php artisan db:seed` → `npm install && npm run dev` → `php artisan serve`.

### Tests & lint
```bash
php artisan test                                   # all tests
php artisan test --filter=SomeTest                 # single test/class
php artisan test tests/Feature/ExampleTest.php     # single file
./vendor/bin/pint           # format (Pint is the configured style tool)
```
PHPUnit defines two suites, `Unit` and `Feature` (`phpunit.xml`). Only example tests currently exist.

## Architecture

### Authorization is the central concept — and it is NOT Laravel Policies
Access control is a custom board-level RBAC implemented through a pivot chain, **not** `app/Policies` and **not** Laravel Gates.

- Three roles are seeded (`PermissionSeeder`): `board_viewer`, `board_editor`, `board_member_manager`.
- The relationship chain is: `User` ↔ `permission_users` (pivot model `PermissionUser`) ↔ `board_permissions` (pivot model `BoardPermission`) ↔ `Board`. A user's role on a specific board exists only if there is a `permission_users` row (user+permission) **and** a `board_permissions` row linking that to the board.
- The board **owner** (`boards.user_id`) implicitly has every permission — this is special-cased everywhere (e.g. `User::hasBoardPermission()` returns `true` immediately for the owner, and board member/assignee queries always union in `board.user_id`).
- Controllers enforce access by calling `authorizeBoardAccess($board, [roles])` or `authorizeTaskAccess($task, [roles])` at the top of each action; these abort 403 unless `User::hasBoardPermission()` passes for one of the listed roles.

⚠️ **`authorizeBoardAccess`/`authorizeTaskAccess` are copy-pasted private methods duplicated across `app/Http/Controllers/User/*Controller.php`** (BoardController, TaskController, ColumnController, ChecklistController, AttachmentController, CommentController, AssigneeController) — they are not in a shared trait or base class. When changing authorization logic, update every copy, or refactor them into one trait.

### Controllers are organized by audience
`app/Http/Controllers/{Auth,Admin,User}/`. Auth is a hand-rolled session login/register (`Auth\LoginController`, `Auth\RegisterController`) — **not** Breeze/Jetstream/Fortify. Admin vs. user separation is driven by the `users.is_admin` flag and the `is_admin` / `is_user` route middleware (aliased in `app/Http/Kernel.php`). `/dashboard` redirects by role. Form validation lives in `app/Http/Requests/`.

### Models carry query logic
Models frequently expose **static query helpers** (e.g. `Board::getBoardsByUser`, `Board::createBoard`, `Board::getBoardData`) and raw `DB::table()` joins for the permission pivots, rather than keeping all queries in controllers. They alias root-namespace facades (`use DB;`, `use Auth;`, `use Hash;`). When adding board/member queries, prefer the existing helpers like `Board::getAssignedUsersByBoardId()`.

### Frontend is a hybrid — most JS is NOT in the Vite pipeline
- **Vite** (`vite.config.mjs`) compiles only `resources/css/app.css` and `resources/js/app.js`, loaded via `@vite(...)` in the Blade layouts.
- **The bulk of the UI** is an AdminLTE + Bootstrap 5 + jQuery theme whose per-feature scripts are **static files in `public/assets/js/`** (`task.js`, `column.js`, `checklist.js`, `comment.js`, `assignee.js`, `permission.js`, …) and vendor plugins in `public/plugins/`. These are loaded through the `asset_min()` helper (`app/helpers.php`), which on-demand minifies a `.css`/`.js` into a sibling `.min.*` (and cache-busts via `filemtime`) **only when the request has a `?minify` query param**. To change board/task behavior in the browser, edit the matching file under `public/assets/js/`, not `resources/js/`.
- Layouts: `resources/views/layouts/{app,admin,user,board,auth}.blade.php`. Views are grouped under `resources/views/{admin,user,auth,components}`. UI strings localized under `resources/lang/vi`.

### Other notes
- Sanctum is installed and `routes/api.php` is auth:sanctum-guarded, but the app is currently almost entirely server-rendered web routes (`routes/web.php`); API surface is minimal.
- Notifications: custom `App\Models\Notification` (a DB table the app reads directly) alongside Laravel's `app/Notifications/` (e.g. `BoardInvitationNotification`). Don't assume the standard `notifications` morphable table semantics.

## Deployment gotchas (learned the hard way)

- **`bootstrap/cache/*.php` must never ship into the Docker image.** It is `.dockerignore`d. Those compiled `packages.php`/`services.php` are generated on the host *with* dev dependencies; baking them into the `--no-dev` production image makes Laravel try to load dev-only providers (e.g. `nunomaduro/collision`) and crash-loop the container.
- **nginx forces `HTTP_HOST` to `$http_host`** in `docker/nginx/default.conf` (overriding Debian's `fastcgi_params`, which uses `$host` and strips the port). Without this, Laravel generates redirects without the published port, breaking login/register and any absolute-URL redirect when the app is served on a non-80 host port.
- The entrypoint caches config/routes/views on boot. After editing `.env`, routes, or config **inside a running container**, run `php artisan optimize:clear` (or rebuild) — stale caches will otherwise win.
- `MYSQL_USER` cannot be `root` (MySQL reserves it); the compose DB credentials must use a non-root username.
