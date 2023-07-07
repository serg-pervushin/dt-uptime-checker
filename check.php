<?php

// Функция для отправки сообщения в Telegram
function sendTelegramMessage($message)
{
    $telegram_token = 'TOKEN';
    $chatId = 1234567890;

    $url = "https://api.telegram.org/bot$telegram_token/sendMessage";
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

// функция проверки сертификата
function checkCertificate($name, $url)
{

    $url_components = parse_url($url);

    // Извлекаем только доменное имя из разобранных компонентов URL
    $url = isset($url_components['host']) ? $url_components['host'] : '';

    // Устанавливаем соединение с сервером
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'capture_peer_cert' => true,
        ]
    ]);

    $stream = stream_socket_client("ssl://{$url}:443", $error, $errorString, 30, STREAM_CLIENT_CONNECT, $context);

    if (!$stream) {
        // произошла ошибка при соединении с сайтом
        $message = "Не удалось получить информацию о сертификате для сайта $name: $errorString ($error)";
        sendTelegramMessage($message);
    } else {

        $cert_data = stream_context_get_params($stream);
        $cert_info = openssl_x509_parse($cert_data['options']['ssl']['peer_certificate']);

        if (!$cert_info) {
            // произошла ошибка при разборе данных сертификата
            $message = "Ошибка при разборе данных сертификата для сайта $name";
            sendTelegramMessage($message);
        } else {
            $expiry_date = $cert_info['validTo_time_t'];
            $days_until_expiry = ceil(($expiry_date - time()) / (60 * 60 * 24)); // Разница в днях между сегодняшней датой и датой истечения сертификата

            // Отправляем уведомление, если сертификат истекает через 10, 5, 3 или 1 день
            if ($days_until_expiry <= 10 || $days_until_expiry <= 5 || $days_until_expiry <= 3 || $days_until_expiry <= 1) {
                $message = "Сертификат для сайта $name истекает через $days_until_expiry дней";
                sendTelegramMessage($message);
            }
        }
    }

    fclose($stream);
}

// Проверка доступности сайтов
function checkWebsite($site)
{
    $name = $site['name'];
    $url = $site['url'];

    $start = microtime(true);
    $headers = get_headers($url);
    $end = microtime(true);

    if ($headers !== false) {
        $status_code = 0;
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
                $status_code = intval($matches[1]);
                break;
            }
        }

        $response_time = round(($end - $start) * 1000);

        // Проверяем был ли ранее результат в results.json
        if (isset($results[$name])) {
            $prev_status_code = $results[$name]['status_code'];

            // Если код ответа изменился, отправляем сообщение в Telegram
            if ($status_code != $prev_status_code) {
                $message = "Статус сайта $name изменился на $status_code";
                sendTelegramMessage($message);
            }
        }

        // Если код ответа отличается от кода 200, отправляем сообщение в Telegram
        if ($status_code != 200) {
            $message = "Сайт $name вернул код ответа $status_code";
            sendTelegramMessage($message);
        }

        // Записываем текущий результат в results.json
        $results[$name] = [
            'status_code' => $status_code,
            'response_time' => $response_time,
        ];
    } else {
        // Если произошла ошибка при отправке запроса, отправляем сообщение в Telegram
        $message = "Не удалось получить доступ к сайту $name: " . error_get_last()['message'];
        sendTelegramMessage($message);
    }

    // чекаем сертификат
    checkCertificate($name, $url);
}

// Чтение списка сайтов из файла sites.json
$sites = json_decode(file_get_contents('json/sites.json'), true);

// Создание или чтение файла results.json
if (file_exists('json/results.json')) {
    $results = json_decode(file_get_contents('json/results.json'), true);
} else {
    $results = [];
}

// проверяем
foreach ($sites as $site) {
    checkWebsite($site);
}

// Сохранение результатов в файл results.json
file_put_contents('json/results.json', json_encode($results, JSON_PRETTY_PRINT));
