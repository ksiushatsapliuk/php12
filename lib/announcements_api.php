<?php
if (!defined('JSON_API')) {
    http_response_code(404);
    exit;
}

// Маршрут api.php?resource=announcements використовує захист форм роботи 08.
require_once __DIR__ . '/../classes/AnnouncementBoard.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = apiQuery('action');

if ($action !== null && $action !== 'delete') apiError('Невідома дія оголошення.', 404);

if (!in_array($method, ['GET', 'POST'], true) || ($action === 'delete' && $method !== 'POST')) {
    header('Allow: ' . ($action === 'delete' ? 'POST' : 'GET, POST'));
    apiError('Метод не підтримується для цієї дії.', 405);
}

if ($method === 'POST') {
    $body = apiBody();

    if ($action === 'delete') {
        $id = apiId($body['id'] ?? null);
    } else {
        try {
            $input = AnnouncementBoard::input($body);
        } catch (InvalidArgumentException $error) {
            apiError($error->getMessage(), 400);
        }
    }
}

require_once __DIR__ . '/db.php';
$board = new AnnouncementBoard($pdo);

if ($method === 'GET') apiReply($board->all());

if ($action === 'delete') {
    if (!$board->delete($id)) apiError('Оголошення не знайдено.', 404);

    apiReply(['id' => $id]);
}

apiReply($board->add($input), 201);
