<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_seed.php';
seedProducts($pdo);

$_SESSION['cart'] = $_SESSION['cart'] ?? [];

$items = [];
$total = 0.0;

if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, image FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $product) {
        $qty = (int)$_SESSION['cart'][(int)$product['id']];
        $line = ((float)$product['price']) * $qty;
        $total += $line;
        $items[] = ['product' => $product, 'qty' => $qty, 'line' => $line];
    }
}
?>
<?php if (!$items): ?>
    <div class="empty-cart">
        <p>Your cart is empty</p>
        <img src="https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=700&q=80" alt="Shopping illustration">
    </div>
<?php else: ?>
    <div class="cart-items">
        <?php foreach ($items as $item): ?>
            <article class="cart-item">
                <img src="<?= htmlspecialchars($item['product']['image']) ?>" alt="<?= htmlspecialchars($item['product']['name']) ?>">
                <div>
                    <h4><?= htmlspecialchars($item['product']['name']) ?></h4>
                    <p>Rs <?= number_format((float)$item['product']['price'], 2) ?> x <?= (int)$item['qty'] ?></p>
                    <p>Line: Rs <?= number_format($item['line'], 2) ?></p>
                </div>
                <form class="remove-item-form">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int)$item['product']['id'] ?>">
                    <button class="btn danger tiny" type="submit">Remove</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="cart-summary">
        <p>Total: <strong>Rs <?= number_format($total, 2) ?></strong></p>
        <a href="cart.php" class="btn">Checkout</a>
    </div>
<?php endif; ?>
