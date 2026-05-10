<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(40) NOT NULL UNIQUE,
        user_id INT NULL,
        customer_name VARCHAR(120) NOT NULL,
        customer_email VARCHAR(190) NOT NULL,
        customer_phone VARCHAR(30) NOT NULL,
        delivery_address TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(30) NOT NULL,
        payment_status VARCHAR(30) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(40) NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(190) NOT NULL,
        product_image VARCHAR(255) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL,
        line_total DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (order_code),
        INDEX (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$hasUserId = $pdo->query("SHOW COLUMNS FROM orders LIKE 'user_id'")->fetch();
if (!$hasUserId) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER order_code, ADD INDEX (user_id)");
}

$purchasedItems = [];
$stmt = $pdo->prepare(
    "SELECT
        oi.order_code,
        oi.product_id,
        oi.product_name,
        oi.product_image,
        oi.unit_price,
        oi.quantity,
        oi.line_total,
        o.payment_status,
        o.created_at AS ordered_at
    FROM order_items oi
    INNER JOIN orders o ON o.order_code = oi.order_code
    WHERE o.user_id = :user_id
    ORDER BY o.created_at DESC, oi.id DESC"
);
$stmt->execute([':user_id' => $userId]);
$purchasedItems = $stmt->fetchAll();

$pageTitle = 'Profile | Atelier Menswear';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="auth-card reveal">
        <h2>My Account</h2>
        <img src="<?= htmlspecialchars($_SESSION['user']['profile_image']) ?>" alt="Profile" class="profile-lg">
        <p><strong>Name:</strong> <?= htmlspecialchars($_SESSION['user']['name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['user']['email']) ?></p>
        <a href="logout.php" class="btn danger">Logout</a>
    </div>

    <div class="purchase-history reveal">
        <h3>Bought Items</h3>
        <?php if (!$purchasedItems): ?>
            <p class="muted-note">No purchased items yet. Complete a checkout to see order history here.</p>
        <?php else: ?>
            <div class="purchase-list">
                <?php foreach ($purchasedItems as $item): ?>
                    <article class="purchase-item">
                        <img src="<?= htmlspecialchars($item['product_image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                        <div>
                            <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                            <p>Order: <?= htmlspecialchars($item['order_code']) ?></p>
                            <p>Qty: <?= (int)$item['quantity'] ?> | Unit: Rs <?= number_format((float)$item['unit_price'], 2) ?></p>
                            <p>Total: Rs <?= number_format((float)$item['line_total'], 2) ?></p>
                            <p>Status: <?= htmlspecialchars($item['payment_status']) ?> | Bought: <?= date('d M Y', strtotime((string)$item['ordered_at'])) ?></p>
                        </div>
                        <a class="btn ghost tiny" href="product.php?id=<?= (int)$item['product_id'] ?>">View Product</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
