<?php
/**
 * Training Lab account bridge and role permissions.
 *
 * This bridge lets the standalone Training Lab recognize an existing Microgifter
 * session, create a Training Lab session, and prepare a Microgifter account
 * creation payload. It deliberately does not guess production auth table names.
 * If the host app exposes a supported adapter function, the bridge can call it;
 * otherwise the account is marked adapter_pending and the user can keep working
 * in a Training Lab session.
 */
require_once __DIR__ . '/training-lab-stage34-service.php';
require_once __DIR__ . '/training-lab-auth-gate.php';

if (!function_exists('tl_account_bridge_roles')) {
    function tl_account_bridge_roles(): array
    {
        return [
            'participant' => [
                'label' => 'Participant',
                'description' => 'Can join campaigns, complete tasks, submit proof, view progress, and claim assigned rewards.',
                'permissions' => ['training.campaign.view','training.participate','training.receipt.view','training.reward.claim','training.note.create'],
            ],
            'coach' => [
                'label' => 'Coach',
                'description' => 'Can support participants, view campaigns, review proof, post notes, and assist reward claims.',
                'permissions' => ['training.campaign.view','training.participate','training.proof.review','training.receipt.view','training.reward.claim','training.note.create'],
            ],
            'reviewer' => [
                'label' => 'Reviewer',
                'description' => 'Can inspect and decide submitted proof, receipts, and review workbench queues.',
                'permissions' => ['training.campaign.view','training.proof.review','training.receipt.view','training.reward.claim','training.note.create'],
            ],
            'manager' => [
                'label' => 'Manager',
                'description' => 'Can manage campaigns, participants, proof review, reporting, reward offers, and claim tracking.',
                'permissions' => ['training.campaign.view','training.campaign.manage','training.participate','training.proof.review','training.receipt.view','training.reward.claim','training.reward.manage','training.note.create','training.ops.qa'],
            ],
            'admin' => [
                'label' => 'Admin',
                'description' => 'Full Training Lab operations access. Still bounded to Training Lab tables/actions.',
                'permissions' => ['training.campaign.view','training.campaign.manage','training.participate','training.proof.review','training.receipt.view','training.reward.claim','training.reward.manage','training.note.create','training.ops.qa'],
            ],
        ];
    }
}

if (!function_exists('tl_account_bridge_normalize_role')) {
    function tl_account_bridge_normalize_role(string $role): string
    {
        $role = strtolower(trim($role));
        $aliases = [
            'owner' => 'admin',
            'merchant_admin' => 'admin',
            'merchant' => 'manager',
            'operator' => 'manager',
            'trainer' => 'coach',
            'mentor' => 'coach',
            'user' => 'participant',
        ];
        $role = $aliases[$role] ?? $role;
        return array_key_exists($role, tl_account_bridge_roles()) ? $role : 'participant';
    }
}

if (!function_exists('tl_account_bridge_role_permissions')) {
    function tl_account_bridge_role_permissions(string $role): array
    {
        $role = tl_account_bridge_normalize_role($role);
        return tl_account_bridge_roles()[$role]['permissions'] ?? [];
    }
}

if (!function_exists('tl_account_bridge_numeric_user_id')) {
    function tl_account_bridge_numeric_user_id(?array $user = null): int
    {
        $user = $user ?: tl_auth_current_user();
        if (!$user) return 1;
        $sourceId = (string)($user['microgifter_user_id'] ?? $user['id'] ?? $user['email'] ?? 'training-user');
        if (ctype_digit($sourceId) && (int)$sourceId > 0) return (int)$sourceId;
        $hash = sprintf('%u', crc32($sourceId));
        return max(1, (int)substr($hash, 0, 9));
    }
}

if (!function_exists('tl_account_bridge_detect_microgifter_session')) {
    function tl_account_bridge_detect_microgifter_session(): ?array
    {
        $existing = tl_auth_existing_microgifter_user();
        if (!$existing) return null;
        $role = tl_account_bridge_normalize_role((string)($existing['role'] ?? 'participant'));
        return [
            'id' => (string)$existing['id'],
            'microgifter_user_id' => (string)$existing['id'],
            'name' => (string)($existing['name'] ?? 'Microgifter User'),
            'email' => (string)($existing['email'] ?? ''),
            'role' => $role,
            'permissions' => tl_account_bridge_role_permissions($role),
            'source' => 'existing_microgifter_session',
            'sync_status' => 'synced',
            'microgifter_account_status' => 'existing_session_detected',
            'numeric_user_id' => tl_account_bridge_numeric_user_id($existing),
            'logged_in_at' => gmdate('c'),
        ];
    }
}

