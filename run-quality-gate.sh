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

echo "== Task detail status and proof revisions contracts =="
php ./tests/task-detail-status-proof-revisions-contract-test.php
echo "== Task detail status and proof revisions scored audit =="
php ./scripts/task-detail-status-proof-revisions-quality-audit.php

echo "== Reward rules analytics and operational health contracts =="
php ./tests/reward-rules-analytics-operational-health-contract-test.php
echo "== Reward rules analytics and operational health scored audit =="
php ./scripts/reward-rules-analytics-operational-health-quality-audit.php

echo "== Onboarding and guided empty states contracts =="
php ./tests/onboarding-guided-empty-states-contract-test.php
echo "== Onboarding and guided empty states scored audit =="
php ./scripts/onboarding-guided-empty-states-quality-audit.php

echo "== Mobile and accessibility completion contracts =="
php ./tests/mobile-accessibility-completion-contract-test.php
echo "== Mobile and accessibility completion scored audit =="
php ./scripts/mobile-accessibility-completion-quality-audit.php

echo "== End-to-end acceptance and deployment contracts =="
php ./tests/end-to-end-acceptance-deployment-contract-test.php
echo "== End-to-end acceptance and deployment scored audit =="
php ./scripts/end-to-end-acceptance-deployment-quality-audit.php

echo "== Production deployment and live acceptance contracts =="
php ./tests/production-deployment-live-acceptance-contract-test.php
echo "== Production deployment and live acceptance scored audit =="
php ./scripts/production-deployment-live-acceptance-quality-audit.php

echo "== Pilot operations and communications contracts =="
php ./tests/pilot-operations-communications-contract-test.php
echo "== Pilot operations and communications scored audit =="
php ./scripts/pilot-operations-communications-quality-audit.php

echo "== Email provider and controlled delivery contracts =="
php ./tests/email-provider-controlled-delivery-contract-test.php
echo "== Email provider and controlled delivery scored audit =="
php ./scripts/email-provider-controlled-delivery-quality-audit.php

echo "== Resend webhooks and delivery reconciliation contracts =="
php ./tests/resend-webhooks-delivery-reconciliation-contract-test.php
echo "== Resend webhooks and delivery reconciliation scored audit =="
php ./scripts/resend-webhooks-delivery-reconciliation-quality-audit.php

echo "== Limited live email pilot and graduation contracts =="
php ./tests/limited-live-email-pilot-graduation-contract-test.php
echo "== Limited live email pilot and graduation scored audit =="
php ./scripts/limited-live-email-pilot-graduation-quality-audit.php

echo "== Merchant campaign and task builder completion contracts =="
php ./tests/merchant-campaign-task-builder-completion-contract-test.php
echo "== Merchant campaign and task builder completion scored audit =="
php ./scripts/merchant-campaign-task-builder-completion-quality-audit.php

echo "== Production integration closeout contracts =="
php ./tests/production-integration-closeout-contract-test.php
echo "== Production integration closeout scored audit =="
php ./scripts/production-integration-closeout-quality-audit.php

echo "== Release package build and verification =="
TL_PACKAGE_PATH="${TMPDIR:-/tmp}/training-lab-release-$$.zip"
trap 'rm -f "$TL_PACKAGE_PATH"' EXIT
php ./bin/build-release-package.php --release=quality-gate --output="$TL_PACKAGE_PATH"
php ./bin/verify-release-package.php --file="$TL_PACKAGE_PATH"
rm -f "$TL_PACKAGE_PATH"
trap - EXIT

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
