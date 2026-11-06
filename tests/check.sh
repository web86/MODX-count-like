#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php -l assets/components/modx-count-like/api.php
php -l modx/snippets/PostEngagement.php
node --check assets/components/modx-count-like/engagement.js
echo 'Syntax checks passed'
