<?php
session_start();
// role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: user.php");
    exit();
}

$serverName = "LAPTOP-8KOIBQER\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "DLSU",
    "Uid" => "",
    "PWD" => ""
];

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    die("An error occurred. Please try again later.");
}

// helper function for query execution
function executeQuery($conn, $sql, $params = []) {
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if (!$stmt || !sqlsrv_execute($stmt)) {
        throw new Exception("Query execution failed: " . print_r(sqlsrv_errors(), true));
    }
    return $stmt;
}

// check if delete order request is sent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $orderId = $_POST['delete_order'];

    try {
        // delete from order_items first
        $deleteItemsSql = "DELETE FROM Order_Items WHERE ORDER_ID = ?";
        executeQuery($conn, $deleteItemsSql, [$orderId]);

        // then delete from orders
        $deleteOrderSql = "DELETE FROM Orders WHERE ORDER_ID = ?";
        executeQuery($conn, $deleteOrderSql, [$orderId]);

        // optional: redirect to avoid resubmission
        header("Location: ?tab=orders");
        exit();
    } catch (Exception $e) {
        echo "<script>alert('Failed to delete order: ".$e->getMessage()."');</script>";
    }
}


// determine active tab and period
$activeTab = $_GET['tab'] ?? 'sales';
$period = $_GET['period'] ?? 'daily';

// sales data
$salesData = [];
if ($period === 'weekly') {
    $salesSql = "SELECT DATEPART(YEAR, ORDER_DATE) AS yr, DATEPART(WEEK, ORDER_DATE) AS wk, SUM(TOTAL_AMOUNT) AS sales FROM Orders GROUP BY DATEPART(YEAR, ORDER_DATE), DATEPART(WEEK, ORDER_DATE) ORDER BY yr, wk";
} elseif ($period === 'monthly') {
    $salesSql = "SELECT DATEPART(YEAR, ORDER_DATE) AS yr, DATEPART(MONTH, ORDER_DATE) AS mth, SUM(TOTAL_AMOUNT) AS sales FROM Orders GROUP BY DATEPART(YEAR, ORDER_DATE), DATEPART(MONTH, ORDER_DATE) ORDER BY yr, mth";
} else {
    $salesSql = "SELECT CAST(ORDER_DATE AS DATE) AS day, SUM(TOTAL_AMOUNT) AS sales FROM Orders GROUP BY CAST(ORDER_DATE AS DATE) ORDER BY day";
}
$salesRes = executeQuery($conn, $salesSql);
while ($row = sqlsrv_fetch_array($salesRes, SQLSRV_FETCH_ASSOC)) {
    if ($period === 'weekly') {
        $label = sprintf("Week %s, %s", $row['wk'], $row['yr']);
    } elseif ($period === 'monthly') {
        $label = sprintf("%s-%02d", $row['yr'], $row['mth']);
    } else {
        $label = $row['day']->format('Y-m-d');
    }
    $salesData[] = [$label, (float)$row['sales']];
}
sqlsrv_free_stmt($salesRes);

// sales summary
$summarySql = "SELECT SUM(TOTAL_AMOUNT) AS totalSales, MAX(TOTAL_AMOUNT) AS highestSales, COUNT(DISTINCT CAST(ORDER_DATE AS DATE)) AS numDays, AVG(TOTAL_AMOUNT) AS avgDailySales FROM Orders";
$summaryRes = executeQuery($conn, $summarySql);
$summary = sqlsrv_fetch_array($summaryRes, SQLSRV_FETCH_ASSOC) ?? ['totalSales' => 0, 'highestSales' => 0, 'numDays' => 0, 'avgDailySales' => 0];
$totalSales = (float)$summary['totalSales'];
$highestSales = (float)$summary['highestSales'];
$numDays = (int)$summary['numDays'];
$avgDailySales = (float)$summary['avgDailySales'];
sqlsrv_free_stmt($summaryRes);

