<?php

declare(strict_types=1);

const WTR_PROVINCES = [
    'Alberta',
    'British Columbia',
    'Manitoba',
    'New Brunswick',
    'Newfoundland and Labrador',
    'Northwest Territories',
    'Nova Scotia',
    'Nunavut',
    'Ontario',
    'Prince Edward Island',
    'Quebec',
    'Saskatchewan',
    'Yukon',
];

const WTR_INTERESTS = [
    'single-visit' => ['label' => 'A single memorial visit', 'route' => 'care'],
    'seasonal-care' => ['label' => 'Seasonal or recurring care', 'route' => 'care'],
    'flowers' => ['label' => 'Flower or tribute placement', 'route' => 'care'],
    'condition-update' => ['label' => 'Photos and condition update', 'route' => 'care'],
    'cemetery' => ['label' => 'Cemetery partnership', 'route' => 'hello'],
    'steward' => ['label' => 'Becoming a Memorial Steward', 'route' => 'stewards'],
    'other' => ['label' => 'Something else', 'route' => 'hello'],
];

function wtr_text(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim(str_replace("\0", '', str_replace(["\r\n", "\r"], "\n", $value)));
}

function wtr_single_line(mixed $value): string
{
    $text = wtr_text($value);
    return str_replace("\n", ' ', $text);
}

function wtr_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function wtr_consent_given(mixed $value): bool
{
    return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true);
}

/**
 * @return array{spam: bool, errors: array<string, string>, data: array<string, mixed>}
 */