if (!function_exists('tl_account_bridge_set_user')) {
    function tl_account_bridge_set_user(array $user): array
    {
        tl_auth_session_start();
        $role = tl_account_bridge_normalize_role((string)($user['role'] ?? 'participant'));
        $user['role'] = $role;
        $user['permissions'] = tl_account_bridge_role_permissions($role);
        $user['numeric_user_id'] = tl_account_bridge_numeric_user_id($user);
        $user['logged_in_at'] = $user['logged_in_at'] ?? gmdate('c');
        $_SESSION['training_lab_user'] = $user;
        return $user;
    }
}

if (!function_exists('tl_account_bridge_log')) {
    function tl_account_bridge_log(string $eventType, array $metadata, ?int $actor = null): void
    {
        try {
            $pdo = tl_db();
            if ($pdo && tl_table_exists('training_events')) {
                tl_log_event($pdo, $actor ?: tl_account_bridge_numeric_user_id(), 'system', null, $eventType, $metadata + ['training_account_bridge' => true]);
            }
        } catch (Throwable $e) {
            // Auth bridge logging must never block login/session creation.
        }
    }
}

if (!function_exists('tl_account_bridge_sync_microgifter')) {
    function tl_account_bridge_sync_microgifter(): array
    {
        $existing = tl_account_bridge_detect_microgifter_session();
        if (!$existing) {
            throw new RuntimeException('No existing Microgifter session was detected. Sign into Microgifter first, or create a Training Lab account that can be linked when the account adapter is connected.');
        }
        $user = tl_account_bridge_set_user($existing);
        tl_account_bridge_log('training_account_synced_microgifter', ['sync_status' => 'synced', 'source' => $user['source']], (int)$user['numeric_user_id']);
        return ['user' => $user, 'sync_status' => 'synced', 'microgifter_account_status' => 'existing_session_detected'];
    }
}

if (!function_exists('tl_account_bridge_create_training_user')) {
    function tl_account_bridge_create_training_user(array $input, bool $requestMicrogifterAccount = false): array
    {
        $email = strtolower(trim((string)($input['email'] ?? $input['work_email'] ?? '')));
        $name = trim((string)($input['name'] ?? $input['display_name'] ?? $input['full_name'] ?? ''));
        $role = tl_account_bridge_normalize_role((string)($input['role'] ?? 'participant'));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email is required.');
        }
        if ($name === '') $name = $email;

        $adapter = 'not_configured';
        $microgifterUserId = null;
        $adapterMessage = 'Production Microgifter account creation is adapter-pending. No unknown production account tables were written.';

        if ($requestMicrogifterAccount) {
            $payload = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'source' => 'training_lab_account_bridge',
                'password' => (string)($input['password'] ?? ''),
            ];
            if (function_exists('microgifter_create_training_user_account')) {
                $adapter = 'microgifter_create_training_user_account';
                $created = microgifter_create_training_user_account($payload);
                if (is_array($created)) {
                    $microgifterUserId = (string)($created['id'] ?? $created['user_id'] ?? '');
                    $adapterMessage = 'Microgifter adapter returned an account record.';
                }
            } elseif (function_exists('microgifter_create_user_account')) {
                $adapter = 'microgifter_create_user_account';
                $created = microgifter_create_user_account($payload);
                if (is_array($created)) {
                    $microgifterUserId = (string)($created['id'] ?? $created['user_id'] ?? '');
                    $adapterMessage = 'Microgifter adapter returned an account record.';
                }
            } else {
                $adapter = 'adapter_pending';
            }
        }

        $seedId = $microgifterUserId ?: ('tl-' . substr(hash('sha256', $email . '|' . $role), 0, 14));
        $status = $requestMicrogifterAccount ? ($microgifterUserId ? 'created_or_linked_by_adapter' : 'adapter_pending') : 'training_session_only';
        $user = tl_account_bridge_set_user([
            'id' => $seedId,
            'microgifter_user_id' => $microgifterUserId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'source' => $requestMicrogifterAccount ? 'training_lab_create_with_microgifter_option' : 'training_lab_login',
            'sync_status' => $microgifterUserId ? 'linked' : ($requestMicrogifterAccount ? 'pending_adapter' : 'local_training_session'),
            'microgifter_account_status' => $status,
            'account_adapter' => $adapter,
        ]);
        tl_account_bridge_log($requestMicrogifterAccount ? 'training_account_create_microgifter_requested' : 'training_account_login', [
            'email_hash' => hash('sha256', $email),
            'role' => $role,
            'microgifter_account_status' => $status,
            'adapter' => $adapter,
            'message' => $adapterMessage,
        ], (int)$user['numeric_user_id']);
        return ['user' => $user, 'microgifter_account_status' => $status, 'adapter' => $adapter, 'message' => $adapterMessage];
    }
}

if (!function_exists('tl_account_bridge_logout')) {
    function tl_account_bridge_logout(): void
    {
        tl_auth_logout_session();
    }
}

