<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/form-core.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$now = 1_800_000_000_000;
$base = [
    'name' => 'Jamie Family',
    'email' => 'jamie@example.com',
    'location' => 'Peterborough',
    'province' => 'Ontario',
    'interest' => 'single-visit',
    'message' => 'Please let me know what information you need next.',
    'consent' => 'on',
    'website' => '',
    'started_at' => (string) ($now - 5000),
    'page' => 'https://wheretheyrest.ca/#request-care',
];

foreach ([
    'single-visit' => 'care',
    'seasonal-care' => 'care',
    'flowers' => 'care',
    'condition-update' => 'care',
    'steward' => 'stewards',
    'cemetery' => 'hello',
    'other' => 'hello',
] as $interest => $route) {
    $input = $base;
    $input['interest'] = $interest;
    $result = wtr_validate_submission($input, $now);
    assert_true($result['errors'] === [], "{$interest} should validate");
    assert_true($result['data']['route'] === $route, "{$interest} should route to {$route}");
}

$invalidEmail = $base;
$invalidEmail['email'] = "bad@example.com\nBcc: attacker@example.com";
$result = wtr_validate_submission($invalidEmail, $now);
assert_true(isset($result['errors']['email']), 'header-injection email must be rejected');

$missingConsent = $base;
unset($missingConsent['consent']);
$result = wtr_validate_submission($missingConsent, $now);
assert_true(isset($result['errors']['consent']), 'missing consent must be rejected');

$unknownInterest = $base;
$unknownInterest['interest'] = 'unknown';
$result = wtr_validate_submission($unknownInterest, $now);
assert_true(isset($result['errors']['interest']), 'unknown interest must be rejected');

$unknownProvince = $base;
$unknownProvince['province'] = 'Not a province';
$result = wtr_validate_submission($unknownProvince, $now);
assert_true(isset($result['errors']['province']), 'unknown province must be rejected');

$tooFast = $base;
$tooFast['started_at'] = (string) ($now - 1000);
$result = wtr_validate_submission($tooFast, $now);
assert_true(isset($result['errors']['started_at']), 'too-fast submission must be rejected');

$trap = $base;
$trap['website'] = 'https://spam.invalid';
$result = wtr_validate_submission($trap, $now);
assert_true($result['spam'] === true, 'honeypot submission must be silently classified as spam');

$longMessage = $base;
$longMessage['message'] = str_repeat('x', 2001);
$result = wtr_validate_submission($longMessage, $now);
assert_true(isset($result['errors']['message']), 'overlong message must be rejected');

$htmlInput = $base;
$htmlInput['message'] = '<script>alert(1)</script>';
$result = wtr_validate_submission($htmlInput, $now);
$email = wtr_internal_email($result['data'], 'WTR-TEST-1234', '2026-07-14T02:00:00+00:00');
assert_true(!str_contains($email['html'], '<script>'), 'HTML content must be escaped');
assert_true(str_contains($email['html'], '&lt;script&gt;'), 'escaped HTML should remain readable');
assert_true(str_contains($email['text'], '<script>alert(1)</script>'), 'plain-text alternative should retain safe text');

$care = wtr_validate_submission($base, $now)['data'];
assert_true(str_starts_with(wtr_subject($care), '[Where They Rest Care Request]'), 'care subject should be clear');

$steward = $base;
$steward['interest'] = 'steward';
$stewardData = wtr_validate_submission($steward, $now)['data'];
assert_true(str_starts_with(wtr_subject($stewardData), '[Where They Rest Steward Interest]'), 'steward subject should be clear');

echo "All Where They Rest form-core tests passed.\n";
