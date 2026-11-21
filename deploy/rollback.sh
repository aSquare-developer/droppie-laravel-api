#!/usr/bin/env bash
#
# Switches <app-path> back to an earlier release.
#
#   bash deploy/rollback.sh <app-path> [release-name]
#
# Without a release name it goes to the release before the current one. Database migrations
# are not rolled back; releases are built to work with the schema of the newer release.
set -euo pipefail

APP_PATH="${1:?app path required}"
TARGET_NAME="${2:-}"

APP_PATH="${APP_PATH%/}"
RELEASES="${APP_PATH}-releases/releases"

log() { printf '[rollback] %s\n' "$*"; }
fail() { printf '[rollback] ERROR: %s\n' "$*" >&2; exit 1; }

[[ -L "$APP_PATH" ]] || fail "$APP_PATH is not a release symlink; nothing to roll back."
current="$(basename "$(readlink "$APP_PATH")")"

if [[ -z "$TARGET_NAME" ]]; then
    TARGET_NAME="$(ls -1 "$RELEASES" | sort | awk -v cur="$current" '$0 == cur { print prev; exit } { prev = $0 }')"
    [[ -n "$TARGET_NAME" ]] || fail "No release older than $current."
fi

TARGET="$RELEASES/$TARGET_NAME"
[[ "$TARGET_NAME" =~ ^[A-Za-z0-9._-]+$ && -d "$TARGET" ]] || fail "Release not found: $TARGET_NAME"
[[ "$TARGET_NAME" != "$current" ]] || fail "$TARGET_NAME is already live."

log "Switching from $current to $TARGET_NAME."
ln -sfn "$TARGET" "$APP_PATH.next"
mv -T "$APP_PATH.next" "$APP_PATH"

cd "$TARGET"
php artisan queue:restart
# A failed deploy may have left maintenance mode on in the shared storage.
php artisan up || true

log "Release $TARGET_NAME is live."
