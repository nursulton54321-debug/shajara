<?php
require_once __DIR__ . '/includes/config.php';

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('ADMIN_TG_ID', getenv('ADMIN_TG_ID') ?: '');

function sendTelegram($method, $data) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 30
        ]
    ];

    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);

    header('Content-Type: text/plain; charset=utf-8');

    if ($res === false) {
        echo "sendTelegram ishlamadi.\n";
        echo "BOT_TOKEN length: " . strlen(BOT_TOKEN) . "\n";
        echo "ADMIN_TG_ID: " . ADMIN_TG_ID . "\n";
        exit;
    }

    echo $res;
}

sendTelegram('sendMessage', [
    'chat_id' => ADMIN_TG_ID,
    'text' => 'Test xabar: serverdan Telegramga yuborildi'
]);
?>
