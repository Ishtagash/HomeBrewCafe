<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: user.php");
    exit();
}

// Initialize cart
$cart = $_SESSION['cart'] ?? [];
$noOrders = empty($cart);

$serverName = "LAPTOP-8KOIBQER\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "DLSU",
    "Uid" => "",
    "PWD" => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) die(print_r(sqlsrv_errors(), true));

// Handle discount selection
$discountType = $_POST['discount_type'] ?? 'none';
$discountPercent = 0;
$discountText = 'No Discount';
if ($discountType === 'senior' || $discountType === 'pwd') {
    $discountPercent = 20;
    $discountText = strtoupper($discountType);
}

// Handle payment method and amount
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$amountPaid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;

// Group cart items by name and size
$groupedCart = [];
foreach ($cart as $item) {
    $name = $item['name'];
    $size = $item['size'];
    $qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
    $key = $name . '|' . $size;

    if (isset($groupedCart[$key])) {
        $groupedCart[$key]['quantity'] += $qty;
        $groupedCart[$key]['total'] += ($unitPrice * $qty);
    } else {
        $groupedCart[$key] = [
            'name' => $name,
            'size' => $size,
            'unit_price' => $unitPrice,
            'quantity' => $qty,
            'total' => $unitPrice * $qty
        ];
    }
}

// Calculate totals
$subtotal = 0;
foreach ($groupedCart as $group) $subtotal += $group['total'];
$discountAmount = $subtotal * ($discountPercent / 100);
$amountAfterDiscount = $subtotal - $discountAmount;
$vatAmount = $amountAfterDiscount * 0.12;
$total = $amountAfterDiscount + $vatAmount;

