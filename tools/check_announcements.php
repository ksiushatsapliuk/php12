<?php
// Консольна перевірка API: створює тільки власне оголошення і прибирає його.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "Увімкніть розширення curl для PHP CLI.\n");
    exit(1);
}

date_default_timezone_set('Europe/Kyiv');
$options = getopt('', ['url:', 'output:']);
$base = rtrim($options['url'] ?? 'http://localhost:8000', '/');
$client = curl_init();
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
$checks = [];
$id = null;
$token = '';

function send(string $path, string $method = 'GET', ?array $data = null, ?string $token = null): array
{
    global $client, $base;
    curl_setopt_array($client, [CURLOPT_URL => $base . '/' . $path, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $data === null ? null : http_build_query($data),
        CURLOPT_HTTPHEADER => $token === null ? [] : ['X-CSRF-Token: ' . $token]]);
    $body = curl_exec($client);

    if ($body === false) throw new RuntimeException(curl_error($client));

    return ['status' => curl_getinfo($client, CURLINFO_RESPONSE_CODE), 'body' => $body,
        'json' => json_decode($body, true)];
}

function verify(string $name, bool $passed, array $details = []): void
{
    global $checks;
    $checks[] = ['test' => $name, 'passed' => $passed] + $details;

    if (!$passed) throw new RuntimeException('Не пройдено: ' . $name);
}

try {
    $page = send('announcements.php');
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page['body'], $match);
    $token = $match[1] ?? '';
    verify('page_and_session', $page['status'] === 200 && strlen($token) === 64);
    $path = 'api.php?resource=announcements';
    $initial = send($path);
    verify('list_available', $initial['status'] === 200 && is_array($initial['json']['data'] ?? null));
    $data = ['title' => 'Перевірка ' . bin2hex(random_bytes(5)), 'body' => 'Тестовий запис для перевірки API.'];

    foreach (['missing_token' => null, 'wrong_token' => 'wrong'] as $label => $badToken) {
        $response = send($path, 'POST', $data, $badToken);
        verify($label, $response['status'] === 403 && send($path)['body'] === $initial['body'], ['expected_http' => 403]);
    }

    foreach (['blank_title' => ['title' => '', 'body' => 'text'],
        'missing_body' => ['title' => 'text'], 'long_title' => ['title' => str_repeat('x', 161), 'body' => 'text']] as $label => $invalid) {
        $response = send($path, 'POST', $invalid, $token);
        verify($label, $response['status'] === 400 && send($path)['body'] === $initial['body'], ['expected_http' => 400]);
    }

    $response = send($path, 'POST', $data, $token);
    $id = $response['json']['data']['id'] ?? null;
    verify('create_with_token', $response['status'] === 201 && is_int($id));
    $rows = send($path)['json']['data'];
    verify('new_record_first', $rows[0]['id'] === $id && $rows[0]['title'] === $data['title']);
    verify('server_timestamp', isset($rows[0]['created_at']) && strtotime($rows[0]['created_at']) !== false);
    verify('get_delete_rejected', send($path . '&action=delete&id=' . $id)['status'] === 405);
    verify('delete_without_token_rejected', send($path . '&action=delete', 'POST', ['id' => $id])['status'] === 403);
    verify('record_preserved', send($path)['json']['data'][0]['id'] === $id);
    verify('delete_with_token', send($path . '&action=delete', 'POST', ['id' => $id], $token)['status'] === 200);
    $id = null;
    verify('original_list_restored', send($path)['body'] === $initial['body']);
} catch (Throwable $error) {
    $checks[] = ['test' => 'execution', 'passed' => false, 'message' => $error->getMessage()];
} finally {
    if ($id !== null) send('api.php?resource=announcements&action=delete', 'POST', ['id' => $id], $token);
}

$failed = count(array_filter($checks, fn($row) => !$row['passed']));
$report = ['tested_at' => date(DATE_ATOM), 'url' => $base, 'total' => count($checks), 'failed' => $failed, 'checks' => $checks];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;

if (isset($options['output'])) file_put_contents($options['output'], $json);

echo $json;
exit($failed ? 1 : 0);