// today sales
$today = date('Y-m-d');
$todaySalesSql = "SELECT SUM(TOTAL_AMOUNT) AS todaySales FROM Orders WHERE CAST(ORDER_DATE AS DATE) = ?";
$todayRes = executeQuery($conn, $todaySalesSql, [$today]);
$todaySales = 0;
if ($row = sqlsrv_fetch_array($todayRes, SQLSRV_FETCH_ASSOC)) {
    $todaySales = (float)($row['todaySales'] ?? 0);
}
sqlsrv_free_stmt($todayRes);

// payment method data
$paymentSql = "SELECT PAYMENT_METHOD, COUNT(*) AS count FROM Orders GROUP BY PAYMENT_METHOD";
$paymentRes = executeQuery($conn, $paymentSql);
$paymentData = [];
while ($row = sqlsrv_fetch_array($paymentRes, SQLSRV_FETCH_ASSOC)) {
    $paymentData[$row['PAYMENT_METHOD']] = (int)$row['count'];
}
sqlsrv_free_stmt($paymentRes);

// discount data
$discountSql = "SELECT DISCOUNT, COUNT(*) AS count FROM Orders GROUP BY DISCOUNT";
$discountRes = executeQuery($conn, $discountSql);
$discountData = [];
while ($row = sqlsrv_fetch_array($discountRes, SQLSRV_FETCH_ASSOC)) {
    $discountData[$row['DISCOUNT']] = (int)$row['count'];
}
sqlsrv_free_stmt($discountRes);

// most and least bought products
$mostSql = "SELECT TOP 1 ITEM_NAME, SUM(QUANTITY) AS totalQty FROM Order_Items GROUP BY ITEM_NAME ORDER BY totalQty DESC";
$mostRes = executeQuery($conn, $mostSql);
$mostProduct = ['name' => 'N/A', 'qty' => 0];
if ($r = sqlsrv_fetch_array($mostRes, SQLSRV_FETCH_ASSOC)) {
    $mostProduct = ['name' => $r['ITEM_NAME'], 'qty' => (int)$r['totalQty']];
}
sqlsrv_free_stmt($mostRes);

$leastSql = "SELECT TOP 1 ITEM_NAME, SUM(QUANTITY) AS totalQty FROM Order_Items GROUP BY ITEM_NAME ORDER BY totalQty ASC";
$leastRes = executeQuery($conn, $leastSql);
$leastProduct = ['name' => 'N/A', 'qty' => 0];
if ($r = sqlsrv_fetch_array($leastRes, SQLSRV_FETCH_ASSOC)) {
    $leastProduct = ['name' => $r['ITEM_NAME'], 'qty' => (int)$r['totalQty']];
}
sqlsrv_free_stmt($leastRes);

// product names
$productListSql = "SELECT DISTINCT ITEM_NAME FROM Order_Items ORDER BY ITEM_NAME";
$productListRes = executeQuery($conn, $productListSql);
$productNames = [];
while ($row = sqlsrv_fetch_array($productListRes, SQLSRV_FETCH_ASSOC)) {
    $productNames[] = $row['ITEM_NAME'];
}
sqlsrv_free_stmt($productListRes);

// product sales data per day
$productData = [];
$productDataSql = "SELECT OI.ITEM_NAME, CAST(O.ORDER_DATE AS DATE) AS day, SUM(OI.QUANTITY) AS qty FROM Order_Items OI JOIN Orders O ON OI.ORDER_ID = O.ORDER_ID GROUP BY OI.ITEM_NAME, CAST(O.ORDER_DATE AS DATE) ORDER BY day";
$productDataRes = executeQuery($conn, $productDataSql);
while ($row = sqlsrv_fetch_array($productDataRes, SQLSRV_FETCH_ASSOC)) {
    $productData[$row['ITEM_NAME']][] = [
        'day' => $row['day']->format('Y-m-d'),
        'qty' => (int)$row['qty']
    ];
}
sqlsrv_free_stmt($productDataRes);

