<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';

$error = '';
$redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php');
if (preg_match('#^https?://#i', $redirect) || strpos($redirect, '..') !== false) {
    $redirect = 'index.php';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please use a valid email address.';
    } else {
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $error = 'Email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $image = 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=80';
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, profile_image) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, $image]);

            $_SESSION['user'] = [
                'id' => (int)$pdo->lastInsertId(),
                'name' => $name,
                'email' => $email,
                'profile_image' => $image,
            ];
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$pageTitle = 'Register | Atelier Menswear';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth">
    <form method="post" class="auth-card reveal">
        <h2>Create Account</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <label for="name">Full Name</label>
        <input id="name" name="name" type="text" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="6">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <button class="btn" type="submit">Register</button>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
