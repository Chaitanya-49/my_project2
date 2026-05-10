<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Product ID is required. Use ?id=PRODUCT_ID');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['price'] ?? '';
    $price = filter_var($raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    if ($price === '' || !is_numeric($price)) {
        $error = 'Please provide a valid numeric price.';
    } else {
        $price = number_format((float)$price, 2, '.', '');
        $stmt = $pdo->prepare('UPDATE products SET price = ? WHERE id = ?');
        $stmt->execute([$price, $id]);
        header('Location: admin_edit_product.php?id=' . $id . '&updated=1');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT id, name, price, category FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    exit('Product not found');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Product - <?= htmlspecialchars($product['name']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-wrap { max-width:720px; margin:40px auto; padding:18px; background:#fff; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.08); }
        .field { margin-bottom:12px; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        input[type="number"] { width:160px; padding:8px; border-radius:8px; border:1px solid #ddd; }
        .muted { color:#666; font-size:0.95rem; }
    </style>
</head>
<body>
    <main class="section">
        <div class="admin-wrap">
            <h2>Edit Product</h2>
            <p class="muted"><?= htmlspecialchars($product['name']) ?> — Category: <?= htmlspecialchars($product['category']) ?></p>
            <?php if (!empty($_GET['updated'])): ?>
                <p style="color:green">Price updated successfully.</p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p style="color:crimson"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                <div class="field">
                    <label for="price">Price (USD)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="<?= number_format((float)$product['price'], 2, '.', '') ?>">
                </div>
                <div class="field">
                    <button class="btn" type="submit">Save Price</button>
                    <a class="btn ghost" href="index.php">Back to site</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
