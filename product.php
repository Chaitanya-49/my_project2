<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_seed.php';
seedProducts($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$pageTitle = $product['name'] . ' | Atelier Menswear';
require __DIR__ . '/includes/header.php';
?>

<section class="product-detail section">
    <div class="gallery reveal">
        <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="mainProductImage" class="main-image">
    </div>

    <div class="details reveal">
        <span class="chip"><?= htmlspecialchars($product['category']) ?></span>
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <p class="price">Rs <?= number_format((float)$product['price'], 2) ?></p>
        <p><?= htmlspecialchars($product['description']) ?></p>

        <?php if (isset($product['category']) && trim(strtolower($product['category'])) === 'shoes'): ?>
            <label for="size">Select Size</label>
            <select id="size">
                <option disabled selected>Select Size</option>
                <option>6</option>
                <option>7</option>
                <option>8</option>
                <option>9</option>
                <option>10</option>
            </select>
        <?php else: ?>
            <label for="size">Select Size</label>
            <select id="size">
                <option>S</option>
                <option selected>M</option>
                <option>L</option>
                <option>XL</option>
            </select>
        <?php endif; ?>

        <div class="actions">
            <button type="button" class="btn add-cart" data-product-id="<?= (int)$product['id'] ?>">Add to Cart</button>
            <a class="btn ghost" href="cart.php">Buy Now</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
