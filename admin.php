<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';

// Change these credentials after deployment.
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123';
const ADMIN_SESSION_KEY = 'is_admin';

function adminIsLoggedIn(): bool
{
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function createImagePath(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF files are allowed.');
    }

    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $baseName = trim((string)$baseName, '-');
    if ($baseName === '') {
        $baseName = 'product';
    }

    return 'images/products/' . $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
}

function deleteLocalProductImage(string $imagePath): void
{
    if (strpos($imagePath, 'images/products/') !== 0) {
        return;
    }

    $absolute = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

$error = '';
$success = '';

if (isset($_POST['admin_action']) && $_POST['admin_action'] === 'logout') {
    unset($_SESSION[ADMIN_SESSION_KEY]);
    header('Location: admin.php');
    exit;
}

if (!adminIsLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['admin_action'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (hash_equals(ADMIN_USERNAME, $username) && hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION[ADMIN_SESSION_KEY] = true;
        header('Location: admin.php');
        exit;
    }
    $error = 'Invalid admin username or password.';
}

if (adminIsLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';

    if ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rawPrice = trim($_POST['price'] ?? '');
        $price = filter_var($rawPrice, FILTER_VALIDATE_FLOAT);
        $file = $_FILES['image'] ?? null;

        if ($name === '' || $category === '' || $description === '' || $price === false || $price < 0 || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'All fields are required and image upload must be valid.';
        } else {
            try {
                $relativePath = createImagePath((string)$file['name']);
                $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                if (!move_uploaded_file((string)$file['tmp_name'], $absolutePath)) {
                    throw new RuntimeException('Image upload failed.');
                }

                $stmt = $pdo->prepare('INSERT INTO products (name, price, image, category, description) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, number_format((float)$price, 2, '.', ''), $relativePath, $category, $description]);
                $success = 'Product added successfully.';
            } catch (Throwable $t) {
                $error = $t->getMessage();
            }
        }
    }

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Invalid product ID for delete.';
        } else {
            $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            $del = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $del->execute([$id]);

            if ($del->rowCount() > 0) {
                if ($existing && !empty($existing['image'])) {
                    deleteLocalProductImage((string)$existing['image']);
                }
                $success = 'Product deleted successfully.';
            } else {
                $error = 'Product not found.';
            }
        }
    }

    if ($action === 'change_image') {
        $id = (int)($_POST['id'] ?? 0);
        $file = $_FILES['new_image'] ?? null;

        if ($id <= 0 || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Choose a valid product and image file.';
        } else {
            $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                $error = 'Product not found.';
            } else {
                try {
                    $relativePath = createImagePath((string)$file['name']);
                    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                    if (!move_uploaded_file((string)$file['tmp_name'], $absolutePath)) {
                        throw new RuntimeException('Image upload failed.');
                    }

                    $update = $pdo->prepare('UPDATE products SET image = ? WHERE id = ?');
                    $update->execute([$relativePath, $id]);

                    if (!empty($existing['image'])) {
                        deleteLocalProductImage((string)$existing['image']);
                    }
                    $success = 'Product image updated successfully.';
                } catch (Throwable $t) {
                    $error = $t->getMessage();
                }
            }
        }
    }
}

$products = [];
if (adminIsLoggedIn()) {
    $stmt = $pdo->query('SELECT id, name, price, category, image, created_at FROM products ORDER BY id DESC');
    $products = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Product Manager</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="section">
    <?php if (!adminIsLoggedIn()): ?>
        <div class="admin-shell">
            <h1>Admin Login</h1>
            <p class="muted-note">Use one admin login to manage products.</p>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post" class="admin-form">
                <input type="hidden" name="admin_action" value="login">
                <label for="username">Username</label>
                <input id="username" name="username" required>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
                <button class="btn" type="submit">Login</button>
            </form>
        </div>
    <?php else: ?>
        <div class="admin-shell">
            <div class="admin-topbar">
                <h1>Admin Product Manager</h1>
                <form method="post">
                    <input type="hidden" name="admin_action" value="logout">
                    <button class="btn danger" type="submit">Logout</button>
                </form>
            </div>

            <?php if ($success): ?><p class="success-msg"><?= htmlspecialchars($success) ?></p><?php endif; ?>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

            <section class="admin-grid">
                <div class="admin-card">
                    <h2>Add Product</h2>
                    <form method="post" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="admin_action" value="add_product">
                        <label for="name">Name</label>
                        <input id="name" name="name" required>
                        <label for="category">Category</label>
                        <input id="category" name="category" placeholder="T-Shirts / Jeans / Shoes" required>
                        <label for="price">Price</label>
                        <input id="price" name="price" type="number" step="0.01" min="0" required>
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required></textarea>
                        <label for="image">Product Image</label>
                        <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif" required>
                        <button class="btn" type="submit">Add Product</button>
                    </form>
                </div>

                <div class="admin-card">
                    <h2>Products</h2>
                    <?php if (!$products): ?>
                        <p class="muted-note">No products available.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= (int)$product['id'] ?></td>
                                        <td><img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="admin-thumb"></td>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= htmlspecialchars($product['category']) ?></td>
                                        <td>Rs <?= number_format((float)$product['price'], 2) ?></td>
                                        <td>
                                            <form method="post" enctype="multipart/form-data" class="admin-inline-form">
                                                <input type="hidden" name="admin_action" value="change_image">
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <input name="new_image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif" required>
                                                <button class="btn tiny" type="submit">Change Photo</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Delete this product?')">
                                                <input type="hidden" name="admin_action" value="delete_product">
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <button class="btn tiny danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
