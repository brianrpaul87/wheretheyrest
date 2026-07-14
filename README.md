# Where They Rest

Official website repository for **Where They Rest Memorial Care**.

> Wherever you are, we care for where they rest.

Where They Rest helps families arrange respectful grave and memorial care when they cannot visit in person.

## Website stack

- Semantic HTML and responsive CSS
- Lightweight vanilla JavaScript
- PHP 8.1+ request endpoint
- PHPMailer over authenticated SMTP
- Local SVG brand and illustration assets

## Request routing

The existing request form routes mail by the visitor's selected interest:

- `care@wheretheyrest.ca`: single visits, seasonal care, flowers, and condition updates
- `stewards@wheretheyrest.ca`: Authorized Memorial Steward interest
- `hello@wheretheyrest.ca`: cemetery partnerships and general inquiries

All internal messages are sent from `care@wheretheyrest.ca`, with the visitor's validated address set as `Reply-To`. A separate acknowledgment is sent to the visitor without promising availability, pricing, timing, or approval.

## Local frontend

From the repository root:

```bash
python -m http.server 8080
```

Then open `http://localhost:8080`.

The PHP endpoint requires a PHP server rather than Python's static server:

```bash
composer install
WTR_DEV_MODE=1 WTR_MAIL_CONFIG="$PWD/config/mail.local.php" php -S 127.0.0.1:8080
```

Copy `config/mail.example.php` to `config/mail.local.php` and use placeholder/local-only values. Development preview mode works only when `WTR_DEV_MODE=1` **and** the request comes from loopback. It writes generated mail previews to the operating system's temporary directory and never exposes the payload in the browser response.

Run the dependency-free core tests with:

```bash
php tests/form-core-test.php
```

## GreenGeeks deployment

The intended site path is:

```text
/public_html/wheretheyrest
```

### 1. Upload the application

Upload the repository contents to `/public_html/wheretheyrest`. Do not upload `.git` history or any private credential file.

### 2. Install PHPMailer

From the site directory in cPanel Terminal or SSH:

```bash
composer install --no-dev --optimize-autoloader
```

If Composer is unavailable on the server, run that command locally and upload the generated `vendor/` directory with the rest of the site. The `vendor/` directory is intentionally excluded from Git.

### 3. Create private configuration outside public_html

Create:

```text
/home/CPANEL_USERNAME/wheretheyrest-private/mail.php
```

Start with `config/mail.example.php`. Replace every placeholder in the private copy only. Never commit or paste the mailbox password into GitHub, website JavaScript, HTML, screenshots, or support messages.

The endpoint automatically looks one directory above `DOCUMENT_ROOT` for `wheretheyrest-private/mail.php`. A custom absolute path can be supplied with the `WTR_MAIL_CONFIG` environment variable if the hosting layout differs.

### 4. Locate GreenGeeks SMTP settings

In cPanel:

1. Open **Email Accounts**.
2. Find `care@wheretheyrest.ca`.
3. Choose **Connect Devices** or **Set Up Mail Client**.
4. Copy the secure outgoing-server hostname, SMTP port, and encryption method.
5. Enter those values and the `care@` mailbox password only in the private configuration file.

Typical combinations are port 465 with SSL/SMTPS or port 587 with TLS/STARTTLS. Use the exact values displayed by GreenGeeks rather than guessing.

### 5. Set recipients and private rate-limit storage

Keep the three recipient addresses in the private file. Create the configured rate-limit directory outside public_html, for example:

```text
/home/CPANEL_USERNAME/wheretheyrest-private/rate-limits
```

Use permissions that allow the site's PHP process to write there but do not make it public. Replace the example salt with at least 32 random characters.

### 6. Confirm email authentication

In cPanel **Email Deliverability**, confirm SPF and DKIM are valid for `wheretheyrest.ca`. Fix any warning shown there before relying on automated acknowledgments.

### 7. Test every route

Submit one test for each category and confirm delivery:

- care request → `care@wheretheyrest.ca`
- steward interest → `stewards@wheretheyrest.ca`
- cemetery/general inquiry → `hello@wheretheyrest.ca`

For each test, verify:

- the internal message arrives with the correct subject and reference;
- Reply sends to the visitor rather than the website mailbox;
- the visitor acknowledgment arrives;
- Inbox and Spam/Junk folders have both been checked;
- no SMTP password or server diagnostic appears in the browser response.

### 8. Troubleshooting safely

- Confirm `vendor/autoload.php` exists.
- Confirm the private configuration path and file permissions.
- Recheck the SMTP hostname, port, encryption, username, and password in cPanel.
- Check the hosting PHP error log for the submission reference only.
- Never enable PHPMailer SMTP debug output on the public endpoint.
- Never send a credential file or mailbox password in email or chat.

## Security controls

The endpoint accepts same-origin URL-encoded POST requests only and includes server-side allow-lists, length limits, consent enforcement, a hidden honeypot, minimum completion time, HTML escaping, a plain-text email alternative, a per-IP hashed rate limit, generic public errors, and minimal reference-only logging. It does not accept file uploads and does not store submissions in a database.
