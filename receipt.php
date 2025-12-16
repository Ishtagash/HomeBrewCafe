<?php
session_start();
if (!isset($_SESSION['role']) || !isset($_SESSION['receipt'])) {
    header("Location: user.php");
    exit();
}

// Get the receipt data
$receipt = $_SESSION['receipt'];
unset($_SESSION['receipt']);

// Ensure numeric values are properly cast
$subtotal = (float) $receipt['subtotal'];
$discountAmount = (float) $receipt['discountAmount'];
$vatAmount = (float) $receipt['vatAmount'];
$total = (float) $receipt['total'];
$amountPaid = (float) $receipt['amountPaid'];
$change = (float) $receipt['change'];

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Receipt</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="receipt.css">
</head>

<body>
<div class="receipt-card">
    <div class="receipt-header">
        <h2>Order Receipt</h2>
        <p>Order ID: <strong><?= htmlspecialchars($receipt['orderId']) ?></strong></p>
        <p>Thank you for your purchase!</p>
    </div>

    <table class="receipt-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($receipt['groupedCart'] as $group): ?>
                <tr>
                    <td><?= htmlspecialchars($group['name']) ?></td>
                    <td><?= htmlspecialchars($group['size']) ?></td>
                    <td><?= (int)$group['quantity'] ?></td>
                    <td>₱<?= number_format((float)$group['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="separator">
                <td colspan="4"></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Subtotal</strong></td>
                <td>₱<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Discount (<?= (float)$receipt['discountPercent'] ?>%)</strong></td>
                <td>₱<?= number_format($discountAmount, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>VAT (12%)</strong></td>
                <td>₱<?= number_format($vatAmount, 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="3"><strong>Total</strong></td>
                <td>₱<?= number_format($total, 2) ?></td>
            </tr>
            <tr class="separator">
                <td colspan="4"></td>
            </tr>
            <tr class="payment-row">
                <td colspan="3"><strong>Payment Method</strong></td>
                <td><?= ucfirst(str_replace('_', ' ', $receipt['paymentMethod'])) ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Payment Amount</strong></td>
                <td>₱<?= number_format($amountPaid, 2) ?></td>
            </tr>
            <tr>
                <td colspan="3"><strong>Change</strong></td>
                <td>₱<?= number_format($change, 2) ?></td>
            </tr>
        </tbody>
    </table>
    <div class="text-center mt-4">
        <a href="home.php" class="btn btn-success me-2">Order Again</a>
        <a href="Entrance.html" class="btn btn-primary me-2">Back to Home</a>
        <button onclick="window.print()" class="btn btn-secondary">Print Receipt</button>
    </div>
</div>
</body>
</html>
