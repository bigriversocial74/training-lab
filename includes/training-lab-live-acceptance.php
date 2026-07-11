<?php
/** Read-only HTTPS and role-session smoke-test helpers. */

if (!function_exists('tl_live_acceptance_validate_base_url')) {
    function tl_live_acceptance_validate_base_url(string $baseUrl): array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($baseUrl === '' || $host === '' || !in_array($scheme, ['https', 'http'], true)) {
            return ['valid' => false, 'error' => 'A valid absolute base URL is required.', 'base_url' => ''];
        }
        if ($scheme !== 'https' && !$isLocal) {
            return ['valid' => false, 'error' => 'Production live acceptance requires HTTPS.', 'base_url' => ''];
        }
        if (!empty($parts['user']) || !empty($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return ['valid' => false, 'error' => 'Do not place credentials, query strings, or fragments in the base URL.', 'base_url' => ''];
        }
        return ['valid' => true, 'error' => null, 'base_url' => $baseUrl, 'host' => $host, 'scheme' => $scheme];
    }
}

if (!function_exists('tl_live_acceptance_url')) {
    function tl_live_acceptance_url(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('tl_live_acceptance_parse_headers')) {
    function tl_live_acceptance_parse_headers(array $lines): array
    {
        $headers = [];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) continue;
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = strtolower($name);
            if ($name === '') continue;
            $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
        }
        return $headers;
    }
}

if (!function_exists('tl_live_acceptance_request')) {
    function tl_live_acceptance_request(string $url, string $cookie = '', int $timeout = 10): array
    {
        $headers = ['Accept: text/html,application/json;q=0.9,*/*;q=0.8', 'User-Agent: TrainingLab-Live-Acceptance/1.0'];
        if ($cookie !== '') $headers[] = 'Cookie: ' . str_replace(["\r", "\n"], '', $cookie);

        if (function_exists('curl_init')) {
            $responseHeaders = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                    $length = strlen($line);
                    $line = trim($line);
                    if ($line !== '' && str_contains($line, ':')) $responseHeaders[] = $line;
                    return $length;
                },
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = $body === false ? curl_error($ch) : null;
            curl_close($ch);
            return [
                'ok' => $error === null && $status > 0,
                'status' => $status,
                'headers' => tl_live_acceptance_parse_headers($responseHeaders),
                'body_bytes' => is_string($body) ? strlen($body) : 0,
                'error' => $error,
            ];
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $responseLines = $http_response_header ?? [];
        $status = 0;
        foreach ($responseLines as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) $status = (int)$matches[1];
        }
        return [
            'ok' => $status > 0,
            'status' => $status,
            'headers' => tl_live_acceptance_parse_headers($responseLines),
            'body_bytes' => is_string($body) ? strlen($body) : 0,
            'error' => $status > 0 ? null : 'HTTP request failed.',
        ];
    }
}

if (!function_exists('tl_live_acceptance_anonymous_routes')) {
    function tl_live_acceptance_anonymous_routes(): array
    {
        return [
            ['key' => 'home', 'path' => '/', 'expected' => [200]],
            ['key' => 'signin', 'path' => '/signin.php', 'expected' => [200]],
            ['key' => 'accessibility', 'path' => '/accessibility.php', 'expected' => [200]],
            ['key' => 'participant_guard', 'path' => '/app/index.php', 'expected' => [301, 302, 303, 307, 308], 'location_contains' => 'signin.php'],
            ['key' => 'admin_guard', 'path' => '/admin/index.php', 'expected' => [301, 302, 303, 307, 308], 'location_contains' => 'signin.php'],
        ];
    }
}

if (!function_exists('tl_live_acceptance_role_routes')) {
    function tl_live_acceptance_role_routes(): array
    {
        return [
            'participant' => [
                'env' => 'TL_ACCEPTANCE_PARTICIPANT_COOKIE',
                'allow' => ['/app/index.php', '/app/campaigns.php', '/app/getting-started.php'],
                'deny' => ['/admin/product-acceptance.php'],
            ],
            'reviewer' => [
                'env' => 'TL_ACCEPTANCE_REVIEWER_COOKIE',
                'allow' => ['/admin/index.php', '/admin/review-workbench.php'],
                'deny' => ['/admin/reward-operations.php'],
            ],
            'manager' => [
                'env' => 'TL_ACCEPTANCE_MANAGER_COOKIE',
                'allow' => ['/admin/index.php', '/admin/campaigns.php', '/admin/reward-rules.php'],
                'deny' => ['/admin/product-acceptance.php'],
            ],
            'admin' => [
                'env' => 'TL_ACCEPTANCE_ADMIN_COOKIE',
                'allow' => ['/admin/product-acceptance.php', '/admin/reward-operations.php', '/admin/live-acceptance.php'],
                'deny' => [],
            ],
        ];
    }
}

