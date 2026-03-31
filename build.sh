#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────
# WPHZ UGC Carousel — Plugin Build Script
# Produces:  dist/wphz-ugc-carousel.zip  (WordPress-installable)
# Usage:     bash build.sh [version]
#            bash build.sh 1.0.6
# ─────────────────────────────────────────────────────────────
set -euo pipefail

PLUGIN_SLUG="wphz-ugc-carousel"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="$ROOT_DIR/dist"
BUILD_DIR="$DIST_DIR/$PLUGIN_SLUG"

# ── Resolve version ──────────────────────────────────────────
if [[ -n "${1:-}" ]]; then
    VERSION="$1"
else
    # Read from the plugin header
    VERSION=$(grep -m1 "Version:" "$ROOT_DIR/$PLUGIN_SLUG.php" | sed "s/.*Version:\s*//")
fi

ZIP_FILE="$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "▶ Building $PLUGIN_SLUG v$VERSION"

# ── Clean previous build ─────────────────────────────────────
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# ── Composer: production install ─────────────────────────────
echo "▶ Running composer install (no-dev, optimized autoloader)…"
composer install \
    --no-dev \
    --optimize-autoloader \
    --classmap-authoritative \
    --working-dir="$ROOT_DIR" \
    --quiet

# ── Copy distributable files ─────────────────────────────────
echo "▶ Copying plugin files…"

cp  "$ROOT_DIR/$PLUGIN_SLUG.php"   "$BUILD_DIR/"

cp -r "$ROOT_DIR/src"              "$BUILD_DIR/src"
cp -r "$ROOT_DIR/assets"           "$BUILD_DIR/assets"
cp -r "$ROOT_DIR/templates"        "$BUILD_DIR/templates"
cp -r "$ROOT_DIR/vendor"           "$BUILD_DIR/vendor"

# ── Remove any stray dev artefacts that may be in vendor ─────
find "$BUILD_DIR/vendor" -name ".git" -type d -exec rm -rf {} + 2>/dev/null || true

# ── Create zip ───────────────────────────────────────────────
echo "▶ Creating zip: $ZIP_FILE"
rm -f "$ZIP_FILE"

cd "$DIST_DIR"
zip -rq "$ZIP_FILE" "$PLUGIN_SLUG"

# ── Cleanup staging dir ──────────────────────────────────────
rm -rf "$BUILD_DIR"

echo "✓ Done → $ZIP_FILE"
