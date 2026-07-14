<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/form-core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function same_origin_request(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin === '' || $host === '') {
        return true;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = explode(':', $host, 2)[0];
    return is_string($originHost) && strcasecmp($originHost, $requestHost) === 0;
}

function load_config(): array
{
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
    $defaultPath = dirname((string) $documentRoot) . '/wheretheyrest-private/mail.php';
    $path = getenv('WTR_MAIL_CONFIG') ?: $defaultPath;

    if (!is_file($path)) {
        throw new RuntimeException('Private mail configuration was not found.');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('Private mail configuration is invalid.');
    }

    return $config;
}

function configured_mailer(array $config): PHPMailer
{
    $smtp = $config['smtp'] ?? [];
    $sender = $config['sender'] ?? [];
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = (string) ($smtp['host'] ?? '');
    $mailer->Port = (int) ($smtp['port'] ?? 0);
    $mailer->SMTPAuth = true;
    $mailer->Username = (string) ($smtp['username'] ?? '');
    $mailer->Password = (string) ($smtp['password'] ?? '');
    $mailer->CharSet = 'UTF-8';
    $mailer->Timeout = 15;

    $encryption = strtolower((string) ($smtp['encryption'] ?? 'tls'));
    $mailer->SMTPSecure = match ($encryption) {
        'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
        'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
        default => '',
    };

    $senderEmail = (string) ($sender['email'] ?? '');
    $senderName = (string) ($sender['name'] ?? 'Where They Rest Website');
    if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Sender configuration is invalid.');
    }

    $mailer->setFrom($senderEmail, $senderName);
    return $mailer;
}

function development_preview_enabled(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return getenv('WTR_DEV_MODE') === '1' && in_array($remote, ['127.0.0.1', '::1'], true);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'This endpoint accepts form submissions only.']);
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
    respond(415, ['ok' => false, 'message' => 'Unsupported form submission format.']);
}

if (!same_origin_request()) {
    respond(403, ['ok' => false, 'message' => 'This request could not be accepted.']);
}

$validation = wtr_validate_submission($_POST);
$reference = wtr_reference();

if ($validation['spam']) {
    respond(200, ['ok' => true, 'reference' => $reference]);
}

if ($validation['errors'] !== []) {
    respond(422, [
        'ok' => false,
        'message' => 'Please check the highlighted information and try again.',
        'errors' => $validation['errors'],
    ]);
}

try {
    $config = load_config();
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!wtr_rate_limit($config, $ip)) {
        respond(429, ['ok' => false, 'message' => 'Please wait before sending another request.']);
    }

    $data = $validation['data'];
    $submittedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    $internal = wtr_internal_email($data, $reference, $submittedAt);
    $confirmation = wtr_confirmation_email($data, $reference);

    if (development_preview_enabled()) {
        $previewPath = sys_get_temp_dir() . '/wtr-mail-preview.jsonl';
        $preview = [
            'reference' => $reference,
            'recipient' => wtr_recipient_for($config, (string) $data['route']),
            'internal' => $internal,
            'confirmation_recipient' => $data['email'],
            'confirmation' => $confirmation,
        ];
        file_put_contents($previewPath, json_encode($preview, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        respond(200, ['ok' => true, 'reference' => $reference]);
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Mailer dependencies are unavailable.');
    }
    require_once $autoload;

    $recipient = wtr_recipient_for($config, (string) $data['route']);
    $internalMailer = configured_mailer($config);
    $internalMailer->addAddress($recipient);
    $internalMailer->addReplyTo((string) $data['email'], (string) $data['name']);
    $internalMailer->isHTML(true);
    $internalMailer->Subject = $internal['subject'];
    $internalMailer->Body = $internal['html'];
    $internalMailer->AltBody = $internal['text'];
    $internalMailer->send();

    try {
        $confirmationMailer = configured_mailer($config);
        $confirmationMailer->addAddress((string) $data['email'], (string) $data['name']);
        $confirmationMailer->addReplyTo('care@wheretheyrest.ca', 'Where They Rest Memorial Care');
        $confirmationMailer->isHTML(true);
        $confirmationMailer->Subject = $confirmation['subject'];
        $confirmationMailer->Body = $confirmation['html'];
        $confirmationMailer->AltBody = $confirmation['text'];
        $confirmationMailer->send();
    } catch (Throwable) {
        error_log('[WhereTheyRest] Confirmation delivery failed for reference ' . $reference);
    }

    respond(200, ['ok' => true, 'reference' => $reference]);
} catch (Throwable) {
    error_log('[WhereTheyRest] Submission delivery failed for reference ' . $reference);
    respond(500, [
        'ok' => false,
        'message' => 'We could not send your request right now. Please email care@wheretheyrest.ca instead.',
    ]);
}
