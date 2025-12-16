<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: user.php");
    exit();
}

// Initialize cart
$cart = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Remove one quantity of an item
    if (isset($_POST['remove_key'])) {
        $removeKey = $_POST['remove_key'];

        foreach ($cart as $i => $item) {
            $key = $item['name'] . '|' . $item['size'];
            if ($key === $removeKey) {
                if (isset($cart[$i]['quantity']) && $cart[$i]['quantity'] > 1) {
                    $cart[$i]['quantity'] -= 1;
                } else {
                    unset($cart[$i]);
                }
                break;
            }
        }
        $_SESSION['cart'] = array_values($cart);
        header("Location: order.php");
        exit();
    }

    // Clear the entire cart
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $cart = [];
    }

    // Confirm order
    if (isset($_POST['confirm_order'])) {
        $_SESSION['cart'] = [];
        $cart = [];
        $message = "Order confirmed!";
    }
}


// Group cart items by name + size
$groupedCart = [];
foreach ($cart as $item) {
    $name = $item['name'];
    $size = $item['size'];
    $qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    
    if (isset($item['unit_price'])) {
        $unitPrice = (float)$item['unit_price'];
    } else {
        $unitPrice = (float)$item['price'] / $qty;
    }

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
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Orders</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="order.css">
</head>

<body>
<div class="order-card">
    <h2 class="mb-4">Your Orders</h2>
    <?php if(isset($message)) echo "<div class='alert alert-success'>$message</div>"; ?>

    <div class="table-wrapper">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Size</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php 
                $total = 0;
                foreach ($groupedCart as $key => $group): 
                    $total += $group['total'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($group['name']) ?></td>
                        <td><?= htmlspecialchars($group['size']) ?></td>
                        <td><?= $group['quantity'] ?></td>
                        <td>₱<?= number_format($group['total'], 2) ?></td>
                        <td class="text-end">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="remove_key" value="<?= $key ?>">
                                <button type="submit" class="btn-remove">&times;</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th>₱<?= number_format($total, 2) ?></th>
                    <th class="text-end">
                        <form method="POST">
                            <button type="submit" name="clear_cart" class="btn btn-danger btn-sm">Clear All</button>
                        </form>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="order-footer">
        <a href="home.php" class="btn btn-primary">Back to Menu</a>
        <form method="POST" action="billing.php">
            <button type="submit" name="confirm_order" class="btn btn-success">Confirm Order</button>
        </form>
    </div>
</div>
</body>
</html>
