<?php
declare(strict_types=1);

// CLI-only timer for the consolidated Make intake. Enable only after migration.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing server configuration.\n");
    exit(1);
}
$config = require $configPath;
$url = trim((string)($config['make_webhook_url'] ?? ''));
$key = trim((string)($config['make_api_key'] ?? ''));
if (!str_starts_with($url, 'https://hook.') || $key === '') {
    fwrite(STDERR, "Missing authenticated Make webhook configuration.\n");
    exit(1);
}

$now = new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
$slots = [4 => [8, 14, 20], 5 => [2, 8, 14]];
$day = (int)$now->format('N');
$hour = (int)$now->format('G');
if (!in_array($hour, $slots[$day] ?? [], true)) {
    exit(0);
}

$slot = $now->format('Y-m-d-H');
$statePath = sys_get_temp_dir() . '/imm-masterclass-reminder-slots.json';
$stateFile = fopen($statePath, 'c+');
if ($stateFile === false || !flock($stateFile, LOCK_EX)) {
    fwrite(STDERR, "Could not lock reminder state.\n");
    exit(1);
}
$sentSlots = json_decode(stream_get_contents($stateFile), true) ?: [];
if (isset($sentSlots[$slot])) {
    fclose($stateFile);
    exit(0);
}

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-make-apikey: ' . $key],
    CURLOPT_POSTFIELDS => json_encode(['lead_status' => 'reminder_tick']),
]);
$response = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
if ($response === false || $status < 200 || $status >= 300) {
    fclose($stateFile);
    fwrite(STDERR, "Reminder request failed (HTTP {$status}).\n");
    exit(1);
}

$sentSlots[$slot] = $now->format(DATE_ATOM);
$sentSlots = array_slice($sentSlots, -36, null, true);
rewind($stateFile);
ftruncate($stateFile, 0);
fwrite($stateFile, json_encode($sentSlots));
flock($stateFile, LOCK_UN);
fclose($stateFile);
echo "Reminder request accepted for {$slot}.\n";
