<?php
// Чтение списка сайтов из файла monitors.json
$monitors = json_decode(file_get_contents('monitors.json'), true);

// Создание или чтение файла results.json
if (file_exists('results.json')) {
    $results = json_decode(file_get_contents('results.json'), true);
} else {
    $results = [];
}

// Функция для отправки сообщения в Telegram
function sendTelegramMessage($message)
{
    $telegramToken = 'TOKEN';
    $chatId = 123456789;

    $url = "https://api.telegram.org/bot$telegramToken/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => http_build_query($params),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        // Если произошла ошибка при отправке запроса, выводим сообщение об ошибке
        echo "Ошибка отправки сообщения в Telegram" . PHP_EOL;
    }
}

// Проверка доступности сайтов
foreach ($monitors as $monitor) {
    $name = $monitor['name'];
    $url = $monitor['url'];

    $start = microtime(true);
    $headers = get_headers($url);
    $end = microtime(true);

    if ($headers !== false) {
        $statusCode = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
                $statusCode = intval($matches[1]);
                break;
            }
        }

        $responseTime = round(($end - $start) * 1000);

        // Проверяем был ли ранее результат в results.json
        if (isset($results[$name])) {
            $prevStatusCode = $results[$name]['statusCode'];

            // Если код ответа изменился, отправляем сообщение в Telegram
            if ($statusCode != $prevStatusCode) {
                $message = "Статус сайта $name изменился на $statusCode";
                sendTelegramMessage($message);
            }
        }

        // Если код ответа отличается от кода 200, отправляем сообщение в Telegram
        if ($statusCode != 200) {
            $message = "Сайт $name вернул код ответа $statusCode";
            sendTelegramMessage($message);
        }

        // Записываем текущий результат в results.json
        $results[$name] = [
            'statusCode' => $statusCode,
            'responseTime' => $responseTime,
        ];
    } else {
        // Если произошла ошибка при отправке запроса, отправляем сообщение в Telegram
        $message = "Не удалось получить доступ к сайту $name: " . error_get_last()['message'];
        sendTelegramMessage($message);
    }
}

// Сохранение результатов в файл results.json
file_put_contents('results.json', json_encode($results, JSON_PRETTY_PRINT));
