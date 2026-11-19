<?php
// Мок Telegram Bot API для автотестов: сохраняет каждое сообщение в файл и отвечает как настоящий API.
file_put_contents(__DIR__ . '/telegram.log', json_encode(['uri' => $_SERVER['REQUEST_URI'], 'post' => $_POST], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'result' => ['message_id' => random_int(1, 9999)]]);
