<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    respond(503, ['ok' => false, 'message' => 'Registration is being configured. Please try again shortly.']);
}

$config = require $configFile;
$allowedOrigin = rtrim((string)($config['allowed_origin'] ?? ''), '/');
$origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
if ($allowedOrigin !== '' && $origin !== '' && !hash_equals($allowedOrigin, $origin)) {
    respond(403, ['ok' => false, 'message' => 'Request origin is not allowed.']);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 20000) {
    respond(400, ['ok' => false, 'message' => 'Invalid request.']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    respond(400, ['ok' => false, 'message' => 'Invalid registration data.']);
}

if (trim((string)($data['company'] ?? '')) !== '') {
    // Honeypot: return success without forwarding bot data.
    respond(200, ['ok' => true]);
}

$name = trim((string)($data['name'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$phone = trim((string)($data['phone'] ?? ''));
$consent = filter_var($data['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
$eventId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['event_id'] ?? ''));

if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'message' => 'Please check your name and email address.']);
}
if ($phone === '' || mb_strlen($phone) > 40 || !$consent || $eventId === '') {
    respond(422, ['ok' => false, 'message' => 'Please provide a valid WhatsApp number and consent to receive class updates.']);
}

$rateDirectory = __DIR__ . '/rate-limit';
if (!is_dir($rateDirectory)) {
    @mkdir($rateDirectory, 0750, true);
}
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateFile = $rateDirectory . '/' . hash('sha256', $clientIp) . '.json';
$now = time();
$attempts = [];
if (is_file($rateFile)) {
    $stored = json_decode((string)file_get_contents($rateFile), true);
    if (is_array($stored)) $attempts = $stored;
}
$attempts = array_values(array_filter($attempts, static fn($time) => is_int($time) && $time > $now - 3600));
if (count($attempts) >= 12) {
    respond(429, ['ok' => false, 'message' => 'Too many attempts. Please try again later.']);
}
$attempts[] = $now;
@file_put_contents($rateFile, json_encode($attempts), LOCK_EX);

$forward = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'consent' => true,
    'consent_at' => (string)($data['consent_at'] ?? gmdate('c')),
    'registered_at' => (string)($data['registered_at'] ?? gmdate('c')),
    'event_friday' => (string)($data['event_friday'] ?? ''),
    'event_saturday' => (string)($data['event_saturday'] ?? ''),
    'event_id' => $eventId,
    'utm_source' => trim((string)($data['utm_source'] ?? '')),
    'utm_medium' => trim((string)($data['utm_medium'] ?? '')),
    'utm_campaign' => trim((string)($data['utm_campaign'] ?? '')),
    'utm_content' => trim((string)($data['utm_content'] ?? '')),
    'utm_term' => trim((string)($data['utm_term'] ?? '')),
    'fbclid' => trim((string)($data['fbclid'] ?? '')),
    'fbc' => trim((string)($data['fbc'] ?? '')),
    'fbp' => trim((string)($data['fbp'] ?? '')),
    'ttclid' => trim((string)($data['ttclid'] ?? '')),
    'ttp' => trim((string)($data['ttp'] ?? '')),
    'landing_page' => trim((string)($data['landing_page'] ?? '')),
    'referrer' => trim((string)($data['referrer'] ?? '')),
    'client_ip_address' => $clientIp,
    'client_user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    'lead_status' => 'registered',
];

$webhookUrl = trim((string)($config['make_webhook_url'] ?? ''));
if ($webhookUrl === '' || !str_starts_with($webhookUrl, 'https://hook.')) {
    respond(503, ['ok' => false, 'message' => 'Registration is being configured. Please try again shortly.']);
}

$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-make-apikey: ' . (string)($config['make_api_key'] ?? ''),
    ],
    CURLOPT_POSTFIELDS => json_encode($forward, JSON_UNESCAPED_SLASHES),
]);
$result = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($result === false || $status < 200 || $status >= 300) {
    error_log('Masterclass webhook failure: HTTP ' . $status . ' ' . $error);
    respond(502, ['ok' => false, 'message' => 'We could not complete your registration. Please try again.']);
}

respond(200, ['ok' => true, 'event_id' => $eventId]);
