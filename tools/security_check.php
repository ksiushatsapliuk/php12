<?php
// Перевірка через HTTP. Створює тільки власні тестові записи та прибирає їх у finally.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Kyiv');

$options = getopt('', ['url:', 'output:']);
$base = rtrim($options['url'] ?? 'http://127.0.0.1:8000', '/');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
$checks = [];
$created = [];
$token = '';
$prefix = 'Перевірка08_' . bin2hex(random_bytes(5));

function http(string $path, string $method = 'GET', ?array $data = null, bool $json = false, ?string $csrf = null): array
{
    global $base, $client;
    $headers = [];

    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    if ($json) $headers[] = 'Content-Type: application/json';

    curl_setopt_array($client, [CURLOPT_URL => $base . '/' . $path, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $data === null ? null : ($json ? json_encode($data, JSON_UNESCAPED_UNICODE) : http_build_query($data)),
        CURLOPT_HTTPHEADER => $headers]);

    $body = curl_exec($client);

    if ($body === false) throw new RuntimeException(curl_error($client));

    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE), 'body' => $body,
        'json' => json_decode($body, true)];
}

function check(string $name, bool $ok, array $details = []): void
{
    global $checks;
    $checks[] = ['test' => $name, 'passed' => $ok] + $details;

    if (!$ok) throw new RuntimeException('Не пройдено: ' . $name);
}

function snapshot(): string
{
    $response = http('api.php?resource=plants');

    if ($response['status'] !== 200) throw new RuntimeException('Список недоступний. Перевірте БД та адресу.');

    return hash('sha256', $response['body']);
}

function rejected(string $name, string $path, string $method, ?array $data, bool $json, ?string $csrf, int $expected): void
{
    $before = snapshot();
    $response = http($path, $method, $data, $json, $csrf);
    check($name, $response['status'] === $expected && snapshot() === $before,
        ['expected_http' => $expected, 'actual_http' => $response['status'], 'data_unchanged' => snapshot() === $before]);
}