if (!function_exists('tl_account_bridge_current_context')) {
    function tl_account_bridge_current_context(): array
    {
        $existing = tl_account_bridge_detect_microgifter_session();
        $current = tl_auth_current_user();
        if ($current) {
            $current = tl_account_bridge_set_user($current);
        }
        return [
            'authenticated' => $current !== null,
            'user' => $current,
            'detected_microgifter_session' => $existing,
            'roles' => tl_account_bridge_roles(),
            'permissions' => $current ? tl_account_bridge_role_permissions((string)$current['role']) : [],
            'enforcement_enabled' => defined('TL_AUTH_ENFORCE') ? (bool)TL_AUTH_ENFORCE : false,
            'safe_boundaries' => [
                'no_unknown_microgifter_auth_table_writes' => true,
                'microgifter_account_creation_requires_adapter' => true,
                'training_session_available_without_auth_gate' => true,
                'role_permissions_available_for_gating' => true,
                'writes_only_training_events_for_bridge_audit' => true,
            ],
        ];
    }
}

if (!function_exists('tl_account_bridge_can')) {
    function tl_account_bridge_can(string $permission, ?array $user = null): bool
    {
        $user = $user ?: tl_auth_current_user();
        if (!$user) return !(defined('TL_AUTH_ENFORCE') && TL_AUTH_ENFORCE);
        $role = tl_account_bridge_normalize_role((string)($user['role'] ?? 'participant'));
        return in_array($permission, tl_account_bridge_role_permissions($role), true);
    }
}

if (!function_exists('tl_account_bridge_action_permission')) {
    function tl_account_bridge_action_permission(string $action): string
    {
        $map = [
            'create_campaign' => 'training.campaign.manage',
            'create_campaign_blueprint' => 'training.campaign.manage',
            'seed_demo' => 'training.campaign.manage',
            'join_campaign' => 'training.participate',
            'complete_task' => 'training.participate',
            'submit_proof' => 'training.participate',
            'review_proof' => 'training.proof.review',
            'queue_reward_event' => 'training.reward.manage',
            'offer_microgifter_reward' => 'training.reward.manage',
            'claim_training_reward' => 'training.reward.claim',
            'retry_microgifter_reward_issue' => 'training.reward.manage',
            'mark_reward_manual_issued' => 'training.reward.manage',
            'cancel_training_reward' => 'training.reward.manage',
            'reconcile_reward_lifecycle' => 'training.reward.manage',
            'update_campaign_status' => 'training.campaign.manage',
            'reconcile_participant_progress' => 'training.proof.review',
            'backend_health_snapshot' => 'training.campaign.view',
            'save_training_note' => 'training.note.create',
            'save_campaign_checkpoint' => 'training.campaign.manage',
            'mark_participant_focus' => 'training.proof.review',
            'create_workflow_snapshot' => 'training.campaign.view',
            'run_core_workflow_qa' => 'training.ops.qa',
        ];
        return $map[$action] ?? 'training.campaign.view';
    }
}

if (!function_exists('tl_account_bridge_authorize_action')) {
    function tl_account_bridge_authorize_action(string $action): array
    {
        $permission = tl_account_bridge_action_permission($action);
        $user = tl_auth_current_user();
        $allowed = tl_account_bridge_can($permission, $user);
        if (!$allowed) {
            throw new RuntimeException('Permission denied for ' . $action . '. Required permission: ' . $permission . '.');
        }
        return ['permission' => $permission, 'allowed' => true, 'user' => $user];
    }
}

if (!function_exists('tl_account_bridge_apply_actor_to_input')) {
    function tl_account_bridge_apply_actor_to_input(array $input): array
    {
        $user = tl_auth_current_user();
        if (!$user) return $input;
        $numericId = tl_account_bridge_numeric_user_id($user);
        if (empty($input['user_id'])) $input['user_id'] = $numericId;
        if (empty($input['owner_user_id'])) $input['owner_user_id'] = $numericId;
        if (empty($input['created_by_user_id'])) $input['created_by_user_id'] = $numericId;
        if (empty($input['submitted_by_user_id'])) $input['submitted_by_user_id'] = $numericId;
        if (empty($input['reviewer_user_id']) && in_array((string)($user['role'] ?? ''), ['coach','reviewer','manager','admin'], true)) $input['reviewer_user_id'] = $numericId;
        if (empty($input['participant_label'])) $input['participant_label'] = (string)($user['name'] ?? 'Training Participant');
        return $input;
    }
}

if (!function_exists('tl_account_bridge_handle_auth_action')) {
    function tl_account_bridge_handle_auth_action(array $input): array
    {
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($input['auth_action'] ?? $input['action'] ?? ''));
        if ($action === 'sync_microgifter') return tl_account_bridge_sync_microgifter();
        if ($action === 'training_login') return tl_account_bridge_create_training_user($input, false);
        if ($action === 'create_training_and_microgifter') return tl_account_bridge_create_training_user($input, true);
        if ($action === 'logout_training') {
            tl_account_bridge_logout();
            return ['logged_out' => true];
        }
        throw new RuntimeException('Unsupported account action: ' . $action);
    }
}
