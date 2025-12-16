<?php
session_start();

// handle role from query
if (isset($_GET['profile']) && $_GET['profile'] === 'cashier') {
    $_SESSION['role'] = 'cashier';
}

// redirect if role not set
if (!isset($_SESSION['role'])) {
    header("Location: user.php");
    exit();
}

$role = $_SESSION['role'];

// handle search query
$searchQuery = trim($_GET['q'] ?? '');

$serverName = "LAPTOP-8KOIBQER\\SQLEXPRESS";
$connectionOptions = [
    "Database" => "DLSU",
    "Uid" => "",
    "PWD" => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

// fetch products from database
if ($searchQuery !== '') {
    $words = explode(' ', $searchQuery);
    $conditions = [];
    foreach ($words as $w) {
        $w = trim($w);
        if ($w !== '') {
            $conditions[] = "PRODUCT_NAME LIKE '%$w%'";
        }
    }
    $where = implode(' AND ', $conditions); 
    $fetch_sql = "SELECT * FROM Menu WHERE $where ORDER BY CATEGORY, PRODUCT_NAME";
    $result = sqlsrv_query($conn, $fetch_sql);
} else {
    $fetch_sql = "SELECT * FROM Menu ORDER BY CATEGORY, PRODUCT_NAME";
    $result = sqlsrv_query($conn, $fetch_sql);
}

if ($result === false) {
    die(print_r(sqlsrv_errors(), true));
}

// process fetched products
$grouped_products = [];
$sizePrices = [];

while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    // group by category
    $catKey = strtolower(str_replace(' ', '-', $row['CATEGORY']));
    $grouped_products[$catKey][] = $row;

    // determine size and prices
    $name = $row['PRODUCT_NAME'];
    $prices = array_map('floatval', [$row['REG_PRICE'], $row['MED_PRICE'], $row['LAR_PRICE']]);

    if (count(array_unique($prices)) === 1) {
        $sizePrices[$name] = ['One Size' => $prices[0]]; 
    } else {
        $sizePrices[$name] = [
            'Regular' => $prices[0],
            'Medium'  => $prices[1],
            'Large'   => $prices[2]
        ];
    }
}

sqlsrv_free_stmt($result);
sqlsrv_close($conn);

// initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// handle add to cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_to_cart') {
        $name = $_POST['name'];
        $size = $_POST['size'];
        $unitPrice = floatval($_POST['unit_price']);
        $quantity = max(1, intval($_POST['quantity']));

        $key = $name . "_" . $size;

        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantity'] += $quantity; 
        } else {
            $_SESSION['cart'][$key] = [
                'name'       => $name,
                'size'       => $size,
                'unit_price' => $unitPrice,
                'quantity'   => $quantity
            ];
        }
        echo json_encode([
            'status' => 'success',
            'cart'   => $_SESSION['cart']
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Brew - Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="home.css">
  </head>
  <body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg">
      <a class="navbar-brand" href="Entrance.html">
          <img src="images/logo.png" alt="Logo" width="125" height="120">
      </a>
      <button id="viewCartBtn" class="btn btn-success me-3" onclick="window.location.href='order.php'">
          <i class="fa fa-shopping-cart"></i>&nbsp;View Orders
      </button>

      <div class="top-buttons">
          <?php if($role === 'admin'): ?>
              <button class="btn btn-success me-2" onclick="window.location.href='reports.php'">
                  <i class="fa-solid fa-chart-line"></i> Sales Report
              </button>
              <button class="btn btn-success me-3" onclick="window.location.href='modify.php'">
                  <i class="fa-solid fa-edit"></i> Modify Menu
              </button>
          <?php endif; ?>
      </div>

      <div class="ms-auto">
          <form id="searchForm" class="d-flex" method="get">
              <input class="form-control me-2" type="search" name="q" placeholder="Search menu..." aria-label="Search" value="<?php echo htmlspecialchars($searchQuery); ?>">
              <button class="btn btn-outline-light" type="submit">Search</button>
          </form>
      </div>
  </nav>

  <!-- Main Content -->
  <div class="main-content">

      <!-- Sidebar -->
      <div class="sidebar">
          <button class="category-btn active" data-category="all">🍽️<br> All</button>
          <button class="category-btn" data-category="coffee">☕<br> Coffee</button>
          <button class="category-btn" data-category="ice-blended">🥤<br> Ice Blended</button>
          <button class="category-btn" data-category="non-coffee">🍵<br> Non-Coffee</button>
          <button class="category-btn" data-category="pasta">🍝<br> Pasta</button>
          <button class="category-btn" data-category="sandwiches">🥪<br> Sandwiches</button>
          <button class="category-btn" data-category="mains">🍗<br> Mains</button>
          <button class="category-btn" data-category="appetizers">🍟<br> Appetizers</button>
      </div>

      <!-- Menu Container -->
      <div class="menu-container">
          <?php
          $categories = ['coffee', 'ice-blended', 'non-coffee', 'pasta', 'sandwiches', 'mains', 'appetizers'];

          $anyProducts = false;
          foreach ($categories as $cat) {
              if (!isset($grouped_products[$cat])) continue; 
              $anyProducts = true;
              $display = 'block'; 
              echo "<div class='menu-section' id='$cat' style='display:$display;'>";
              echo "<h2>" . ucfirst(str_replace('-', ' ', $cat)) . "</h2>";
              echo "<div class='row'>";

              foreach ($grouped_products[$cat] as $p) {
                  $img = $p['IMAGE'];
                  $name = htmlspecialchars($p['PRODUCT_NAME'], ENT_QUOTES);
                  $desc = htmlspecialchars($p['DESCRIPTION'], ENT_QUOTES);
                  $catAttr = htmlspecialchars($p['CATEGORY'], ENT_QUOTES);
                  $price = $p['REG_PRICE'];

                  echo "<div class='col-sm-6 col-lg-4 mb-4'>";
                  echo "<div class='menu-item card product-card' data-name='{$name}' data-desc='{$desc}' data-category='{$catAttr}'>";
                  echo "<img src='{$img}' class='card-img-top' alt='{$name}'>";
                  echo "<div class='menu-item-content card-body'>";
                  echo "<h5>{$name}</h5>";
                  echo "<p>{$desc}</p>";
                  echo "<span class='price'>₱{$price}</span>";
                  echo "</div></div></div>";
              }

              echo "</div></div>";
          }

          if (!$anyProducts) {
              echo '<p class="text-center mt-4">No items found.</p>';
          }
          ?>
      </div>
  </div>

  <!-- Item Modal -->
  <div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="itemModalLabel"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="sizeContainer" class="mb-3 d-none">
            <label for="sizeSelect">Choose Size:</label>
            <select id="sizeSelect" class="form-select"></select>
          </div>
          <div id="quantityContainer">
            <label for="quantityInput">Quantity:</label>
            <div class="input-group">
              <button type="button" class="btn btn-outline-secondary" id="decreaseQty">−</button>
              <input type="number" id="quantityInput" class="form-control text-center" min="1" value="1">
              <button type="button" class="btn btn-outline-secondary" id="increaseQty">+</button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="addToCartBtn" class="btn btn-success">Add to Cart</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
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

  <!-- Toast Notification -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="cartToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body" id="toastBody">Item added to cart!</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const sizePrices = <?php echo json_encode($sizePrices); ?>;

    // category buttons
    document.querySelectorAll(".category-btn").forEach(btn => {
      btn.addEventListener("click", function () {
        document.querySelectorAll(".category-btn").forEach(b => b.classList.remove("active"));
        this.classList.add("active");

        const selected = this.getAttribute("data-category");

        document.querySelectorAll(".menu-section").forEach(sec => {
          if(selected === 'all') {
            sec.style.display = "block"; 
          } else {
            sec.style.display = (sec.id === selected) ? "block" : "none";
          }
        });
      });
    });

    // product click -> open modal
    function attachProductModal() {
      document.querySelectorAll(".product-card").forEach(card => {
        card.addEventListener("click", function () {
          const name = this.dataset.name;
          const desc = this.dataset.desc;

          document.getElementById("itemModalLabel").innerText = name;

          const sizeSelect = document.getElementById("sizeSelect");
          const sizeContainer = document.getElementById("sizeContainer");
          sizeSelect.innerHTML = "";

          if (sizePrices[name]) {
            const sizes = Object.keys(sizePrices[name]);
            if (sizes.length === 1) {
              sizeContainer.classList.add("d-none");
              const price = sizePrices[name][sizes[0]];
              sizeSelect.innerHTML = `<option value="One Size" data-price="${price}">One Size - ₱${price}</option>`;
            } else {
              sizeContainer.classList.remove("d-none");
              sizes.forEach(sz => {
                const price = sizePrices[name][sz];
                const opt = document.createElement("option");
                opt.value = sz;
                opt.dataset.price = price;
                opt.textContent = `${sz} - ₱${price}`;
                sizeSelect.appendChild(opt);
              });
            }
          }

          document.getElementById("quantityInput").value = 1;
          document.getElementById("addToCartBtn").dataset.productName = name;

          new bootstrap.Modal(document.getElementById('itemModal')).show();
        });
      });
    }
    attachProductModal();

    // quantity buttons
    document.getElementById("decreaseQty").addEventListener("click", () => {
      const q = document.getElementById("quantityInput");
      q.value = Math.max(1, parseInt(q.value || "1") - 1);
    });
    document.getElementById("increaseQty").addEventListener("click", () => {
      const q = document.getElementById("quantityInput");
      q.value = Math.max(1, parseInt(q.value || "1") + 1);
    });

    // add to cart
    document.getElementById("addToCartBtn").addEventListener("click", function () {
      const name = this.dataset.productName;
      const sizeSelect = document.getElementById("sizeSelect");
      const size = sizeSelect.value || "One Size";
      const price = parseFloat(sizeSelect.options[sizeSelect.selectedIndex].dataset.price || 0);
      const qty = parseInt(document.getElementById("quantityInput").value || "1");

      const formBody = new URLSearchParams();
      formBody.append('action', 'add_to_cart');
      formBody.append('name', name);
      formBody.append('size', size);
      formBody.append('unit_price', price);
      formBody.append('quantity', qty);

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formBody.toString()
      })
      .then(r => r.json())
      .then(res => {
        if (res.status === 'success') {
          const toastEl = document.getElementById('cartToast');
          document.getElementById('toastBody').innerText = `${qty} x ${name} (${size}) added to cart!`;
          const toast = new bootstrap.Toast(toastEl);
          toast.show();

          const modal = bootstrap.Modal.getInstance(document.getElementById('itemModal'));
          if (modal) modal.hide();
        } else {
          console.error('Could not add to cart', res);
        }
      }).catch(err => console.error(err));
    });
  </script>
  </body>
</html>