try {
    $page = http('index.php');
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page['body'], $match);
    $token = $match[1] ?? '';
    check('session_token', $page['status'] === 200 && strlen($token) === 64);
    $initial = snapshot();
    $data = ['name' => $prefix, 'watering_interval_days' => 7,
        'last_watered_at' => date('Y-m-d', strtotime('-10 days')), 'is_succulent' => false];

    $response = http('api.php?resource=plants', 'POST', $data, true, $token);
    $id = $response['json']['data']['id'] ?? 0;
    if ($id) $created[] = $id;
    check('create_json_with_token', $response['status'] === 201 && $id > 0);

    // Токен попередньої сесії не підходить після переходу до нової сесії.
    curl_setopt($client, CURLOPT_COOKIELIST, 'ALL');
    $page = http('index.php');
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page['body'], $match);
    $oldToken = $token;
    $token = $match[1] ?? '';
    check('new_session_has_other_token', strlen($token) === 64 && $token !== $oldToken);

    $routes = [
        'create_json' => ['api.php?resource=plants', $data, true],
        'create_form' => ['api.php?resource=plants', $data, false],
        'water_json' => ['api.php?resource=plants&action=water', ['id' => $id], true],
        'water_form' => ['api.php?resource=plants&action=water', ['id' => $id], false],
        'edit' => ['edit.php?id=' . $id, $data, false],
        'delete' => ['delete.php', ['id' => $id], false],
    ];

    foreach ($routes as $label => [$path, $values, $json]) {
        foreach (['missing' => null, 'wrong' => str_repeat('0', 64), 'old_session' => $oldToken] as $kind => $badToken) {
            rejected('csrf_' . $label . '_' . $kind, $path, 'POST', $values, $json, $badToken, 403);
        }
    }

    foreach (['missing_name' => array_diff_key($data, ['name' => true]),
        'blank_name' => array_replace($data, ['name' => '   ']),
        'array_name' => array_replace($data, ['name' => ['bad']]),
        'long_name' => array_replace($data, ['name' => str_repeat('а', 256)]),
        'negative_interval' => array_replace($data, ['watering_interval_days' => -1]),
        'zero_interval' => array_replace($data, ['watering_interval_days' => 0]),
        'large_interval' => array_replace($data, ['watering_interval_days' => 3651]),
        'fraction_interval' => array_replace($data, ['watering_interval_days' => 1.5]),
        'invalid_date' => array_replace($data, ['last_watered_at' => '2026-02-30']),
        'future_date' => array_replace($data, ['last_watered_at' => '9999-01-01']),
        'invalid_flag' => array_replace($data, ['is_succulent' => ['bad']]),
        'blank_light' => array_replace($data, ['sunlight_needs' => ''])] as $label => $invalid) {
        rejected('validation_' . $label, 'api.php?resource=plants', 'POST', $invalid, true, $token, 400);
    }

    rejected('edit_server_validation', 'edit.php?id=' . $id, 'POST', ['name' => ''], false, $token, 422);
    rejected('delete_invalid_id', 'delete.php', 'POST', ['id' => '1 OR 1=1'], false, $token, 422);

    foreach ([$id, $id + 1, $id + 2] as $candidate) {
        rejected('get_delete_' . $candidate, 'delete.php?id=' . $candidate, 'GET', null, false, null, 405);
    }

    rejected('get_water', 'api.php?resource=plants&action=water&id=' . $id, 'GET', null, false, null, 405);
    rejected('get_edit_does_not_save', 'edit.php?id=' . $id . '&name=Changed', 'GET', null, false, null, 200);

    foreach (['abc', '-1', '1%20OR%201=1', '%5B1%5D'] as $badId) {
        $response = http('edit.php?id=' . $badId);
        check('edit_invalid_id_' . $badId, $response['status'] === 400);
    }

    $response = http('api.php?resource=plants&q=' . rawurlencode("' OR '1'='1"));
    check('sqli_search', $response['status'] === 200 && $response['json']['data'] === [], ['payload' => "' OR '1'='1"]);
    $response = http('api.php?resource=plants&interval=' . rawurlencode('7 OR 1=1'));
    check('sqli_interval', $response['status'] === 400);

    foreach (['<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '"><svg onload=alert(1)>'] as $payload) {
        $response = http('api.php?resource=plants', 'POST', array_replace($data, ['name' => $payload]), true, $token);
        $xssId = $response['json']['data']['id'] ?? 0;
        if ($xssId) $created[] = $xssId;
        check('xss_payload_saved_' . $xssId, $response['status'] === 201 && $response['json']['data']['name'] === $payload);
        $page = http('edit.php?id=' . $xssId);
        check('xss_edit_escaped_' . $xssId, str_contains($page['body'], htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'))
            && !str_contains($page['body'], 'value="' . $payload . '"'), ['payload' => $payload]);
    }

    $response = http('api.php?resource=plants&action=water', 'POST', ['id' => $id], true, $token);
    check('markWatered_with_token', $response['status'] === 200 && $response['json']['data']['last_watered_at'] === date('Y-m-d'));

    $response = http('edit.php?id=' . $id, 'POST', array_replace($data, ['name' => $prefix . '_edited', 'csrf' => $token]));
    $record = http('api.php?resource=plants&id=' . $id);
    check('edit_form_with_token', $response['status'] === 302 && $record['json']['data']['name'] === $prefix . '_edited');

    $response = http('api.php?resource=plants', 'POST', array_replace($data, ['csrf' => $token]));
    $formId = $response['json']['data']['id'] ?? 0;
    if ($formId) $created[] = $formId;
    check('create_form_with_hidden_token', $response['status'] === 201 && $formId > 0);

    foreach (['tools/install.php', 'tools/benchmark.php', 'tools/security_check.php', 'benchmarks/original06.php?resource=plants'] as $path) {
        check('http_guard_' . $path, http($path)['status'] === 404);
    }
} catch (Throwable $error) {
    $checks[] = ['test' => 'execution', 'passed' => false, 'message' => $error->getMessage()];
} finally {
    foreach ($created as $id) {
        $response = http('delete.php', 'POST', ['id' => $id, 'csrf' => $token]);
        $checks[] = ['test' => 'cleanup_' . $id, 'passed' => $response['status'] === 303];
    }

    if (isset($initial)) $checks[] = ['test' => 'original_data_restored', 'passed' => snapshot() === $initial];
}

$failed = count(array_filter($checks, fn($check) => !$check['passed']));
$report = ['tested_at' => date(DATE_ATOM), 'url' => $base, 'php' => PHP_VERSION,
    'total' => count($checks), 'passed' => count($checks) - $failed, 'failed' => $failed, 'checks' => $checks];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;

if (isset($options['output'])) file_put_contents($options['output'], $json);

echo $json;
exit($failed ? 1 : 0);
