<?php
/**
 * Merchant reward rules, business analytics, and fulfillment health.
 *
 * This layer edits only Training Lab reward rules. It does not create, issue,
 * claim, redeem, cancel, refund, or mutate Microgifter rewards or wallets.
 */
require_once __DIR__ . '/training-lab-campaign-experience.php';
require_once __DIR__ . '/training-lab-product-shell.php';
require_once __DIR__ . '/training-lab-stage890-reward-handoff-outbox.php';

if (!function_exists('tl_reward_management_scope')) {
    function tl_reward_management_scope(array $user): array
    {
        $role = tl_product_role($user);
        if (!tl_product_role_allows($role, 'manager')) {
            throw new TlHttpException('Merchant reward management access is required.', 403, 'reward_management_forbidden');
        }
        return [
            'role'=>$role,
            'owner_user_id'=>tl_campaign_user_id($user),
            'platform'=>$role === 'admin',
        ];
    }
}

if (!function_exists('tl_reward_management_campaign')) {
    function tl_reward_management_campaign(PDO $pdo, array $user, string $campaignRef, bool $lock = false): array
    {
        $scope = tl_reward_management_scope($user);
        $campaignRef = tl_campaign_clean_ref($campaignRef);
        if ($campaignRef === '') throw new TlHttpException('Campaign is required.', 422, 'campaign_required');
        $whereOwner = $scope['platform'] ? '' : ' AND owner_user_id=?';
        $sql = 'SELECT * FROM training_campaigns WHERE (id=? OR public_id=? OR slug=?)' . $whereOwner . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $params = [ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef];
        if (!$scope['platform']) $params[] = (int)$scope['owner_user_id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new TlHttpException('Campaign was not found in your merchant account.', 404, 'campaign_not_found');
        return $row;
    }
}

if (!function_exists('tl_reward_management_campaigns')) {
    function tl_reward_management_campaigns(array $user): array
    {
        $pdo = tl_require_db();
        $scope = tl_reward_management_scope($user);
        $where = $scope['platform'] ? '1=1' : 'c.owner_user_id=?';
        $params = $scope['platform'] ? [] : [(int)$scope['owner_user_id']];
        $sql = "SELECT c.id,c.public_id,c.slug,c.title,c.status,c.visibility,c.updated_at,
                    (SELECT COUNT(*) FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status<>'archived') AS rule_count,
                    (SELECT COUNT(*) FROM training_reward_rules rr WHERE rr.campaign_id=c.id AND rr.status='active') AS active_rule_count,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') AS reward_event_count
                FROM training_campaigns c
                WHERE {$where}
                ORDER BY c.updated_at DESC,c.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('tl_reward_management_rules')) {
    function tl_reward_management_rules(array $user, string $campaignRef = ''): array
    {
        $pdo = tl_require_db();
        $campaigns = tl_reward_management_campaigns($user);
        if ($campaignRef === '' && $campaigns) $campaignRef = (string)($campaigns[0]['slug'] ?: $campaigns[0]['public_id']);
        if ($campaignRef === '') return ['campaigns'=>[],'campaign'=>null,'rules'=>[]];
        $campaign = tl_reward_management_campaign($pdo, $user, $campaignRef);
        $stmt = $pdo->prepare("SELECT rr.*,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.reward_rule_id=rr.id AND re.status<>'cancelled') AS reward_event_count,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.reward_rule_id=rr.id AND re.status IN ('issued','linked_to_microgifter')) AS delivered_count
                FROM training_reward_rules rr
                WHERE rr.campaign_id=?
                ORDER BY FIELD(rr.status,'active','draft','paused','archived'),rr.threshold_count ASC,rr.id ASC");
        $stmt->execute([(int)$campaign['id']]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rules as &$rule) {
            $rule['display_value'] = strtoupper((string)$rule['currency']) . ' ' . number_format(((int)$rule['reward_value_cents']) / 100, 2);
            $rule['trigger_label'] = match ((string)$rule['trigger_type']) {
                'action_count'=>'Verified task count',
                'sequence_completed'=>'Campaign completion',
                'streak_days'=>'Learning streak',
                'manual'=>'Manual eligibility',
                default=>'Training milestone',
            };
        }
        unset($rule);
        return ['campaigns'=>$campaigns,'campaign'=>$campaign,'rules'=>$rules];
    }
}

if (!function_exists('tl_reward_management_rule')) {
    function tl_reward_management_rule(array $user, string $campaignRef, string $ruleRef = ''): array
    {
        $pdo = tl_require_db();
        $campaign = tl_reward_management_campaign($pdo, $user, $campaignRef);
        if ($ruleRef === '') return ['campaign'=>$campaign,'rule'=>null];
        $ruleRef = tl_action_clean($ruleRef, 180, true, 'Reward rule');
        $stmt = $pdo->prepare('SELECT * FROM training_reward_rules WHERE campaign_id=? AND (id=? OR public_id=?) LIMIT 1');
        $stmt->execute([(int)$campaign['id'], ctype_digit($ruleRef) ? (int)$ruleRef : 0, $ruleRef]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rule) throw new TlHttpException('Reward rule was not found.', 404, 'reward_rule_not_found');
        return ['campaign'=>$campaign,'rule'=>$rule];
    }
}

if (!function_exists('tl_reward_management_save_rule')) {
    function tl_reward_management_save_rule(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign_id'] ?? $input['campaign'] ?? '');
        $ruleRef = tl_action_clean($input['rule_id'] ?? $input['rule'] ?? '', 180);
        $name = tl_action_clean($input['rule_name'] ?? '', 180, true, 'Rule name');
        $label = tl_action_clean($input['reward_label'] ?? '', 255, true, 'Reward label');
        $trigger = tl_action_enum($input['trigger_type'] ?? '', ['action_count','sequence_completed','streak_days','manual'], '');
        if ($trigger === '') throw new TlHttpException('Select a valid reward trigger.', 422, 'reward_trigger_invalid');
        $threshold = max(1, min(100000, (int)($input['threshold_count'] ?? 1)));
        if (in_array($trigger, ['sequence_completed','manual'], true)) $threshold = 1;
        $type = tl_action_enum($input['reward_type'] ?? '', ['badge','microgift','entitlement','wallet_credit_preview','custom'], '');
        if ($type === '') throw new TlHttpException('Select a valid reward type.', 422, 'reward_type_invalid');
        $valueCents = max(0, min(100000000, (int)($input['reward_value_cents'] ?? 0)));
        $currency = strtoupper(tl_action_clean($input['currency'] ?? 'USD', 3));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) throw new TlHttpException('Currency must be a three-letter code.', 422, 'currency_invalid');
        $description = tl_action_clean($input['description'] ?? '', 1000);

        $pdo->beginTransaction();
        try {
            $campaign = tl_reward_management_campaign($pdo, $user, $campaignRef, true);
            $rule = null;
            if ($ruleRef !== '') {
                $stmt = $pdo->prepare('SELECT * FROM training_reward_rules WHERE campaign_id=? AND (id=? OR public_id=?) LIMIT 1 FOR UPDATE');
                $stmt->execute([(int)$campaign['id'], ctype_digit($ruleRef) ? (int)$ruleRef : 0, $ruleRef]);
                $rule = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$rule) throw new TlHttpException('Reward rule was not found.', 404, 'reward_rule_not_found');
                if ((string)$rule['status'] === 'archived') throw new TlHttpException('Archived reward rules cannot be edited.', 409, 'reward_rule_archived');
            }
            $settings = [
                'description'=>$description,
                'source'=>'merchant_reward_rule_editor',
                'wallet_write'=>false,
                'microgifter_authority'=>'existing_reward_bridge_only',
            ];
            if ($rule) {
                $stmt = $pdo->prepare('UPDATE training_reward_rules SET rule_name=?,trigger_type=?,threshold_count=?,reward_type=?,reward_label=?,reward_value_cents=?,currency=?,settings_json=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $stmt->execute([$name,$trigger,$threshold,$type,$label,$valueCents,$currency,json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),(int)$rule['id']]);
                $ruleId = (int)$rule['id'];
                $publicId = (string)$rule['public_id'];
                $event = 'reward_rule_updated';
            } else {
                $publicId = tl_uuid();
                $stmt = $pdo->prepare("INSERT INTO training_reward_rules (public_id,campaign_id,rule_name,trigger_type,threshold_count,reward_type,reward_label,reward_value_cents,currency,status,settings_json) VALUES (?,?,?,?,?,?,?,?,?,'draft',?)");
                $stmt->execute([$publicId,(int)$campaign['id'],$name,$trigger,$threshold,$type,$label,$valueCents,$currency,json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
                $ruleId = (int)$pdo->lastInsertId();
                $event = 'reward_rule_created';
            }
            tl_log_event($pdo, tl_campaign_user_id($user), 'reward_rule', $ruleId, $event, ['campaign_id'=>(int)$campaign['id'],'public_id'=>$publicId]);
            $pdo->commit();
            return ['id'=>$ruleId,'public_id'=>$publicId,'campaign_ref'=>(string)($campaign['slug'] ?: $campaign['public_id']),'created'=>$rule === null];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_reward_management_transition')) {
    function tl_reward_management_transition(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign_id'] ?? $input['campaign'] ?? '');
        $ruleRef = tl_action_clean($input['rule_id'] ?? $input['rule'] ?? '', 180, true, 'Reward rule');
        $action = tl_action_enum($input['rule_action'] ?? $input['transition'] ?? '', ['activate','pause','resume','archive'], '');
        if ($action === '') throw new TlHttpException('Select a valid reward rule action.', 422, 'reward_rule_action_invalid');
        $allowed = [
            'draft'=>['activate'=>'active','archive'=>'archived'],
            'active'=>['pause'=>'paused','archive'=>'archived'],
            'paused'=>['resume'=>'active','archive'=>'archived'],
            'archived'=>[],
        ];
        $pdo->beginTransaction();
        try {
            $campaign = tl_reward_management_campaign($pdo, $user, $campaignRef, true);
            $stmt = $pdo->prepare('SELECT * FROM training_reward_rules WHERE campaign_id=? AND (id=? OR public_id=?) LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$campaign['id'], ctype_digit($ruleRef) ? (int)$ruleRef : 0, $ruleRef]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule) throw new TlHttpException('Reward rule was not found.', 404, 'reward_rule_not_found');
            $current = (string)$rule['status'];
            $next = $allowed[$current][$action] ?? null;
            if (!$next) throw new TlHttpException('This reward rule transition is not allowed.', 409, 'reward_rule_transition_invalid');
            if ($action === 'activate' && (string)$campaign['status'] === 'archived') {
                throw new TlHttpException('A reward rule cannot be activated for an archived campaign.', 409, 'campaign_archived');
            }
            $upd = $pdo->prepare('UPDATE training_reward_rules SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $upd->execute([$next,(int)$rule['id']]);
            tl_log_event($pdo, tl_campaign_user_id($user), 'reward_rule', (int)$rule['id'], 'reward_rule_' . $action, ['campaign_id'=>(int)$campaign['id'],'from'=>$current,'to'=>$next]);
            $pdo->commit();
            return ['id'=>(int)$rule['id'],'public_id'=>(string)$rule['public_id'],'status'=>$next,'action'=>$action];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_reward_management_analytics')) {
    function tl_reward_management_analytics(array $user): array
    {
        $pdo = tl_require_db();
        $scope = tl_reward_management_scope($user);
        $where = $scope['platform'] ? '1=1' : 'c.owner_user_id=?';
        $params = $scope['platform'] ? [] : [(int)$scope['owner_user_id']];
        $sql = "SELECT c.id,c.public_id,c.slug,c.title,c.status,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status<>'removed') AS participants,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status='completed') AS completed_participants,
                    (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.campaign_id=c.id) AS proofs,
                    (SELECT COUNT(*) FROM training_reviews r INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id) AS reviews,
                    (SELECT COUNT(*) FROM training_reviews r INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id AND r.decision='approved') AS approvals,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') AS rewards,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status IN ('issued','linked_to_microgifter')) AS delivered_rewards,
                    (SELECT COALESCE(SUM(re.value_cents),0) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') AS reward_value_cents,
                    (SELECT AVG(TIMESTAMPDIFF(MINUTE,p.submitted_at,COALESCE(r.reviewed_at,r.created_at))) FROM training_reviews r INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id) AS avg_review_minutes
                FROM training_campaigns c WHERE {$where} ORDER BY c.updated_at DESC,c.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totals = ['campaigns'=>count($campaigns),'participants'=>0,'completed'=>0,'proofs'=>0,'reviews'=>0,'approvals'=>0,'rewards'=>0,'delivered'=>0,'reward_value_cents'=>0];
        foreach ($campaigns as &$row) {
            foreach (['participants','completed_participants','proofs','reviews','approvals','rewards','delivered_rewards','reward_value_cents'] as $key) $row[$key] = (int)$row[$key];
            $row['completion_rate'] = $row['participants'] > 0 ? (int)round(($row['completed_participants'] / $row['participants']) * 100) : 0;
            $row['approval_rate'] = $row['reviews'] > 0 ? (int)round(($row['approvals'] / $row['reviews']) * 100) : 0;
            $row['delivery_rate'] = $row['rewards'] > 0 ? (int)round(($row['delivered_rewards'] / $row['rewards']) * 100) : 0;
            $row['avg_review_minutes'] = $row['avg_review_minutes'] === null ? null : (int)round((float)$row['avg_review_minutes']);
            $totals['participants'] += $row['participants'];
            $totals['completed'] += $row['completed_participants'];
            $totals['proofs'] += $row['proofs'];
            $totals['reviews'] += $row['reviews'];
            $totals['approvals'] += $row['approvals'];
            $totals['rewards'] += $row['rewards'];
            $totals['delivered'] += $row['delivered_rewards'];
            $totals['reward_value_cents'] += $row['reward_value_cents'];
        }
        unset($row);
        $totals['completion_rate'] = $totals['participants'] > 0 ? (int)round(($totals['completed'] / $totals['participants']) * 100) : 0;
        $totals['approval_rate'] = $totals['reviews'] > 0 ? (int)round(($totals['approvals'] / $totals['reviews']) * 100) : 0;
        $totals['delivery_rate'] = $totals['rewards'] > 0 ? (int)round(($totals['delivered'] / $totals['rewards']) * 100) : 0;
        return ['scope'=>$scope['platform'] ? 'platform' : 'merchant','campaigns'=>$campaigns,'totals'=>$totals];
    }
}

if (!function_exists('tl_reward_management_fulfillment')) {
    function tl_reward_management_fulfillment(array $user, int $limit = 100): array
    {
        $pdo = tl_require_db();
        $scope = tl_reward_management_scope($user);
        $where = $scope['platform'] ? '1=1' : 'c.owner_user_id=?';
        $params = $scope['platform'] ? [] : [(int)$scope['owner_user_id']];
        $handoffSelect = tl_table_exists('training_reward_handoffs')
            ? ",(SELECT h.status FROM training_reward_handoffs h WHERE h.reward_event_id=re.id ORDER BY h.id DESC LIMIT 1) AS handoff_status,
               (SELECT h.attempt_count FROM training_reward_handoffs h WHERE h.reward_event_id=re.id ORDER BY h.id DESC LIMIT 1) AS attempt_count,
               (SELECT h.last_error FROM training_reward_handoffs h WHERE h.reward_event_id=re.id ORDER BY h.id DESC LIMIT 1) AS last_error"
            : ",'not_configured' AS handoff_status,0 AS attempt_count,NULL AS last_error";
        $sql = "SELECT re.id,re.public_id,re.status,re.value_cents,re.currency,re.created_at,re.updated_at,
                    rr.reward_label,c.title AS campaign_title,tp.participant_label{$handoffSelect}
                FROM training_reward_events re
                INNER JOIN training_campaigns c ON c.id=re.campaign_id
                LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id
                LEFT JOIN training_participants tp ON tp.id=re.participant_id
                WHERE {$where} AND re.status<>'cancelled'
                ORDER BY CASE re.status WHEN 'failed' THEN 0 WHEN 'claimed' THEN 1 WHEN 'eligible' THEN 2 ELSE 3 END,re.updated_at DESC,re.id DESC
                LIMIT " . max(1, min(250, $limit));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $counts = ['total'=>count($rows),'ready'=>0,'processing'=>0,'delivered'=>0,'failed'=>0,'blocked'=>0];
        foreach ($rows as &$row) {
            $handoff = strtolower((string)($row['handoff_status'] ?? ''));
            $bucket = match (true) {
                in_array($handoff, ['delivered'], true), in_array((string)$row['status'], ['issued','linked_to_microgifter'], true) => 'delivered',
                in_array($handoff, ['processing'], true) => 'processing',
                in_array($handoff, ['failed'], true), (string)$row['status'] === 'failed' => 'failed',
                in_array($handoff, ['blocked','quarantined','cancelled'], true) => 'blocked',
                default => 'ready',
            };
            $counts[$bucket]++;
            $row['health_bucket'] = $bucket;
            $row['display_value'] = strtoupper((string)$row['currency']) . ' ' . number_format(((int)$row['value_cents']) / 100, 2);
            $row['confirmation'] = substr(hash('sha256', (string)$row['public_id']), 0, 12);
            $row['last_error'] = $row['last_error'] ? mb_substr(strip_tags((string)$row['last_error']), 0, 220) : null;
            unset($row['id']);
        }
        unset($row);
        return ['scope'=>$scope['platform'] ? 'platform' : 'merchant','counts'=>$counts,'rows'=>$rows,'advanced_available'=>$scope['role']==='admin'];
    }
}
