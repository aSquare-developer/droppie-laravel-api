#!/usr/bin/env bash
#
# Activates a release on the server without touching the live code until the last moment.
#
#   bash deploy/release.sh <app-path> <release-archive> <release-name> [keep-releases]
#
# <app-path> (DEPLOY_PATH) is what the web server and cron use. It becomes a symlink to the
# current release, so the document root (<app-path>/public) and the cron command stay the same.
# Releases and shared files live next to it:
#
#   <app-path>-releases/releases/<release-name>/   one directory per release
#   <app-path>-releases/shared/.env                kept across releases
#   <app-path>-releases/shared/storage/
#   <app-path>-releases/shared/well-known/         public/.well-known (certificates)
#
# The first run converts an existing plain <app-path> directory into this layout. Anything that
# fails before the switch leaves the current release serving traffic.
set -euo pipefail

APP_PATH="${1:?app path required}"
ARCHIVE="${2:?release archive required}"
RELEASE_NAME="${3:?release name required}"
KEEP="${4:-5}"

APP_PATH="${APP_PATH%/}"
# The archive path may be relative to the starting directory; the script changes directory later.
[[ -f "$ARCHIVE" ]] && ARCHIVE="$(cd "$(dirname "$ARCHIVE")" && pwd)/$(basename "$ARCHIVE")"
BASE="${APP_PATH}-releases"
RELEASES="$BASE/releases"
SHARED="$BASE/shared"
NEW="$RELEASES/$RELEASE_NAME"

log() { printf '[release] %s\n' "$*"; }
fail() { printf '[release] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$APP_PATH" == /* && "$APP_PATH" != "/" ]] || fail "App path must be an absolute directory and cannot be /."
[[ -f "$ARCHIVE" ]] || fail "Release archive does not exist: $ARCHIVE"
[[ "$RELEASE_NAME" =~ ^[A-Za-z0-9._-]+$ ]] || fail "Invalid release name: $RELEASE_NAME"
command -v php >/dev/null || fail "php is not available."

LEGACY=0
if [[ -L "$APP_PATH" ]]; then
    [[ -f "$SHARED/.env" ]] || fail "Shared .env is missing: $SHARED/.env"
elif [[ -d "$APP_PATH" ]]; then
    # First run: <app-path> is still a plain directory with the live app.
    LEGACY=1
    [[ -f "$APP_PATH/.env" ]] || fail "Production .env is missing: $APP_PATH/.env"
else
    fail "App path does not exist: $APP_PATH"
fi

mkdir -p "$RELEASES" "$SHARED"

if [[ "$LEGACY" == 1 ]]; then
    log "Converting $APP_PATH into the release layout."
    # Copies, not moves: the live app keeps its own files until the switch.
    [[ -f "$SHARED/.env" ]] || cp -p "$APP_PATH/.env" "$SHARED/.env"
    if [[ ! -d "$SHARED/storage" && -d "$APP_PATH/storage" ]]; then
        cp -a "$APP_PATH/storage" "$SHARED/storage"
    fi
    if [[ ! -d "$SHARED/well-known" && -d "$APP_PATH/public/.well-known" ]]; then
        cp -a "$APP_PATH/public/.well-known" "$SHARED/well-known"
    fi
fi

mkdir -p \
    "$SHARED/storage/app/public" \
    "$SHARED/storage/framework/cache/data" \
    "$SHARED/storage/framework/sessions" \
    "$SHARED/storage/framework/views" \
    "$SHARED/storage/logs"

log "Unpacking $RELEASE_NAME."
rm -rf "$NEW"
mkdir -p "$NEW"
tar -xzf "$ARCHIVE" -C "$NEW"

MAINTENANCE=0
cleanup_failed_release() {
    status=$?
    if [[ "$MAINTENANCE" == 1 ]]; then
        (cd "$NEW" && php artisan up) || true
    fi
    rm -rf "$NEW"
    log "Release $RELEASE_NAME was not activated; the current release keeps serving."
    exit "$status"
}
trap cleanup_failed_release ERR

rm -rf "$NEW/storage" "$NEW/.env" "$NEW/bootstrap/cache"
ln -s "$SHARED/.env" "$NEW/.env"
ln -s "$SHARED/storage" "$NEW/storage"
mkdir -p "$NEW/bootstrap/cache"
if [[ -d "$SHARED/well-known" ]]; then
    rm -rf "$NEW/public/.well-known"
    ln -s "$SHARED/well-known" "$NEW/public/.well-known"
fi

cd "$NEW"
php artisan storage:link --force >/dev/null

# Maintenance mode lives in the shared storage, so it covers the release still serving traffic.
# It is only needed while migrations change the database under the old code.
pending="$(php artisan migrate:status --pending --no-ansi)"
if [[ "$pending" == *Pending* ]]; then
    log "Pending migrations: entering maintenance mode."
    php artisan down --retry=60
    MAINTENANCE=1
fi

php artisan migrate --force
php artisan optimize

log "Switching to $RELEASE_NAME."
trap - ERR
if [[ "$LEGACY" == 1 ]]; then
    # A directory cannot be swapped for a symlink atomically; the gap is a single rename.
    # Named to sort before every timestamped release, so rollback finds it as the previous one.
    mv "$APP_PATH" "$RELEASES/00000000000000-legacy"
    ln -s "$NEW" "$APP_PATH"
else
    ln -sfn "$NEW" "$APP_PATH.next"
    mv -T "$APP_PATH.next" "$APP_PATH"
fi

php artisan queue:restart
if [[ "$MAINTENANCE" == 1 ]]; then
    php artisan up
fi

# Keep the newest releases; never delete the one now live.
current="$(readlink "$APP_PATH")"
ls -1 "$RELEASES" | sort | head -n "-$KEEP" | while read -r old; do
    [[ "$RELEASES/$old" == "$current" ]] || rm -rf "${RELEASES:?}/$old"
done

rm -f "$ARCHIVE"
log "Release $RELEASE_NAME is live."
