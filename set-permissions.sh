#!/usr/bin/env bash
#
# NaaraSim — set correct file/folder permissions for shared cPanel (or VPS).
# Run this ONCE from the project root after uploading, via cPanel → Terminal
# (or SSH):   bash set-permissions.sh
#
# On shared hosting PHP runs as YOUR cPanel user (suPHP/LiteSpeed), so 755/644
# is correct and secure — do NOT use 777. Only the writable runtime folders
# below need group/other write; everything else stays read-only to the web.

set -e
cd "$(dirname "$0")"
echo "Setting permissions in: $(pwd)"

# 1) Baseline: directories 755, files 644 (safe default for the whole tree).
find . -type d -not -path './vendor/*' -not -path './node_modules/*' -exec chmod 755 {} \;
find . -type f -not -path './vendor/*' -not -path './node_modules/*' -exec chmod 644 {} \;

# 2) Writable runtime folders Laravel needs (cache, sessions, compiled views,
#    logs, uploads, framework cache). 775 lets PHP + your user both write.
chmod -R 775 storage bootstrap/cache
[ -d storage/app/public ] && chmod -R 775 storage/app/public

# 3) Executables.
chmod 755 artisan
[ -f set-permissions.sh ] && chmod 755 set-permissions.sh

# 4) Secrets: keep .env readable only by the owner once it exists (post-install).
[ -f .env ] && chmod 600 .env || true

echo "Done."
echo " - storage/ and bootstrap/cache/ are writable (775)"
echo " - everything else is 755 (dirs) / 644 (files)"
echo " - .env, if present, is locked to 600"
echo
echo "If PHP still cannot write (rare on some hosts), try 775 -> 770 on storage,"
echo "or ask Namecheap support which UID/GID the PHP handler runs as."