// total products sold per day (single query)
$productsPerDaySql = "SELECT CAST(O.ORDER_DATE AS DATE) AS day, SUM(OI.QUANTITY) AS totalQty FROM Orders O JOIN Order_Items OI ON O.ORDER_ID = OI.ORDER_ID GROUP BY CAST(O.ORDER_DATE AS DATE) ORDER BY day";
$productsPerDayRes = executeQuery($conn, $productsPerDaySql);
$productsPerDayLabels = [];
$productsPerDayValues = [];
while ($row = sqlsrv_fetch_array($productsPerDayRes, SQLSRV_FETCH_ASSOC)) {
    $productsPerDayLabels[] = $row['day']->format('Y-m-d');
    $productsPerDayValues[] = (int)$row['totalQty'];
}
sqlsrv_free_stmt($productsPerDayRes);

// all orders with items
$searchTerm = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'date_desc';
$allOrdersSql = "SELECT O.ORDER_ID, O.ORDER_DATE, O.TOTAL_AMOUNT, O.PAYMENT_METHOD, O.DISCOUNT, OI.ITEM_NAME, OI.QUANTITY FROM Orders O JOIN Order_Items OI ON O.ORDER_ID = OI.ORDER_ID";
if (!empty($searchTerm)) {
    $allOrdersSql .= " WHERE O.ORDER_ID LIKE ? OR OI.ITEM_NAME LIKE ? OR O.PAYMENT_METHOD LIKE ?";
}
switch ($sortBy) {
    case 'date_asc': $allOrdersSql .= " ORDER BY O.ORDER_DATE ASC, O.ORDER_ID ASC"; break;
    case 'amount_desc': $allOrdersSql .= " ORDER BY O.TOTAL_AMOUNT DESC"; break;
    case 'amount_asc': $allOrdersSql .= " ORDER BY O.TOTAL_AMOUNT ASC"; break;
    default: $allOrdersSql .= " ORDER BY O.ORDER_DATE DESC, O.ORDER_ID DESC";
}
$allOrdersRes = !empty($searchTerm) ? executeQuery($conn, $allOrdersSql, ["%$searchTerm%","%$searchTerm%","%$searchTerm%"]) : executeQuery($conn, $allOrdersSql);

$allOrders = [];
while ($row = sqlsrv_fetch_array($allOrdersRes, SQLSRV_FETCH_ASSOC)) {
    $oid = $row['ORDER_ID'];
    if (!isset($allOrders[$oid])) {
        $allOrders[$oid] = [
            'date' => $row['ORDER_DATE']->format('Y-m-d H:i'),
            'total' => (float)$row['TOTAL_AMOUNT'],
            'payment' => $row['PAYMENT_METHOD'],
            'discount' => $row['DISCOUNT'],
            'items' => []
        ];
    }
    $allOrders[$oid]['items'][] = [
        'name' => $row['ITEM_NAME'],
        'qty' => (int)$row['QUANTITY']
    ];
}
sqlsrv_free_stmt($allOrdersRes);

// close connection
sqlsrv_close($conn);

// encode data for charts
$days = json_encode(array_column($salesData, 0));
$sales = json_encode(array_column($salesData, 1));
$paymentLabels = json_encode(array_keys($paymentData));
$paymentCounts = json_encode(array_values($paymentData));
$discountLabels = json_encode(array_keys($discountData));
$discountCounts = json_encode(array_values($discountData));
$productNamesJson = json_encode($productNames);
$productDataJson = json_encode($productData);
$productsPerDayLabelsJson = json_encode($productsPerDayLabels);
$productsPerDayValuesJson = json_encode($productsPerDayValues);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="reports.css">
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <a class="navbar-brand" href="Entrance.html">
        <img src="images/logo.png" alt="Logo" width="125" height="120">
    </a>
    <button class="btn btn-success ms-3" onclick="window.location.href='home.php'">
        <i class="fa-solid fa-arrow-left"></i> Back to Menu
    </button>
</nav>

