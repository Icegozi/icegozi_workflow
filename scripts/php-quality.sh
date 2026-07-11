#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-check}"
shift || true

if [[ "$MODE" != "check" && "$MODE" != "fix" ]]; then
    echo "Usage: $0 [check|fix] [PHP files...]" >&2
    exit 2
fi

cd "$(git rev-parse --show-toplevel)"

if (( $# > 0 )); then
    FILES=("$@")
else
    mapfile -d '' FILES < <(git ls-files -z -- app routes config)
fi

PHP_FILES=()
for file in "${FILES[@]}"; do
    [[ "$file" == *.php && -f "$file" ]] && PHP_FILES+=("$file")
done

if (( ${#PHP_FILES[@]} == 0 )); then
    echo "[php-quality] No PHP files to check."
    exit 0
fi

RUN=()
if docker compose exec -T app true >/dev/null 2>&1; then
    RUN=(docker compose exec -T app)
elif [[ -x vendor/bin/pint && -x vendor/bin/phpcs && -x vendor/bin/phpmd ]]; then
    echo "[php-quality] App container unavailable; using host PHP dependencies."
else
    echo "[php-quality] Start the Docker stack with 'make up' before checking." >&2
    exit 1
fi

if [[ "$MODE" == "fix" ]]; then
    echo "[php-quality] Applying Pint and PHPCBF fixes..."
    "${RUN[@]}" vendor/bin/pint "${PHP_FILES[@]}"

    set +e
    "${RUN[@]}" vendor/bin/phpcbf --standard=phpcs.xml "${PHP_FILES[@]}"
    PHPCBF_STATUS=$?
    set -e
    if (( PHPCBF_STATUS > 1 )); then
        echo "[php-quality] PHPCBF could not fix every reported issue." >&2
    fi
fi

echo "[php-quality] Checking syntax, Pint, PHPMD, and PHPCS..."
"${RUN[@]}" sh -c 'for file do php -l "$file" >/dev/null || exit 1; done' sh "${PHP_FILES[@]}"
"${RUN[@]}" vendor/bin/pint --test "${PHP_FILES[@]}"

PHP_FILES_CSV=$(IFS=,; echo "${PHP_FILES[*]}")
"${RUN[@]}" vendor/bin/phpmd "$PHP_FILES_CSV" text phpmd.xml
"${RUN[@]}" vendor/bin/phpcs --standard=phpcs.xml "${PHP_FILES[@]}"

echo "[php-quality] Passed."
