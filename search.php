<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_seed.php';
seedProducts($pdo);

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, category, price, image FROM products WHERE name LIKE :q OR category LIKE :q ORDER BY id DESC LIMIT 8');
$stmt->execute([':q' => '%' . $q . '%']);
echo json_encode($stmt->fetchAll());