<div class="container mt-4">
    <ul class="nav nav-tabs" id="reportTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'sales' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#salesTab">Sales Summary</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'product' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#productTab">Product Purchase Stats</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'orders' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#allOrdersTab">All Orders</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade <?= $activeTab === 'sales' ? 'show active' : '' ?>" id="salesTab">
            <div class="report-card">
                <h3>Sales Report (<?= ucfirst($period) ?>)</h3>
                <form method="GET" class="mb-3">
                    <input type="hidden" name="tab" value="sales">
                    <label>Period</label>
                    <select name="period" class="form-select d-inline-block w-auto">
                        <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                    <button type="submit" class="btn btn-success ms-2">Apply</button>
                </form>
                <canvas id="salesChart"></canvas>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h6>Total Sales</h6>
                        <h5>₱<?= number_format($totalSales, 2) ?></h5>
                    </div>
                    <div class="stat-card">
                        <h6>Sales Today</h6>
                        <h5>₱<?= number_format($todaySales, 2) ?></h5>
                    </div>
                    <div class="stat-card">
                        <h6>Highest Sale</h6>
                        <h5>₱<?= number_format($highestSales, 2) ?></h5>
                    </div>
                    <div class="stat-card">
                        <h6>Number of Days</h6>
                        <h5><?= $numDays ?></h5>
                    </div>
                    <div class="stat-card">
                        <h6>Average Daily Sales</h6>
                        <h5>₱<?= number_format($avgDailySales, 2) ?></h5>
                    </div>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-container">
                    <h5 class="mb-3">Payment Methods</h5>
                    <div class="chart-wrapper">
                        <canvas id="paymentChartPie"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h5 class="mb-3">Products Sold Per Day</h5>
                    <div class="chart-wrapper">
                        <canvas id="productsPerDayChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h5 class="mb-3">Discounts</h5>
                    <div class="chart-wrapper">
                        <canvas id="discountChartPie"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'product' ? 'show active' : '' ?>" id="productTab">
            <div class="product-layout">
                <div class="report-card">
                    <h4>Product Purchase Statistics</h4>
                    <div class="mb-3">
                        <label class="fw-bold">Select Product</label>
                        <select id="productSelect" class="form-select">
                            <?php foreach ($productNames as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <canvas id="productChart"></canvas>
                </div>

                <div class="product-stats-sidebar">
                    <h5 class="mb-0">Product Summary</h5>
                    <div class="stat-card">
                        <h6>Most Bought Product</h6>
                        <h5><?= htmlspecialchars($mostProduct['name']) ?></h5>
                        <small class="text-muted"><?= $mostProduct['qty'] ?> items sold</small>
                    </div>
                    <div class="stat-card">
                        <h6>Least Bought Product</h6>
                        <h5><?= htmlspecialchars($leastProduct['name']) ?></h5>
                        <small class="text-muted"><?= $leastProduct['qty'] ?> items sold</small>
                    </div>
                    <div class="stat-card">
                        <h6>Total Products Sold</h6>
                        <?php
                            $totalProductsSold = 0;
                            foreach ($productData as $product) {
                                foreach ($product as $dayData) {
                                    $totalProductsSold += $dayData['qty'];
                                }
                            }
                            ?>
                        <h5><?= $totalProductsSold ?></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $activeTab === 'orders' ? 'show active' : '' ?>" id="allOrdersTab">
            <div class="report-card">
                <h4>All Orders</h4>
                
                <form method="GET" class="filter-section">
                    <input type="hidden" name="tab" value="orders">
                    <div>
                        <label class="form-label"><i class="fa-solid fa-search"></i> Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Order ID, Product, or Payment Method" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <div>
                        <label class="form-label"><i class="fa-solid fa-sort"></i> Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Date (Newest First)</option>
                            <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>Date (Oldest First)</option>
                            <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>Amount (High to Low)</option>
                            <option value="amount_asc" <?= $sortBy === 'amount_asc' ? 'selected' : '' ?>>Amount (Low to High)</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="submit" class="btn btn-success me-2">Apply</button>
                        <a href="?tab=orders" class="btn btn-secondary">Clear</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Payment</th>
                                <th>Discount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allOrders as $id => $order): ?>
                                <tr>
                                    <td><?= $id ?></td>
                                    <td><?= $order['date'] ?></td>
                                    <td>
                                        <ul class="mb-0">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <li><?= htmlspecialchars($item['name']) ?> x <?= $item['qty'] ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>₱<?= number_format($order['total'], 2) ?></td>
                                    <td><?= htmlspecialchars($order['payment']) ?></td>
                                    <td><?= htmlspecialchars($order['discount']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_order" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this order?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($allOrders) == 0): ?>
                                <tr><td colspan="7" class="text-center">No orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<br><br><br><br><br>
<footer class="bg-dark text-white py-3 mt-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-3">
                <h6 class="fw-bold mb-2">Follow Us</h6>
                <div class="d-flex">
                    <a href="https://www.facebook.com/ClassicHomeBrewCafe" target="_blank" class="text-white me-3">
                        <img src="images/facebook.png" height="35" width="35">
                    </a>
                    <a href="https://www.instagram.com/homebrewcafeimus" target="_blank" class="text-white me-3">
                        <img src="images/instagram.png" height="35" width="35">
                    </a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <h6 class="fw-bold mb-2">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="Entrance.html" class="text-white text-decoration-none">Home</a></li>
                    <li class="mb-1"><a href="home.php" class="text-white text-decoration-none">Menu</a></li>
                    <li class="mb-1"><a href="aboutme.html" class="text-white text-decoration-none">About Us</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-12 mb-3">
                <h6 class="fw-bold mb-2">Contact Us</h6>
                <p class="mb-1 small"><img src="images/email.png" height="35" width="35">&nbsp;&nbsp;<a href="mailto:homebrew@gmail.com" class="text-white text-decoration-none">homebrew@gmail.com</a></p>
                <p class="mb-2 small"><img src="images/contact.png" height="35" width="35">&nbsp;<a href="tel:+639270281312" class="text-white text-decoration-none">+63 927 028 1312</a></p>
                <p class="mb-0 small">&copy; 2025 Home Brew. All Rights Reserved.</p>
                <p class="mb-0 small">Designed by Corbin Ezekiel O. Gutierrez</p>
            </div>
        </div>
    </div>
</footer>

<script>
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: <?= $days ?>,
            datasets: [{
                label: 'Sales (₱)',
                data: <?= $sales ?>,
                borderWidth: 2,
                fill: true,
                backgroundColor: 'rgba(54,162,235,0.2)',
                borderColor: 'rgba(54,162,235,1)'
            }]
        },
        options: { 
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true } } 
        }
    });

