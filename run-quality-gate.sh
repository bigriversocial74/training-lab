#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "== PHP syntax =="
bash ./run-full-syntax-check.sh

echo "== Security runtime =="
php ./tests/security-runtime-test.php

echo "== Production runtime acceptance contracts =="
php ./tests/production-runtime-acceptance-contract-test.php

echo "== Stage 886 account integration contracts =="
php ./tests/stage886-account-integration-contract-test.php

echo "== Data-integrity contracts =="
php ./tests/data-integrity-contract-test.php

echo "== HTTP route contracts =="
php ./tests/http-route-contract-test.php

echo "== Section scoring =="
php ./scripts/quality-audit.php