// Handle payment
$change = 0;
$insufficientPayment = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enter_payment'])) {
    if ($noOrders) {
    } else {
        // Calculate change
        $change = $amountPaid - $total;
        if ($change < 0) {
            $insufficientPayment = true;
            $change = 0;
        } else {
            // insert into Orders table
            $orderDate = date('Y-m-d H:i:s');
            $orderInsert = "INSERT INTO Orders (ORDER_DATE, TOTAL_AMOUNT, DISCOUNT, PAYMENT_METHOD) VALUES ('$orderDate', $total, '$discountText', '$paymentMethod')";
            $stmt = sqlsrv_query($conn, $orderInsert);
            if ($stmt === false) die(print_r(sqlsrv_errors(), true));

            // get inserted ORDER_ID
            $orderIdQuery = "SELECT IDENT_CURRENT('Orders') AS orderId";
            $orderResult = sqlsrv_query($conn, $orderIdQuery);
            if ($orderResult === false) die(print_r(sqlsrv_errors(), true));
            $row = sqlsrv_fetch_array($orderResult, SQLSRV_FETCH_ASSOC);
            if ($row === false || $row === null) {
                die("Failed to fetch order ID from IDENT_CURRENT(): " . print_r(sqlsrv_errors(), true));
            }
            $orderId = $row['orderId'];
            if ($orderId === null) {
                die("Retrieved order ID is NULL. Check if ORDER_ID is an identity column in the Orders table.");
            }

            // insert order items
            $runningTotal = 0;
            foreach ($groupedCart as $item) {
                $itemTotal = $item['total'];
                $runningTotal += $itemTotal;
                $itemInsert = "INSERT INTO Order_Items (ORDER_ID, ITEM_NAME, SIZE, QUANTITY, ITEM_TOTAL, RUNNING_TOTAL) VALUES ($orderId, '{$item['name']}', '{$item['size']}', {$item['quantity']}, $itemTotal, $runningTotal)";
                $stmtItem = sqlsrv_query($conn, $itemInsert);
                if ($stmtItem === false) die(print_r(sqlsrv_errors(), true));
            }

            // store receipt in session
            $_SESSION['receipt'] = [
                'orderId' => $orderId,
                'groupedCart' => $groupedCart,
                'subtotal' => $subtotal,
                'discountPercent' => $discountPercent,
                'discountAmount' => $discountAmount,
                'vatAmount' => $vatAmount,
                'total' => $total,
                'paymentMethod' => $paymentMethod,
                'amountPaid' => $amountPaid,
                'change' => $change
            ];

            $_SESSION['cart'] = [];
            header("Location: receipt.php");
            exit();
        }
    }
}
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Billing - Receipt</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="billing.css">
    </head>
    <body>

    <div class="billing-card">
        <h2 class="mb-4">Billing - Receipt</h2>
        <form method="POST" id="billing_form">
            <div class="row">
                <!-- Main Receipt Table -->
                <div class="col-lg-8">
                    <div class="table-wrapper">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Size</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedCart as $group): ?>
                                <tr>
                                    <td><?= htmlspecialchars($group['name']) ?></td>
                                    <td><?= htmlspecialchars($group['size']) ?></td>
                                    <td><?= $group['quantity'] ?></td>
                                    <td>₱<?= number_format($group['total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr><th colspan="3">Subtotal</th><th>₱<?= number_format($subtotal, 2) ?></th></tr>
                                <tr><th colspan="3">Discount (<?= $discountPercent ?>%)</th><th>₱<?= number_format($discountAmount, 2) ?></th></tr>
                                <tr><th colspan="3">VAT (12%)</th><th>₱<?= number_format($vatAmount, 2) ?></th></tr>
                                <tr><th colspan="3">Total</th><th>₱<?= number_format($total, 2) ?></th></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Sidebar: Discount and Payment -->
                <div class="col-lg-4">
                    <div class="sidebar">
                        <h5>Select Discount</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="discount_type" id="none" value="none" <?= $discountType==='none'?'checked':'' ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="none">No Discount</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="discount_type" id="senior" value="senior" <?= $discountType==='senior'?'checked':'' ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="senior">Senior Citizen (20%)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="discount_type" id="pwd" value="pwd" <?= $discountType==='pwd'?'checked':'' ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="pwd">PWD (20%)</label>
                        </div>

                        <hr>
                        <h5>Payment Method</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash" <?= $paymentMethod==='cash'?'checked':'' ?>>
                            <label class="form-check-label" for="cash">Cash</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="gcash" value="gcash" <?= $paymentMethod==='gcash'?'checked':'' ?>>
                            <label class="form-check-label" for="gcash">Gcash</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="online_bank" value="online_bank" <?= $paymentMethod==='online_bank'?'checked':'' ?>>
                            <label class="form-check-label" for="online_bank">Online Bank</label>
                        </div>

                        <div class="mt-2">
                            <label for="amount_paid">Amount Paid:</label>
                            <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" value="<?= htmlspecialchars($amountPaid) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="billing-footer">
                <a href="order.php" class="btn btn-secondary">Back to Orders</a>
                <button type="submit" name="enter_payment" class="btn btn-success" <?= $noOrders ? 'disabled' : '' ?>>Enter Payment</button>
            </div>
        </form>
    </div>

    <!-- Insufficient Payment Modal -->
    <div class="modal fade" id="insufficientModal" tabindex="-1" aria-labelledby="insufficientModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="insufficientModalLabel">Insufficient Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    The amount paid is less than the total amount due. Please enter a sufficient amount.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- No Orders Modal -->
    <div class="modal fade" id="noOrdersModal" tabindex="-1" aria-labelledby="noOrdersModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noOrdersModalLabel">No Orders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Your cart is empty. Please add items before proceeding to payment.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($insufficientPayment): ?>
            var myModal = new bootstrap.Modal(document.getElementById('insufficientModal'), {});
            myModal.show();
        <?php endif; ?>
        <?php if ($noOrders): ?>
            var noOrdersModal = new bootstrap.Modal(document.getElementById('noOrdersModal'), {});
            noOrdersModal.show();
        <?php endif; ?>
    </script>

    </body>
</html>
