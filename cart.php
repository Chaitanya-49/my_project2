<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/product_seed.php';
seedProducts($pdo);

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$paymentError = '';
$paymentMethod = $_POST['payment_method'] ?? 'card';
$checkoutMeta = [];
$upiPending = $_SESSION['upi_pending'] ?? [];
$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
$userLoggedIn = !empty($_SESSION['user']);

// Mock order storage for professional checkout flow (non-real payments).
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
    "CREATE TABLE IF NOT EXISTS order_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(40) NOT NULL,
        transaction_ref VARCHAR(60) NOT NULL,
        provider_name VARCHAR(80) NOT NULL,
        method VARCHAR(30) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(30) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (order_code)
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

// Lightweight migration: add user_id for older projects that already have the orders table.
$hasUserId = $pdo->query("SHOW COLUMNS FROM orders LIKE 'user_id'")->fetch();
if (!$hasUserId) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER order_code, ADD INDEX (user_id)");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    if ($action === 'confirm_upi') {
        $upiTxnId = strtoupper(trim($_POST['upi_txn_id'] ?? ''));
        if (!$userLoggedIn) {
            $paymentError = 'Please login to confirm your UPI payment.';
        } elseif (!$upiPending || empty($upiPending['order_id'])) {
            $paymentError = 'No pending UPI payment found.';
        } elseif ($upiTxnId === '') {
            $paymentError = 'Please enter your UPI transaction ID to continue.';
        } elseif (!preg_match('/^[A-Z0-9\-]{6,40}$/', $upiTxnId)) {
            $paymentError = 'Please enter a valid UPI transaction ID.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtOrderStatus = $pdo->prepare(
                    "UPDATE orders
                     SET payment_status = 'Paid'
                     WHERE order_code = :order_code AND payment_status = 'Pending'"
                );
                $stmtOrderStatus->execute([
                    ':order_code' => $upiPending['order_id'],
                ]);

                $stmtPaymentStatus = $pdo->prepare(
                    "UPDATE order_payments
                     SET status = 'Paid', transaction_ref = :transaction_ref
                     WHERE order_code = :order_code AND status = 'Pending'"
                );
                $stmtPaymentStatus->execute([
                    ':transaction_ref' => $upiTxnId,
                    ':order_code' => $upiPending['order_id'],
                ]);

                $pdo->commit();

                $_SESSION['cart'] = [];
                $_SESSION['checkout_success'] = 'Order placed successfully after UPI confirmation.';
                $_SESSION['checkout_meta'] = [
                    'order_id' => $upiPending['order_id'],
                    'transaction_ref' => $upiTxnId,
                    'provider' => $upiPending['provider'],
                    'status' => 'Paid',
                    'amount' => (float)$upiPending['amount'],
                    'method' => 'UPI',
                    'eta' => $upiPending['eta'],
                ];
                unset($_SESSION['upi_pending']);
                header('Location: cart.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $paymentError = 'Unable to confirm UPI payment right now. Please try again.';
            }
        }
    }

    if ($action === 'add' && $id > 0) {
        $_SESSION['cart'][$id] = (int)($_SESSION['cart'][$id] ?? 0) + $qty;
    }
    if ($action === 'remove' && $id > 0) {
        unset($_SESSION['cart'][$id]);
    }
    if ($action === 'update' && $id > 0) {
        $_SESSION['cart'][$id] = $qty;
    }
    if ($action === 'pay') {
        // Require login before proceeding with real/ sandbox checkout
        if (!$userLoggedIn) {
            $paymentError = 'Please login or register to complete payment.';
            // Prevent further processing of payment when not authenticated
            $action = '';
        }

        if ($action === '') {
            // skip the rest of the pay-handler when not logged in
        } else {
        $paymentMethod = $_POST['payment_method'] ?? 'card';
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = preg_replace('/\D+/', '', $_POST['customer_phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $cardHolder = trim($_POST['card_holder'] ?? '');
        $cardNumber = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvv = preg_replace('/\D+/', '', $_POST['card_cvv'] ?? '');
        $upiId = trim($_POST['upi_id'] ?? '');
        $bankCode = trim($_POST['bank_code'] ?? '');

        $ids = array_map('intval', array_keys($_SESSION['cart']));
        $orderTotal = 0.0;
        $orderLines = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, image, price FROM products WHERE id IN ($in)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) {
                $qty = (int)($_SESSION['cart'][(int)$row['id']] ?? 0);
                if ($qty < 1) {
                    continue;
                }
                $lineTotal = ((float)$row['price']) * $qty;
                $orderTotal += $lineTotal;
                $orderLines[] = [
                    'product_id' => (int)$row['id'],
                    'product_name' => (string)$row['name'],
                    'product_image' => (string)$row['image'],
                    'unit_price' => (float)$row['price'],
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                ];
            }
        }

        if (!$_SESSION['cart']) {
            $paymentError = 'Your cart is empty.';
        } elseif (!$orderLines) {
            $paymentError = 'No valid items found in cart.';
        } elseif ($customerName === '' || $customerEmail === '' || $customerPhone === '' || $address === '') {
            $paymentError = 'Please fill in all contact and delivery details.';
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $paymentError = 'Please enter a valid email address.';
        } elseif (strlen($customerPhone) < 10) {
            $paymentError = 'Please enter a valid phone number.';
        } elseif (!in_array($paymentMethod, ['card', 'upi', 'netbanking', 'cod'], true)) {
            $paymentError = 'Please choose a valid payment method.';
        } elseif (
            $paymentMethod === 'card'
            && ($cardHolder === '' || strlen($cardNumber) < 16 || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry) || strlen($cardCvv) < 3)
        ) {
            $paymentError = 'Please enter valid card details.';
        } elseif ($paymentMethod === 'upi' && !preg_match('/^[a-zA-Z0-9.\-_]{2,}@[a-zA-Z]{2,}$/', $upiId)) {
            $paymentError = 'Please enter a valid UPI ID.';
        } elseif ($paymentMethod === 'netbanking' && $bankCode === '') {
            $paymentError = 'Please select your bank for net banking.';
        } else {
            $orderId = 'ORD' . date('YmdHis') . random_int(100, 999);
            $txnRef = 'TXN' . strtoupper(bin2hex(random_bytes(4))) . random_int(1000, 9999);
            $provider = match ($paymentMethod) {
                'card' => 'Visa/Mastercard Gateway (Sandbox)',
                'upi' => 'UPI Switch (Sandbox)',
                'netbanking' => 'Banking Hub (Sandbox)',
                default => 'Cash Collection on Delivery'
            };
            $paymentStatus = in_array($paymentMethod, ['upi', 'cod'], true) ? 'Pending' : 'Paid';

            $pdo->beginTransaction();
            try {
                $stmtOrder = $pdo->prepare(
                    "INSERT INTO orders
                    (order_code, user_id, customer_name, customer_email, customer_phone, delivery_address, total_amount, payment_method, payment_status)
                    VALUES
                    (:order_code, :user_id, :customer_name, :customer_email, :customer_phone, :delivery_address, :total_amount, :payment_method, :payment_status)"
                );
                $stmtOrder->execute([
                    ':order_code' => $orderId,
                    ':user_id' => $currentUserId > 0 ? $currentUserId : null,
                    ':customer_name' => $customerName,
                    ':customer_email' => $customerEmail,
                    ':customer_phone' => $customerPhone,
                    ':delivery_address' => $address,
                    ':total_amount' => $orderTotal,
                    ':payment_method' => $paymentMethod,
                    ':payment_status' => $paymentStatus,
                ]);

                $stmtPayment = $pdo->prepare(
                    "INSERT INTO order_payments
                    (order_code, transaction_ref, provider_name, method, amount, status)
                    VALUES
                    (:order_code, :transaction_ref, :provider_name, :method, :amount, :status)"
                );
                $stmtPayment->execute([
                    ':order_code' => $orderId,
                    ':transaction_ref' => $txnRef,
                    ':provider_name' => $provider,
                    ':method' => $paymentMethod,
                    ':amount' => $orderTotal,
                    ':status' => $paymentStatus,
                ]);

                $stmtItem = $pdo->prepare(
                    "INSERT INTO order_items
                    (order_code, product_id, product_name, product_image, unit_price, quantity, line_total)
                    VALUES
                    (:order_code, :product_id, :product_name, :product_image, :unit_price, :quantity, :line_total)"
                );

                foreach ($orderLines as $line) {
                    $stmtItem->execute([
                        ':order_code' => $orderId,
                        ':product_id' => $line['product_id'],
                        ':product_name' => $line['product_name'],
                        ':product_image' => $line['product_image'],
                        ':unit_price' => $line['unit_price'],
                        ':quantity' => $line['quantity'],
                        ':line_total' => $line['line_total'],
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $paymentError = 'Payment processing failed in sandbox mode. Please try again.';
            }

            if ($paymentError === '') {
                if ($paymentMethod === 'upi') {
                    $_SESSION['upi_pending'] = [
                        'order_id' => $orderId,
                        'transaction_ref' => $txnRef,
                        'provider' => $provider,
                        'amount' => $orderTotal,
                        'eta' => date('D, d M', strtotime('+4 days')),
                    ];
                } else {
                    unset($_SESSION['upi_pending']);
                    $_SESSION['cart'] = [];
                    $_SESSION['checkout_success'] = "Order placed successfully in sandbox mode.";
                    $_SESSION['checkout_meta'] = [
                        'order_id' => $orderId,
                        'transaction_ref' => $txnRef,
                        'provider' => $provider,
                        'status' => $paymentStatus,
                        'amount' => $orderTotal,
                        'method' => strtoupper($paymentMethod),
                        'eta' => date('D, d M', strtotime('+4 days')),
                    ];
                }
                header('Location: cart.php');
                exit;
            }
            }
        }
    }

    if (($_POST['json'] ?? '') === '1' && $action !== 'pay') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($paymentError === '') {
        header('Location: cart.php');
        exit;
    }
}

$items = [];
$total = 0.0;
if ($_SESSION['cart']) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $qty = (int)$_SESSION['cart'][(int)$product['id']];
        $line = (float)$product['price'] * $qty;
        $total += $line;
        $items[] = ['product' => $product, 'qty' => $qty, 'line' => $line];
    }
}
$checkoutSuccess = $_SESSION['checkout_success'] ?? '';
$checkoutMeta = $_SESSION['checkout_meta'] ?? [];
unset($_SESSION['checkout_success']);
unset($_SESSION['checkout_meta']);
$upiPending = $_SESSION['upi_pending'] ?? [];

