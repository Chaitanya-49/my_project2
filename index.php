<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_seed.php';

seedProducts($pdo);

$query = trim($_GET['q'] ?? '');
$params = [];
$sql = 'SELECT * FROM products';
if ($query !== '') {
    $sql .= ' WHERE name LIKE :q OR category LIKE :q OR description LIKE :q';
    $params[':q'] = '%' . $query . '%';
}
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Group products by category for display
$productsByCategory = [];
foreach ($products as $p) {
    $cat = $p['category'] ?? 'Uncategorized';
    if (!isset($productsByCategory[$cat])) $productsByCategory[$cat] = [];
    $productsByCategory[$cat][] = $p;
}

$pageTitle = 'Atelier Menswear | Premium Men\'s Fashion';
require __DIR__ . '/includes/header.php';
?>

<section class="hero" id="heroSlider">
    <article class="slide active" style="background-image:url('https://images.unsplash.com/photo-1516826957135-700dedea698c?auto=format&fit=crop&w=1700&q=80')">
        <div class="slide-content">
            <p>Star Product</p>
            <h1>Monochrome Tailored Essentials</h1>
            <p>Sharp silhouettes with premium fabrics for confident daily style.</p>
            <a href="#products" class="btn">Shop Now</a>
        </div>
    </article>
    <article class="slide" style="background-image:url('https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=1700&q=80')">
        <div class="slide-content">
            <p>Upcoming Collection</p>
            <h1>Spring City Layering</h1>
            <p>Lightweight outerwear and breathable pieces launching soon.</p>
            <a href="#products" class="btn">Preview Drop</a>
        </div>
    </article>
    <article class="slide" style="background-image:url('https://images.unsplash.com/photo-1488161628813-04466f872be2?auto=format&fit=crop&w=1700&q=80')">
        <div class="slide-content">
            <p>Best Seller</p>
            <h1>Performance Knit Footwear</h1>
            <p>Engineered comfort and a clean profile for modern movement.</p>
            <a href="#products" class="btn">Shop Best Seller</a>
        </div>
    </article>
    <article>
        
</section>

<section class="products section" id="products">
    <div class="section-head">
        <h2>Shop Categories</h2>
        <?php if ($query !== ''): ?>
            <p>Results for "<?= htmlspecialchars($query) ?>"</p>
        <?php else: ?>
            <p>T-Shirts, Shirts, Jeans, Jackets, Hoodies, Shoes</p>
        <?php endif; ?>
    </div>

    <?php if (!$products): ?>
        <p class="empty">No products found.</p>
    <?php else: ?>
        <?php foreach ($productsByCategory as $category => $items): ?>
            <div class="category-block">
                <h3 class="category-title"><?= htmlspecialchars($category) ?></h3>
                <div class="product-grid">
                    <?php foreach ($items as $product): ?>
                        <article class="product-card reveal">
                            <a href="product.php?id=<?= (int)$product['id'] ?>" class="product-image-wrap">
                                <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                            </a>
                            <div class="product-info">
                                <span class="chip"><?= htmlspecialchars($product['category']) ?></span>
                                <h3><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="price">Rs <?= number_format((float)$product['price'], 2) ?></p>
                                <div class="actions">
                                    <a class="btn ghost" href="product.php?id=<?= (int)$product['id'] ?>">View</a>
                                    <button type="button" class="btn add-cart" data-product-id="<?= (int)$product['id'] ?>">Add to Cart</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