if (!function_exists('tl_live_acceptance_security_header_checks')) {
    function tl_live_acceptance_security_header_checks(array $headers, bool $https): array
    {
        $required = [
            'x-request-id' => null,
            'x-content-type-options' => 'nosniff',
            'x-frame-options' => 'SAMEORIGIN',
            'referrer-policy' => 'strict-origin-when-cross-origin',
            'permissions-policy' => null,
            'content-security-policy' => null,
        ];
        if ($https) $required['strict-transport-security'] = null;
        $checks = [];
        foreach ($required as $name => $expected) {
            $value = (string)($headers[$name] ?? '');
            $passed = $value !== '' && ($expected === null || strcasecmp($value, $expected) === 0);
            $checks[] = [
                'key' => 'header_' . str_replace('-', '_', $name),
                'label' => 'Security header: ' . $name,
                'passed' => $passed,
                'detail' => $passed ? 'Present.' : 'Missing or unexpected.',
                'category' => 'security_headers',
            ];
        }
        return $checks;
    }
}

if (!function_exists('tl_live_acceptance_report')) {
    function tl_live_acceptance_report(string $baseUrl, bool $requireRoleSessions = false): array
    {
        $validation = tl_live_acceptance_validate_base_url($baseUrl);
        if (!$validation['valid']) {
            return ['ready' => false, 'score' => 0, 'checks' => [], 'failed' => [], 'skipped' => [], 'error' => $validation['error']];
        }
        $baseUrl = (string)$validation['base_url'];
        $checks = [];
        $skipped = [];
        $homeResponse = null;

        foreach (tl_live_acceptance_anonymous_routes() as $route) {
            $response = tl_live_acceptance_request(tl_live_acceptance_url($baseUrl, $route['path']));
            if ($route['key'] === 'home') $homeResponse = $response;
            $passed = $response['ok'] && in_array((int)$response['status'], $route['expected'], true);
            if ($passed && isset($route['location_contains'])) {
                $location = (string)($response['headers']['location'] ?? '');
                $passed = str_contains($location, (string)$route['location_contains']);
            }
            $checks[] = [
                'key' => 'anonymous_' . $route['key'],
                'label' => 'Anonymous route: ' . $route['path'],
                'passed' => $passed,
                'detail' => 'HTTP ' . (int)$response['status'],
                'category' => 'anonymous_routes',
            ];
        }

        if (is_array($homeResponse)) {
            $checks = array_merge(
                $checks,
                tl_live_acceptance_security_header_checks(
                    (array)$homeResponse['headers'],
                    (string)$validation['scheme'] === 'https'
                )
            );
        }

        foreach (tl_live_acceptance_role_routes() as $role => $definition) {
            $cookie = (string)(getenv($definition['env']) ?: '');
            if ($cookie === '') {
                $entry = [
                    'key' => 'role_' . $role . '_session',
                    'label' => ucfirst($role) . ' live session',
                    'passed' => !$requireRoleSessions,
                    'detail' => $requireRoleSessions ? 'Required session cookie is missing.' : 'Skipped; set ' . $definition['env'] . ' to test this role.',
                    'category' => 'role_sessions',
                    'skipped' => !$requireRoleSessions,
                ];
                $checks[] = $entry;
                if (!$requireRoleSessions) $skipped[] = $entry;
                continue;
            }
            foreach ($definition['allow'] as $path) {
                $response = tl_live_acceptance_request(tl_live_acceptance_url($baseUrl, $path), $cookie);
                $checks[] = [
                    'key' => 'role_' . $role . '_allow_' . md5($path),
                    'label' => ucfirst($role) . ' access: ' . $path,
                    'passed' => $response['ok'] && (int)$response['status'] === 200,
                    'detail' => 'HTTP ' . (int)$response['status'],
                    'category' => 'role_sessions',
                ];
            }
            foreach ($definition['deny'] as $path) {
                $response = tl_live_acceptance_request(tl_live_acceptance_url($baseUrl, $path), $cookie);
                $checks[] = [
                    'key' => 'role_' . $role . '_deny_' . md5($path),
                    'label' => ucfirst($role) . ' denied: ' . $path,
                    'passed' => $response['ok'] && (int)$response['status'] !== 200,
                    'detail' => 'HTTP ' . (int)$response['status'],
                    'category' => 'role_sessions',
                ];
            }
        }

        $failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['passed']));
        $scored = array_values(array_filter($checks, static fn(array $check): bool => empty($check['skipped'])));
        return [
            'ready' => count($failed) === 0,
            'score' => count($scored) > 0 ? (int)round((count($scored) - count($failed)) / count($scored) * 100) : 0,
            'checks' => $checks,
            'failed' => $failed,
            'skipped' => $skipped,
            'base_url' => $baseUrl,
            'generated_at' => gmdate('c'),
            'safe_boundaries' => [
                'get_requests_only' => true,
                'same_base_url_only' => true,
                'no_cookie_output' => true,
                'no_write_actions' => true,
                'no_reward_or_worker_actions' => true,
            ],
        ];
    }
}