$upiReceiverId = 'chaitanyamayekar12345@okhdfcbank';
$upiReceiverName = 'Mayekar Chaitanya';
$upiDisplayAmount = isset($upiPending['amount']) ? (float)$upiPending['amount'] : $total;
$postedUpiTxnId = trim((string)($_POST['upi_txn_id'] ?? ''));
$upiPayAmount = number_format($upiDisplayAmount, 2, '.', '');
$upiIntent = sprintf(
    'upi://pay?pa=%s&pn=%s&am=%s&cu=INR',
    rawurlencode($upiReceiverId),
    rawurlencode($upiReceiverName),
    rawurlencode($upiPayAmount)
);
$upiQrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&data=' . rawurlencode($upiIntent);

$pageTitle = 'Cart | Atelier Menswear';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="section-head">
        <h2>Shopping Cart</h2>
        <p>Review your selected items before checkout.</p>
    </div>
    <?php if ($checkoutSuccess !== ''): ?>
        <div class="payment-success-card">
            <h3><?= htmlspecialchars($checkoutSuccess) ?></h3>
            <?php if ($checkoutMeta): ?>
                <p><strong>Order ID:</strong> <?= htmlspecialchars($checkoutMeta['order_id']) ?></p>
                <p><strong>Transaction Ref:</strong> <?= htmlspecialchars($checkoutMeta['transaction_ref']) ?></p>
                <p><strong>Gateway:</strong> <?= htmlspecialchars($checkoutMeta['provider']) ?></p>
                <p><strong>Payment Status:</strong> <?= htmlspecialchars($checkoutMeta['status']) ?></p>
                <p><strong>Payment Method:</strong> <?= htmlspecialchars($checkoutMeta['method']) ?></p>
                <p><strong>Amount:</strong> Rs <?= number_format((float)$checkoutMeta['amount'], 2) ?></p>
                <p><strong>Estimated Delivery:</strong> <?= htmlspecialchars($checkoutMeta['eta']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($upiPending): ?>
        <div class="payment-pending-card">
            <h3>UPI payment is pending</h3>
            <p><strong>Order ID:</strong> <?= htmlspecialchars($upiPending['order_id']) ?></p>
            <p><strong>Transaction Ref:</strong> <?= htmlspecialchars($upiPending['transaction_ref']) ?></p>
            <p><strong>Gateway:</strong> <?= htmlspecialchars($upiPending['provider']) ?></p>
            <p><strong>Payment Status:</strong> Pending</p>
            <p><strong>Amount:</strong> Rs <?= number_format((float)$upiPending['amount'], 2) ?></p>
            <p class="muted-note">Complete payment by QR, then enter transaction ID to continue.</p>
            <form method="post" class="upi-confirm-form">
                <input type="hidden" name="action" value="confirm_upi">
                <label for="upi_txn_id">UPI Transaction ID</label>
                <input id="upi_txn_id" name="upi_txn_id" type="text" required placeholder="Example: T2409123ABCD" value="<?= htmlspecialchars($postedUpiTxnId) ?>">
                <button class="btn" type="submit">Confirm Payment & Continue</button>
            </form>
        </div>
    <?php endif; ?>
    <?php if ($paymentError !== ''): ?>
        <p class="error"><?= htmlspecialchars($paymentError) ?></p>
    <?php endif; ?>

    <?php if (!$items): ?>
        <div class="empty-cart-page">
            <img src="https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=900&q=80" alt="Empty cart">
            <p>Your cart is empty</p>
            <a href="index.php#products" class="btn">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-table">
            <?php foreach ($items as $item): ?>
                <article class="cart-row">
                    <img src="<?= htmlspecialchars($item['product']['image']) ?>" alt="<?= htmlspecialchars($item['product']['name']) ?>">
                    <div>
                        <h3><?= htmlspecialchars($item['product']['name']) ?></h3>
                        <p>Rs <?= number_format((float)$item['product']['price'], 2) ?></p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= (int)$item['product']['id'] ?>">
                        <input type="number" min="1" name="quantity" value="<?= (int)$item['qty'] ?>">
                        <button class="btn ghost" type="submit">Update</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= (int)$item['product']['id'] ?>">
                        <button class="btn danger" type="submit">Remove</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="cart-total">
            <h3>Total: Rs <?= number_format($total, 2) ?></h3>
        </div>
        <?php if (!$userLoggedIn): ?>
            <div class="auth-required">
                <p class="muted-note">You must be logged in to place an order. Please login or create an account to continue to checkout.</p>
                <a class="btn" href="login.php?redirect=cart.php">Login to Pay</a>
                <a class="btn ghost" href="register.php?redirect=cart.php">Sign Up</a>
            </div>
        <?php endif; ?>

        <form method="post" class="checkout-form">
            <h3>Secure Checkout</h3>
            <input type="hidden" name="action" value="pay">

            <label for="customer_name">Full Name</label>
            <input id="customer_name" name="customer_name" type="text" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">

            <label for="customer_email">Email</label>
            <input id="customer_email" name="customer_email" type="email" required value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">

            <label for="customer_phone">Phone</label>
            <input id="customer_phone" name="customer_phone" type="tel" required maxlength="15" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">

            <label for="address">Delivery Address</label>
            <textarea id="address" name="address" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

            <p class="payment-title">Payment Method</p>
            <div class="payment-options" id="payment_method">
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="card" <?= $paymentMethod === 'card' ? 'checked' : '' ?>>
                    <span>Card</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="upi" <?= $paymentMethod === 'upi' ? 'checked' : '' ?>>
                    <span>UPI</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="netbanking" <?= $paymentMethod === 'netbanking' ? 'checked' : '' ?>>
                    <span>Net Banking</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
                    <span>Cash on Delivery</span>
                </label>
            </div>

            <div class="payment-fields card-fields <?= $paymentMethod === 'card' ? '' : 'payment-hidden' ?>">
                <label for="card_holder">Name on Card</label>
                <input id="card_holder" name="card_holder" type="text" placeholder="Cardholder Name" value="<?= htmlspecialchars($_POST['card_holder'] ?? '') ?>">

                <label for="card_number">Card Number</label>
                <input id="card_number" name="card_number" type="text" placeholder="1234 5678 9012 3456" value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>">

                <div class="split-fields">
                    <div>
                        <label for="card_expiry">Expiry (MM/YY)</label>
                        <input id="card_expiry" name="card_expiry" type="text" placeholder="MM/YY" value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="card_cvv">CVV</label>
                        <input id="card_cvv" name="card_cvv" type="password" placeholder="***" value="<?= htmlspecialchars($_POST['card_cvv'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="payment-fields upi-fields <?= $paymentMethod === 'upi' ? '' : 'payment-hidden' ?>">
                <input id="upi_id" name="upi_id" type="hidden" value="<?= htmlspecialchars($upiReceiverId) ?>">
                <div class="upi-qr-card">
                    <p class="upi-payee-name"><?= htmlspecialchars($upiReceiverName) ?></p>
                    <img src="<?= htmlspecialchars($upiQrImageUrl) ?>" alt="UPI QR code for Rs <?= htmlspecialchars($upiPayAmount) ?>">
                    <p class="upi-payee-id">UPI ID: <?= htmlspecialchars($upiReceiverId) ?></p>
                    <p class="upi-payee-amount">Scan to pay Rs <?= number_format($upiDisplayAmount, 2) ?></p>
                </div>
            </div>

            <div class="payment-fields nb-fields <?= $paymentMethod === 'netbanking' ? '' : 'payment-hidden' ?>">
                <label for="bank_code">Select Bank</label>
                <select id="bank_code" name="bank_code">
                    <option value="">Choose your bank</option>
                    <option value="HDFC" <?= ($_POST['bank_code'] ?? '') === 'HDFC' ? 'selected' : '' ?>>HDFC Bank</option>
                    <option value="ICICI" <?= ($_POST['bank_code'] ?? '') === 'ICICI' ? 'selected' : '' ?>>ICICI Bank</option>
                    <option value="SBI" <?= ($_POST['bank_code'] ?? '') === 'SBI' ? 'selected' : '' ?>>State Bank of India</option>
                    <option value="AXIS" <?= ($_POST['bank_code'] ?? '') === 'AXIS' ? 'selected' : '' ?>>Axis Bank</option>
                </select>
            </div>

            <div class="payment-fields cod-fields <?= $paymentMethod === 'cod' ? '' : 'payment-hidden' ?>">
                <p class="muted-note">Pay in cash at the time of delivery. A confirmation call may be made before dispatch.</p>
            </div>

            <p class="muted-note">Order Amount: Rs <?= number_format($upiDisplayAmount, 2) ?></p>
            <p class="sandbox-note">Sandbox Checkout: This is a simulated payment flow for academic demo use.</p>
            <?php if (!$userLoggedIn): ?>
                <button class="btn" type="button" disabled>Login to Pay</button>
            <?php elseif ($upiPending): ?>
                <button class="btn" type="button" disabled>UPI Payment Pending Confirmation</button>
            <?php else: ?>
                <button class="btn" id="payNowBtn" type="submit">Pay Securely</button>
            <?php endif; ?>
        </form>
        <script>
            (() => {
                const paymentOptions = document.querySelectorAll('input[name="payment_method"]');
                if (!paymentOptions.length) return;
                const cardFields = document.querySelector('.card-fields');
                const upiFields = document.querySelector('.upi-fields');
                const nbFields = document.querySelector('.nb-fields');
                const codFields = document.querySelector('.cod-fields');
                const cardNumber = document.getElementById('card_number');
                const cardExpiry = document.getElementById('card_expiry');
                const payNowBtn = document.getElementById('payNowBtn');
                const checkoutForm = document.querySelector('.checkout-form');

                const toggleFields = () => {
                    const active = document.querySelector('input[name="payment_method"]:checked');
                    const value = active ? active.value : 'card';
                    cardFields?.classList.toggle('payment-hidden', value !== 'card');
                    upiFields?.classList.toggle('payment-hidden', value !== 'upi');
                    nbFields?.classList.toggle('payment-hidden', value !== 'netbanking');
                    codFields?.classList.toggle('payment-hidden', value !== 'cod');
                };

                paymentOptions.forEach((option) => option.addEventListener('change', toggleFields));
                toggleFields();

                cardNumber?.addEventListener('input', () => {
                    const digits = cardNumber.value.replace(/\D+/g, '').slice(0, 16);
                    cardNumber.value = digits.replace(/(.{4})/g, '$1 ').trim();
                });

                cardExpiry?.addEventListener('input', () => {
                    const digits = cardExpiry.value.replace(/\D+/g, '').slice(0, 4);
                    cardExpiry.value = digits.length > 2
                        ? `${digits.slice(0, 2)}/${digits.slice(2)}`
                        : digits;
                });

                checkoutForm?.addEventListener('submit', () => {
                    if (!payNowBtn) return;
                    payNowBtn.disabled = true;
                    payNowBtn.textContent = 'Processing Secure Payment...';
                });
            })();
        </script>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
