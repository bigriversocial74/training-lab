#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "== PHP syntax =="
bash ./run-full-syntax-check.sh

echo "== Role-aware shell and participant home contracts =="
php ./tests/role-aware-shell-participant-home-contract-test.php

echo "== Role-aware shell and participant home scored audit =="
php ./scripts/role-aware-shell-participant-home-quality-audit.php

echo "== Campaign discovery detail and enrollment contracts =="
php ./tests/campaign-discovery-detail-enrollment-contract-test.php

echo "== Campaign discovery detail and enrollment scored audit =="
php ./scripts/campaign-discovery-detail-enrollment-quality-audit.php

echo "== Security runtime =="
php ./tests/security-runtime-test.php

echo "== Production runtime acceptance contracts =="
php ./tests/production-runtime-acceptance-contract-test.php

echo "== Stage 886 account integration contracts =="
php ./tests/stage886-account-integration-contract-test.php

echo "== Stage 889 shared session hardening contracts =="
php ./tests/stage889-shared-session-hardening-contract-test.php

echo "== Stage 890 reward handoff outbox contracts =="
php ./tests/stage890-reward-handoff-outbox-contract-test.php

echo "== Stage 891 reward handoff recovery contracts =="
php ./tests/stage891-reward-handoff-recovery-contract-test.php

echo "== Stage 892 scheduled worker contracts =="
php ./tests/stage892-scheduled-worker-contract-test.php

echo "== Stage 893 external delivery reconciliation contracts =="
php ./tests/stage893-external-delivery-reconciliation-contract-test.php

echo "== Stage 894 signed reward lookup client contracts =="
php ./tests/stage894-signed-reward-lookup-client-contract-test.php

echo "== Stage 895 signed integration acceptance contracts =="
php ./tests/stage895-signed-integration-acceptance-contract-test.php

echo "== Stage 896 limited reward pilot contracts =="
php ./tests/stage896-limited-reward-pilot-contract-test.php

echo "== Stage 896 scored audit =="
php ./scripts/stage896-quality-audit.php

echo "== Stage 897 controlled batch rollout contracts =="
php ./tests/stage897-controlled-batch-rollout-contract-test.php

echo "== Stage 897 scored audit =="
php ./scripts/stage897-quality-audit.php

echo "== Stage 898 worker canary monitoring contracts =="
php ./tests/stage898-worker-canary-monitoring-contract-test.php

echo "== Stage 898 scored audit =="
php ./scripts/stage898-quality-audit.php

echo "== Stage 899 canary graduation limited scheduling contracts =="
php ./tests/stage899-canary-graduation-limited-scheduled-processing-contract-test.php

echo "== Stage 899 scored audit =="
php ./scripts/stage899-quality-audit.php

echo "== Data-integrity contracts =="
php ./tests/data-integrity-contract-test.php

echo "== HTTP route contracts =="
php ./tests/http-route-contract-test.php

echo "== Section scoring =="
php ./scripts/quality-audit.php
