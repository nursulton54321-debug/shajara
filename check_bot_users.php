<?php
require_once __DIR__ . '/includes/config.php';

dbConnect();

$sql = "SELECT id, tg_id, status, step, created_at
        FROM bot_users
        ORDER BY id DESC";

$res = db_query($sql);

header('Content-Type: text/plain; charset=utf-8');

if (!$res) {
    echo "Query xato ishladi.\n";
    exit;
}

if ($res->num_rows === 0) {
    echo "bot_users jadvalida hozircha ma'lumot yo'q.\n";
    exit;
}

while ($row = $res->fetch_assoc()) {
    echo "id: " . $row['id'] . "\n";
    echo "tg_id: " . $row['tg_id'] . "\n";
    echo "status: " . $row['status'] . "\n";
    echo "step: " . $row['step'] . "\n";
    echo "created_at: " . $row['created_at'] . "\n";
    echo "-----------------------------\n";
}
?>
