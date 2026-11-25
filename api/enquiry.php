<?php
/*
 * Contact and private hire form handler for the static site.
 * Same fields, validation, reCAPTCHA v3 check and Brevo email as ContactController and
 * PrivateHireController on the previous Laravel site.
 *
 * Setup on the server: copy api/forms.env.example to a file called temper-forms.env ONE LEVEL
 * ABOVE the web root (for example next to public_html, not inside it) and fill in the values from
 * the previous site's .env. A different location can be given in the TEMPER_FORMS_ENV variable.
 * Never commit the filled-in file.
 */
declare(strict_types=1);

const FALLBACK_MESSAGE = "Sorry, your message couldn't be sent. Please try again, or email reservationsmanager@temperrestaurant.com.";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, bool $ok, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

function settings(): array
{
    // Above the web root: two levels up for the live site, three for a test copy in a sub-folder.
    $candidates = array_filter([getenv('TEMPER_FORMS_ENV'), dirname(__DIR__, 2) . '/temper-forms.env', dirname(__DIR__, 3) . '/temper-forms.env']);
    $lines = false;
    foreach ($candidates as $file) {
        if (@is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            break;
        }
    }
    if ($lines === false) {
        error_log('temper forms: settings file not found or not readable');
        respond(500, false, FALLBACK_MESSAGE);
    }

    $values = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }
        // Support references to earlier keys, e.g. MAIL_FROM_NAME="${APP_NAME}"
        $values[$key] = preg_replace_callback('/\$\{(\w+)\}/', fn ($m) => $values[$m[1]] ?? '', $value);
    }

    foreach (['BREVO_API_KEY', 'MAIL_FROM_ADDRESS', 'MAIL_TO_ADDRESS', 'RECAPTCHA_SECRET_KEY'] as $key) {
        if (($values[$key] ?? '') === '') {
            error_log("temper forms: {$key} is missing from the settings file");
            respond(500, false, FALLBACK_MESSAGE);
        }
    }

    return $values;
}

/** Reads one posted field, enforcing "required" and a maximum length. */
function field(string $name, string $label, bool $required, int $max): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        respond(422, false, "Please check the {$label} field.");
    }
    $value = trim($value);
    if ($required && $value === '') {
        respond(422, false, "Please fill in the {$label} field.");
    }
    if (mb_strlen($value) > $max) {
        respond(422, false, "The {$label} field is too long.");
    }

    return $value;
}

function emailField(): string
{
    $email = field('email', 'email', true, 255);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        respond(422, false, 'Please enter a valid email address.');
    }

    return $email;
}

function postRequest(string $url, array $headers, string $body): ?array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($curl);
    if ($response === false) {
        error_log('temper forms: request failed: ' . curl_error($curl));
        return null;
    }
    $decoded = json_decode((string) $response, true);

    return is_array($decoded) ? $decoded : null;
}

function verifyRecaptcha(array $settings, string $action): void
{
    $token = $_POST['g-recaptcha-response'] ?? '';
    if (!is_string($token) || trim($token) === '') {
        respond(422, false, 'Please try again — security verification failed.');
    }

    $result = postRequest(
        'https://www.google.com/recaptcha/api/siteverify',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(array_filter([
            'secret' => $settings['RECAPTCHA_SECRET_KEY'],
            'response' => trim($token),
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]))
    );
    $threshold = (float) ($settings['RECAPTCHA_SCORE_THRESHOLD'] ?? 0.5);

    if (empty($result['success']) || ($result['action'] ?? '') !== $action || (float) ($result['score'] ?? 0) < $threshold) {
        error_log('temper forms: reCAPTCHA rejected a submission: ' . implode(',', (array) ($result['error-codes'] ?? ['score or action'])));
        respond(422, false, 'Your message could not be sent. Please try again in a moment.');
    }
}

function emailHtml(string $intro, array $rows): string
{
    $escape = fn (string $text) => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $cells = '';
    foreach ($rows as $label => $value) {
        $cells .= '<tr><th align="left" valign="top">' . $escape($label) . ':</th><td>'
            . nl2br($escape($value !== '' ? $value : '-')) . '</td></tr>';
    }

    return '<!DOCTYPE html><html lang="en"><body style="margin:0;padding:40px 0;background:#f2f2f2;font-family:Arial,sans-serif;color:#555">'
        . '<table align="center" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:6px">'
        . '<tr><td align="center" style="padding:30px 0;background:#141312;color:#f5ede0;font-size:28px;letter-spacing:.4em">temper</td></tr>'
        . '<tr><td align="center" style="padding:20px;font-size:16px">' . $escape($intro) . '</td></tr>'
        . '<tr><td align="center" style="padding:0 40px 30px;font-size:16px"><table border="1" cellpadding="8" style="border-collapse:collapse">' . $cells . '</table></td></tr>'
        . '</table></body></html>';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Method not allowed.');
}

$type = $_POST['form_type'] ?? '';

if ($type === 'contact') {
    $name = field('name', 'name', true, 255);
    $email = emailField();
    $phone = field('phone', 'phone', false, 255);
    $message = field('message', 'message', true, 5000);

    $action = 'contact';
    $subjectStart = 'Contact Us — ';
    $intro = 'New message has been received from website.';
    $rows = ['Name' => $name, 'Email' => $email, 'Phone' => $phone, 'Message' => $message];
    $success = 'Your message has successfully been sent';
} elseif ($type === 'private_hire') {
    $name = field('name', 'name', true, 255);
    $email = emailField();
    $room = field('room', 'preferred room', true, 255);
    $date = field('date', 'date', false, 10);
    $guests = field('guests', 'guests', false, 4);
    $budget = field('budget', 'budget', false, 255);
    $notes = field('notes', 'notes', false, 3000);

    if ($date !== '') {
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            respond(422, false, 'Please enter a valid date.');
        }
    }
    if ($guests !== '' && filter_var($guests, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 5000]]) === false) {
        respond(422, false, 'Guests must be a number from 2 to 5000.');
    }

    $action = 'private_hire';
    $subjectStart = 'Private Hire Enquiry - ';
    $intro = 'New private hire enquiry has been received from website.';
    $rows = ['Name' => $name, 'Email' => $email, 'Preferred room' => $room, 'Date' => $date,
        'Guests' => $guests, 'Budget per head' => $budget, 'Notes' => $notes];
    $success = 'Your private hire enquiry has successfully been sent';
} else {
    respond(422, false, FALLBACK_MESSAGE);
}

$settings = settings();
verifyRecaptcha($settings, $action);

$appName = ($settings['APP_NAME'] ?? '') ?: 'Temper Steak Restaurant';
$result = postRequest(
    'https://api.brevo.com/v3/smtp/email',
    ['accept: application/json', 'content-type: application/json', 'api-key: ' . $settings['BREVO_API_KEY']],
    (string) json_encode([
        'sender' => ['name' => ($settings['MAIL_FROM_NAME'] ?? '') ?: $appName, 'email' => $settings['MAIL_FROM_ADDRESS']],
        'to' => [['email' => $settings['MAIL_TO_ADDRESS']]],
        'replyTo' => ['email' => $email],
        'subject' => $subjectStart . $appName,
        'htmlContent' => emailHtml($intro, $rows),
    ])
);

if (empty($result['messageId'])) {
    error_log('temper forms: Brevo did not accept the email: ' . ($result['message'] ?? 'no response'));
    respond(502, false, FALLBACK_MESSAGE);
}

respond(200, true, $success);
