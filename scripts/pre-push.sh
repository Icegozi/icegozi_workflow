#!/usr/bin/env bash
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

ZERO_SHA="0000000000000000000000000000000000000000"
declare -A CHANGED_PHP=()
RECEIVED_REFS=false

while read -r local_ref local_sha remote_ref remote_sha; do
    RECEIVED_REFS=true
    [[ "$local_sha" == "$ZERO_SHA" ]] && continue

    if [[ "$remote_sha" == "$ZERO_SHA" ]]; then
        base=$(git merge-base "$local_sha" origin/main 2>/dev/null \
            || git merge-base "$local_sha" origin/develop 2>/dev/null \
            || git hash-object -t tree /dev/null)
    else
        base="$remote_sha"
    fi

    while IFS= read -r file; do
        [[ -n "$file" ]] && CHANGED_PHP["$file"]=1
    done < <(git diff --name-only --diff-filter=ACMR "$base" "$local_sha" -- app routes config)
done

if [[ "$RECEIVED_REFS" == false ]]; then
    while IFS= read -r file; do
        [[ -n "$file" ]] && CHANGED_PHP["$file"]=1
    done < <(git ls-files -- app routes config)
fi

echo "[pre-push] Running PHP quality checks..."
if (( ${#CHANGED_PHP[@]} > 0 )); then
    mapfile -t PHP_FILES < <(printf '%s\n' "${!CHANGED_PHP[@]}" | sort)
    scripts/php-quality.sh check "${PHP_FILES[@]}"
else
    echo "[pre-push] No changed PHP files in app/, routes/, or config/."
fi

if docker compose exec -T app true >/dev/null 2>&1; then
    RUN=(docker compose exec -T app)
else
    RUN=()
    if ! php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'; then
        echo "[pre-push] Host PHP is missing pdo_sqlite. Start/rebuild Docker with 'make build && make up'." >&2
        exit 1
    fi
fi

echo "[pre-push] Running PHPUnit..."
"${RUN[@]}" php artisan test

echo "[pre-push] Building frontend..."
"${RUN[@]}" npm run build

echo "[pre-push] All checks passed; push is allowed."
