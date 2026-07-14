<?php

declare(strict_types=1);

/**
 * EXAMPLE ONLY — do not put real credentials in this repository.
 *
 * Copy this file to a private location outside public_html, normally:
 * /home/CPANEL_USERNAME/wheretheyrest-private/mail.php
 */
return [
    'smtp' => [
        'host' => 'YOUR_GREENGEEKS_SMTP_HOST',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'care@wheretheyrest.ca',
        'password' => 'PASTE_THE_MAILBOX_PASSWORD_ONLY_IN_THE_PRIVATE_COPY',
    ],
    'sender' => [
        'email' => 'care@wheretheyrest.ca',
        'name' => 'Where They Rest Website',
    ],
    'recipients' => [
        'care' => 'care@wheretheyrest.ca',
        'hello' => 'hello@wheretheyrest.ca',
        'stewards' => 'stewards@wheretheyrest.ca',
    ],
    'rate_limit' => [
        'max_submissions' => 5,
        'window_seconds' => 900,
        'directory' => '/home/CPANEL_USERNAME/wheretheyrest-private/rate-limits',
        'salt' => 'REPLACE_WITH_AT_LEAST_32_RANDOM_CHARACTERS',
    ],
];
