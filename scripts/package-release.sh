#!/usr/bin/env bash
#
# NaaraSim — build a cPanel-ready installable ZIP from a clean checkout.
#
# This is the repeatable, first-party way to produce a working installable
# package directly from GitHub — so no third-party tool is ever needed again to
# diagnose and hand-patch a broken export (see docs/PLATFORM-STATE.md for the
# incident that made this necessary).
#
# What it does, in order:
#   1. Verifies install-critical files (bin/check-install-integrity.php).
#   2. Installs PHP deps for production (composer, --no-dev, optimized autoload).
#   3. Installs + builds the front-end assets (npm ci && npm run build).
#   4. Materialises the writable runtime directories the app needs at boot,
#      even if a dotfile-stripping archiver would have dropped them.
#   5. Zips a clean tree (no .git/node_modules/dev cruft) into dist/.
#
# Usage:   bash scripts/package-release.sh [output-name]
# Example: bash scripts/package-release.sh naarasim-cpanel
#
# Run it from a CLEAN checkout — it builds vendor/ and public/build/ in place,
# so run it in a throwaway clone or `git stash` local changes first.

set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"

NAME="${1:-naarasim-cpanel}"
STAMP="$(date +%Y%m%d%H%M%S)"
DIST="${ROOT}/dist"
ZIP="${DIST}/${NAME}-${STAMP}.zip"

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

say "1/5  Verifying install-critical files"
php bin/check-install-integrity.php

say "2/5  Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

say "3/5  Building front-end assets"
if [ -f package-lock.json ]; then npm ci; else npm install; fi
npm run build

say "4/5  Ensuring writable runtime directories exist"
# Belt-and-suspenders: these are preserved by tracked .gitignore files, but a
# dotfile-stripping archiver could drop them — recreate so the package is bullet
# proof regardless of how it is later re-zipped or uploaded.
for d in \
  bootstrap/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/testing \
  storage/app/public \
  storage/logs; do
  mkdir -p "$ROOT/$d"
  [ -e "$ROOT/$d/.gitignore" ] || printf '*\n!.gitignore\n' > "$ROOT/$d/.gitignore"
done

say "5/5  Building the ZIP"
mkdir -p "$DIST"
rm -f "$ZIP"
# Exclude source-control, dev tooling, local env, and the dist folder itself.
# node_modules is excluded (compiled output already lives in public/build).
zip -r -q "$ZIP" . \
  -x '.git/*' \
  -x 'node_modules/*' \
  -x 'dist/*' \
  -x 'tests/*' \
  -x '.github/*' \
  -x '.env' \
  -x 'storage/installed' \
  -x 'storage/logs/*.log' \
  -x '*/.DS_Store'

say "Done"
printf 'Package: %s\n' "$ZIP"
printf 'Size:    %s\n' "$(du -h "$ZIP" | cut -f1)"
printf '\nUpload it per docs/CPANEL-INSTALL.md. Remember: it ships vendor/ and\n'
printf 'public/build/, so no Composer/npm is needed on the server.\n'
