#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

bash ./run-full-syntax-check.sh
php ./tests/quality-gate.php
