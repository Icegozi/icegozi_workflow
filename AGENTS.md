# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel 10 Kanban application with an Inertia/Vue 3 interface. Backend code lives in `app/`: controllers are grouped by audience under `app/Http/Controllers/{Admin,Auth,User}`, validation belongs in `app/Http/Requests`, and domain behavior is organized in `app/Models` and `app/Support`. Routes are defined in `routes/`, while migrations, factories, and seeders live in `database/`.

Frontend entry points are `resources/js/app.js` and `resources/css/app.css`. Put reusable Vue components in `resources/js/Components`, layouts in `Layouts`, route-level screens in `Pages`, and shared composition logic in `composables`. Public static assets belong in `public/`. Tests are split between `tests/Unit` and `tests/Feature`.

## Build, Test, and Development Commands

Docker Compose, wrapped by the Makefile, is the canonical environment.

- `make build && make up`: build and start the app, MySQL, and Mailpit services.
- `make dev`: run the Vite development server in the app container.
- `make npm c="run build"`: create production frontend assets.
- `make migrate` or `make seed`: apply schema changes or load sample data.
- `make test`: run the complete PHPUnit suite.
- `make quality`: check PHP syntax, Pint, PHPMD, and PHPCS; `make quality-fix` applies safe style fixes.
- `make artisan c="route:list"`: run an arbitrary Artisan command.
- `make help`: list all supported targets.

Use `make fresh` cautiously: it drops and reseeds the database.

## Coding Style & Naming Conventions

PHP follows PSR-12 with four-space indentation. Use PascalCase for classes, camelCase for methods and variables, and Laravel conventions for migrations and controllers (`CreateStatusesTable`, `BoardController`). Check PHP with `vendor/bin/pint --test`, `vendor/bin/phpcs --standard=phpcs.xml`, and `vendor/bin/phpmd app text phpmd.xml`; run Pint without `--test` to format.

Vue components use PascalCase filenames such as `KanbanCard.vue`; composables use the `useThing.js` pattern. Follow the existing `<script setup>` style and four-space indentation.

## Testing Guidelines

PHPUnit 10 uses in-memory SQLite as configured in `phpunit.xml`. Name files `*Test.php` and test methods `test_*`. Prefer feature tests for routes, authorization, database effects, and Inertia responses; use `RefreshDatabase` where state is created. No numeric coverage threshold is configured, but new behavior and regressions should include focused tests. Run one case with `make artisan c="test --filter=TaskAccessTest"`.

## Commit & Pull Request Guidelines

History favors short prefixes such as `feature/...`, `fix/...`, and `refactor ...`. Keep each commit focused and describe the user-visible change, for example `fix/task_access_redirect`. Install the repository hooks once with `make hooks`. Every `git push origin <branch>` must pass PHP quality checks, PHPUnit, and the frontend production build; never bypass the hook with `--no-verify`. Pull requests should summarize behavior, note migrations or configuration changes, link the relevant issue, list verification commands, and include screenshots for UI changes. Never commit `.env`, credentials, generated caches, or local database data.