new Chart(document.getElementById('productsPerDayChart'), {
    type: 'line',
    data: {
        labels: <?= $productsPerDayLabelsJson ?>,
        datasets: [{
            label: 'Products Sold',
            data: <?= $productsPerDayValuesJson ?>,
            fill: true,
            borderColor: 'rgba(75,192,192,1)',
            backgroundColor: 'rgba(75,192,192,0.2)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: { y: { beginAtZero: true } }
    }
});



    new Chart(document.getElementById('paymentChartPie'), {
        type: 'pie',
        data: {
            labels: <?= $paymentLabels ?>,
            datasets: [{ data: <?= $paymentCounts ?>, backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4BC0C0'] }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom' } } 
        }
    });

    new Chart(document.getElementById('discountChartPie'), {
        type: 'pie',
        data: {
            labels: <?= $discountLabels ?>,
            datasets: [{ data: <?= $discountCounts ?>, backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4BC0C0'] }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom' } } 
        }
    });

    const productNames = <?= $productNamesJson ?>;
    const productData = <?= $productDataJson ?>;

    const productChart = new Chart(document.getElementById('productChart'), {
        type: 'bar',
        data: { 
            labels: [], 
            datasets: [{ 
                label: 'Quantity Sold', 
                data: [], 
                borderWidth: 1, 
                backgroundColor: 'rgba(255,99,132,0.7)' 
            }] 
        },
        options: { 
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true } } 
        }
    });

    function updateProductChart(name) {
        const data = productData[name] || [];
        productChart.data.labels = data.map(d => d.day);
        productChart.data.datasets[0].data = data.map(d => d.qty);
        productChart.update();
    }

    if (productNames.length > 0) updateProductChart(productNames[0]);
    document.getElementById('productSelect').addEventListener('change', function () {
        updateProductChart(this.value);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>