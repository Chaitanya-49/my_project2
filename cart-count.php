<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

$count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $count += (int)$qty;
    }
}

echo json_encode(['count' => $count]);