function wtr_validate_submission(array $input, ?int $nowMs = null): array
{
    $nowMs ??= (int) floor(microtime(true) * 1000);
    $website = wtr_single_line($input['website'] ?? '');

    if ($website !== '') {
        return ['spam' => true, 'errors' => [], 'data' => []];
    }

    $name = wtr_single_line($input['name'] ?? '');
    $email = wtr_single_line($input['email'] ?? '');
    $location = wtr_single_line($input['location'] ?? '');
    $province = wtr_single_line($input['province'] ?? '');
    $interest = wtr_single_line($input['interest'] ?? '');
    $message = wtr_text($input['message'] ?? '');
    $page = wtr_single_line($input['page'] ?? '');
    $startedAtRaw = wtr_single_line($input['started_at'] ?? '');
    $errors = [];

    if ($name === '' || wtr_length($name) > 100) {
        $errors['name'] = 'Please enter your name (100 characters maximum).';
    }

    if ($email === '' || wtr_length($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($location === '' || wtr_length($location) > 140) {
        $errors['location'] = 'Please enter the cemetery city or community.';
    }

    if (!in_array($province, WTR_PROVINCES, true)) {
        $errors['province'] = 'Please select a valid province or territory.';
    }

    if (!array_key_exists($interest, WTR_INTERESTS)) {
        $errors['interest'] = 'Please select a valid request type.';
    }

    if (wtr_length($message) > 2000) {
        $errors['message'] = 'Please keep the message under 2,000 characters.';
    }

    if ($page !== '' && wtr_length($page) > 300) {
        $errors['page'] = 'Invalid originating page.';
    }

    if (!wtr_consent_given($input['consent'] ?? null)) {
        $errors['consent'] = 'Consent is required before sending.';
    }

    if ($startedAtRaw === '' || !ctype_digit($startedAtRaw)) {
        $errors['started_at'] = 'Invalid form timing.';
    } else {
        $elapsedMs = $nowMs - (int) $startedAtRaw;
        if ($elapsedMs < 3000 || $elapsedMs > 7200000) {
            $errors['started_at'] = 'Please refresh the page and try again.';
        }
    }

    $route = WTR_INTERESTS[$interest]['route'] ?? '';
    $label = WTR_INTERESTS[$interest]['label'] ?? '';

    return [
        'spam' => false,
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'email' => $email,
            'location' => $location,
            'province' => $province,
            'interest' => $interest,
            'interest_label' => $label,
            'route' => $route,
            'message' => $message,
            'page' => $page,
        ],
    ];
}

function wtr_reference(?DateTimeImmutable $now = null): string
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return 'WTR-' . $now->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function wtr_subject(array $data): string
{
    $name = str_replace(["\r", "\n"], ' ', (string) $data['name']);
    $location = str_replace(["\r", "\n"], ' ', (string) $data['location']);

    return match ($data['route']) {
        'stewards' => sprintf('[Where They Rest Steward Interest] %s — %s', $name, $data['province']),
        'hello' => sprintf('[Where They Rest General Inquiry] %s', $name),
        default => sprintf('[Where They Rest Care Request] %s — %s', $name, $location),
    };
}

function wtr_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return array{subject: string, html: string, text: string}
 */
function wtr_internal_email(array $data, string $reference, string $submittedAt): array
{
    $message = $data['message'] !== '' ? $data['message'] : 'No additional message supplied.';
    $rows = [
        'Reference' => $reference,
        'Submission type' => $data['interest_label'],
        'Name' => $data['name'],
        'Email' => $data['email'],
        'Cemetery community' => $data['location'],
        'Province or territory' => $data['province'],
        'Message' => $message,
        'Submitted at' => $submittedAt,
        'Originating page' => $data['page'] !== '' ? $data['page'] : 'Not supplied',
    ];

    $htmlRows = '';
    $textRows = [];
    foreach ($rows as $label => $value) {
        $htmlRows .= sprintf(
            '<tr><th align="left" style="padding:8px 12px;border-bottom:1px solid #ded8cc;vertical-align:top">%s</th><td style="padding:8px 12px;border-bottom:1px solid #ded8cc">%s</td></tr>',
            wtr_html($label),
            nl2br(wtr_html((string) $value)),
        );
        $textRows[] = $label . ': ' . $value;
    }

    $html = '<h2 style="color:#173d34">New Where They Rest website request</h2>'
        . '<table role="presentation" style="border-collapse:collapse;width:100%;max-width:720px">'
        . $htmlRows
        . '</table>'
        . '<p><strong>This is a request for review, not an approved booking.</strong> Confirm availability, cemetery access, service scope, timing, and pricing before making any commitment.</p>';

    $text = "New Where They Rest website request\n\n"
        . implode("\n", $textRows)
        . "\n\nThis is a request for review, not an approved booking. Confirm availability, cemetery access, service scope, timing, and pricing before making any commitment.";

    return ['subject' => wtr_subject($data), 'html' => $html, 'text' => $text];
}

/**
 * @return array{subject: string, html: string, text: string}
 */
function wtr_confirmation_email(array $data, string $reference): array
{
    $subject = 'We received your Where They Rest request — ' . $reference;
    $safeName = wtr_html((string) $data['name']);
    $safeReference = wtr_html($reference);
    $html = "<p>Hello {$safeName},</p>"
        . '<p>Thank you for contacting Where They Rest. We received your request and will review the cemetery location, local availability, access requirements, and the care you are considering.</p>'
        . "<p>Your reference is <strong>{$safeReference}</strong>.</p>"
        . '<p>This acknowledgment does not confirm service availability, pricing, timing, or approval. We will contact you before any work is arranged.</p>'
        . '<p>With care,<br>Where They Rest Memorial Care</p>';
    $text = "Hello {$data['name']},\n\n"
        . "Thank you for contacting Where They Rest. We received your request and will review the cemetery location, local availability, access requirements, and the care you are considering.\n\n"
        . "Your reference is {$reference}.\n\n"
        . "This acknowledgment does not confirm service availability, pricing, timing, or approval. We will contact you before any work is arranged.\n\n"
        . "With care,\nWhere They Rest Memorial Care";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function wtr_recipient_for(array $config, string $route): string
{
    $recipients = $config['recipients'] ?? [];
    $key = in_array($route, ['care', 'hello', 'stewards'], true) ? $route : 'care';
    $recipient = $recipients[$key] ?? '';

    if (!is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Recipient configuration is invalid.');
    }

    return $recipient;
}

/**
 * Lightweight per-IP limit for shared hosting. Raw IP addresses are never stored.
 */
function wtr_rate_limit(array $config, string $ip, ?int $now = null): bool
{
    $now ??= time();
    $limit = max(1, (int) ($config['rate_limit']['max_submissions'] ?? 5));
    $window = max(60, (int) ($config['rate_limit']['window_seconds'] ?? 900));
    $directory = (string) ($config['rate_limit']['directory'] ?? sys_get_temp_dir() . '/wtr-rate-limits');
    $salt = (string) ($config['rate_limit']['salt'] ?? '');

    if ($salt === '') {
        throw new RuntimeException('Rate-limit salt is missing.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Rate-limit directory is unavailable.');
    }

    $key = hash('sha256', $salt . '|' . $ip);
    $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key . '.json';
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Rate-limit storage is unavailable.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Rate-limit lock failed.');
        }

        $raw = stream_get_contents($handle);
        $timestamps = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $cutoff = $now - $window;
        $timestamps = array_values(array_filter($timestamps, static fn ($ts): bool => is_int($ts) && $ts > $cutoff));
        if (count($timestamps) >= $limit) {
            return false;
        }

        $timestamps[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($timestamps, JSON_THROW_ON_ERROR));
        fflush($handle);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
