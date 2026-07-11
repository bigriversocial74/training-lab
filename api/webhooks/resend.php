<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/training-lab-resend-webhooks.php';

tl_security_headers(true);
try {
    tl_security_require_method('POST');
    $configuration = tl_resend_webhook_config();
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > (int)$configuration['max_body_bytes']) {
        throw new TlHttpException('The webhook body exceeds the safe size limit.', 413, 'resend_webhook_body_too_large');
    }
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($contentType !== '' && !str_contains($contentType, 'application/json')) {
        throw new TlHttpException('The webhook content type must be application/json.', 415, 'resend_webhook_content_type_invalid');
    }
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody)) throw new TlHttpException('The webhook body could not be read.', 400, 'resend_webhook_body_unavailable');
    $result = tl_resend_webhook_ingest($rawBody, tl_resend_webhook_headers($_SERVER));
    tl_security_json_response([
        'ok'=>true,
        'status'=>(string)($result['status'] ?? 'accepted'),
        'duplicate'=>!empty($result['duplicate']),
        'event_confirmation'=>(string)($result['event_confirmation'] ?? ''),
    ], 200);
} catch (Throwable $error) {
    tl_security_json_exception($error);
}
