#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php -l assets/components/modx-count-like/api.php
# Snippet source files intentionally omit PHP open tags for MODX Manager.
# Prefix a PHP tag before linting, otherwise php -l treats source as plain HTML.
for snippet in modx/snippets/PostEngagement.php modx/snippets/PostEngagementHydrate.php; do
  { printf '<?php\n'; cat "$snippet"; } | php -l
done
node --check assets/components/modx-count-like/engagement.js
node --check assets/components/modx-count-like/list.js
echo 'Syntax checks passed'
