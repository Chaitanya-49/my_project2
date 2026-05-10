<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';

$error = '';
$redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php');
// sanitize redirect to a local path
if (preg_match('#^https?://#i', $redirect) || strpos($redirect, '..') !== false) {
    $redirect = 'index.php';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $error = 'Invalid email or password.';
    } else {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'profile_image' => $user['profile_image'],
        ];
        header('Location: ' . $redirect);
        exit;
    }
}

$pageTitle = 'Login | Atelier Menswear';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth">
    <form method="post" class="auth-card reveal">
        <h2>Welcome Back</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <button class="btn" type="submit">Login</button>
        <p>New here? <a href="register.php">Create account</a></p>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
