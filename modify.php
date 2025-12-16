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
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    die("connection failed: " . print_r(sqlsrv_errors(), true));
}

$message = "";

// handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // add product
    if ($action === 'add') {
        $category     = $_POST['category'];
        $product_name = $_POST['product_name'];
        $description  = $_POST['description'];

        // check if single price
        if (isset($_POST['no_size'])) {
            $price = $_POST['single_price'];
            $reg_price = $price;
            $med_price = $price;
            $lar_price = $price;
        } else {
            $reg_price = $_POST['reg_price'];
            $med_price = $_POST['med_price'];
            $lar_price = $_POST['lar_price'];
        }

        // handle image upload
        $destination = "products/";
        $filename = basename($_FILES["image"]["name"]);
        $targetFilePath = $destination . $filename;
        $allowtypes = ['jpg', 'jpeg', 'png'];
        $filetype = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        if (in_array(strtolower($filetype), $allowtypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $insert_sql = "
                    INSERT INTO Menu (CATEGORY, PRODUCT_NAME, REG_PRICE, MED_PRICE, LAR_PRICE, DESCRIPTION, IMAGE)
                    VALUES ('$category', '$product_name', $reg_price, $med_price, $lar_price, '$description', '$targetFilePath')
                ";
                $stmt = sqlsrv_query($conn, $insert_sql);
                $message = ($stmt === false) ? "error adding product: " . print_r(sqlsrv_errors(), true) : "product added successfully!";
            } else $message = "failed to upload image.";
        } else $message = "invalid image type. only jpg, jpeg, png allowed.";
    }

    // edit product
    if ($action === 'edit') {
        $id           = $_POST['product_id'];
        $category     = $_POST['editCategory'];
        $product_name = $_POST['editName'];
        $description  = $_POST['editDesc'];

        // check if single price for edit
        if (isset($_POST['edit_no_size'])) {
            $price = $_POST['editSinglePrice'];
            $reg_price = $price;
            $med_price = $price;
            $lar_price = $price;
        } else {
            $reg_price = $_POST['editRegPrice'];
            $med_price = $_POST['editMedPrice'];
            $lar_price = $_POST['editLarPrice'];
        }

        // update query
        $update_sql = "
            UPDATE Menu SET 
                CATEGORY='$category',
                PRODUCT_NAME='$product_name',
                REG_PRICE=$reg_price,
                MED_PRICE=$med_price,
                LAR_PRICE=$lar_price,
                DESCRIPTION='$description'
            WHERE MENU_ID=$id
        ";
        $stmt = sqlsrv_query($conn, $update_sql);
        $message = ($stmt === false) ? "error updating product: " . print_r(sqlsrv_errors(), true) : "product updated successfully!";
    }

    // delete product
    if ($action === 'delete') {
        $id = $_POST['deleteProduct'];
        $del_sql = "DELETE FROM Menu WHERE MENU_ID=$id";
        $stmt = sqlsrv_query($conn, $del_sql);
        $message = ($stmt === false) ? "error deleting product: " . print_r(sqlsrv_errors(), true) : "product deleted successfully!";
    }
}

// fetch products for dropdowns
$products_result = sqlsrv_query($conn, "SELECT MENU_ID, PRODUCT_NAME, CATEGORY, REG_PRICE, MED_PRICE, LAR_PRICE, DESCRIPTION FROM Menu ORDER BY PRODUCT_NAME");
if ($products_result === false) {
    die("error fetching products: " . print_r(sqlsrv_errors(), true));
}

// store products in array
$products = [];
while ($p = sqlsrv_fetch_array($products_result, SQLSRV_FETCH_ASSOC)) {
    $products[] = $p;
}

// close connection
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Modify Menu - Home Brew</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
        <link rel="stylesheet" href="home.css">
    <style>
        body { 
            background-color: #f0f0f0;
        }
        .paper-card { 
            background: white; 
            padding:30px; 
            border-radius:12px; 
            box-shadow:0 0 15px rgba(0,0,0,0.15);
        }
    </style>
    </head>
    <body>
    <nav class="navbar navbar-expand-lg">
        <a class="navbar-brand" href="Entrance.html"><img src="images/logo.png" width="125" height="120"></a>
        <button id="viewCartBtn" class="btn btn-success me-3" onclick="window.location.href='home.php'">Back to Menu</button>
    </nav>

    <div class="container mt-5 paper-card">

    <h2 class="mb-4">Modify Menu</h2>
    <?php if($message): ?>
    <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- tabs -->
    <ul class="nav nav-tabs mb-4" id="menuTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" id="add-tab" data-bs-toggle="tab" data-bs-target="#addTab">Add Product</button></li>
    <li class="nav-item"><button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#editTab">Edit Product</button></li>
    <li class="nav-item"><button class="nav-link" id="delete-tab" data-bs-toggle="tab" data-bs-target="#deleteTab">Delete Product</button></li>
    </ul>

    <div class="tab-content">
        <!-- add product tab -->
        <div class="tab-pane fade show active" id="addTab">
            <form method="POST" action="modify.php" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="action" value="add">

                <div class="col-md-6">
                    <label>Category</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select Category</option>
                        <option value="coffee">Coffee</option>
                        <option value="ice-blended">Ice Blended</option>
                        <option value="non-coffee">Non-Coffee</option>
                        <option value="pasta">Pasta</option>
                        <option value="sandwiches">Sandwiches</option>
                        <option value="mains">Mains</option>
                        <option value="appetizers">Appetizers</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label>Product Name</label>
                    <input type="text" name="product_name" class="form-control" required>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="no_size" name="no_size">
                        <label class="form-check-label" for="no_size">No Size (Single Price)</label>
                    </div>
                </div>

                <div id="size_prices" class="col-12">
                    <div class="row">
                        <div class="col-md-4"><label>Regular Price</label><input type="number" step="0.01" name="reg_price" id="reg_price" class="form-control"></div>
                        <div class="col-md-4"><label>Medium Price</label><input type="number" step="0.01" name="med_price" id="med_price" class="form-control"></div>
                        <div class="col-md-4"><label>Large Price</label><input type="number" step="0.01" name="lar_price" id="lar_price" class="form-control"></div>
                    </div>
                </div>

                <div id="single_price_div" class="col-12" style="display:none;">
                    <label>Price</label>
                    <input type="number" step="0.01" name="single_price" id="single_price" class="form-control">
                </div>

                <div class="col-12">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>

                <div class="col-12">
                    <label>Upload Image</label>
                    <input type="file" name="image" class="form-control" required>
                </div>

                <div class="col-12"><button type="submit" class="btn btn-success">Add Product</button></div>
            </form>
        </div>

        <!-- edit product tab -->
        <div class="tab-pane fade" id="editTab">
            <form method="POST" action="modify.php" class="row g-3">
                <input type="hidden" name="action" value="edit">

                <div class="col-12">
                    <label>Select Product</label>
                    <select name="product_id" id="editProductSelect" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['MENU_ID']; ?>"><?php echo htmlspecialchars($p['PRODUCT_NAME']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label>Category</label>
                    <select name="editCategory" id="editCategory" class="form-select" required>
                        <option value="">Select Category</option>
                        <option value="coffee">Coffee</option>
                        <option value="ice-blended">Ice Blended</option>
                        <option value="non-coffee">Non-Coffee</option>
                        <option value="pasta">Pasta</option>
                        <option value="sandwiches">Sandwiches</option>
                        <option value="mains">Mains</option>
                        <option value="appetizers">Appetizers</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label>Product Name</label>
                    <input type="text" name="editName" id="editName" class="form-control" required>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_no_size" name="edit_no_size">
                        <label class="form-check-label" for="edit_no_size">No Size (Single Price)</label>
                    </div>
                </div>

                <div id="edit_size_prices" class="col-12">
                    <div class="row">
                        <div class="col-md-4"><label>Regular Price</label><input type="number" step="0.01" name="editRegPrice" id="editRegPrice" class="form-control"></div>
                        <div class="col-md-4"><label>Medium Price</label><input type="number" step="0.01" name="editMedPrice" id="editMedPrice" class="form-control"></div>
                        <div class="col-md-4"><label>Large Price</label><input type="number" step="0.01" name="editLarPrice" id="editLarPrice" class="form-control"></div>
                    </div>
                </div>

                <div id="edit_single_price_div" class="col-12" style="display:none;">
                    <label>Price</label>
                    <input type="number" step="0.01" name="editSinglePrice" id="editSinglePrice" class="form-control">
                </div>

                <div class="col-12">
                    <label>Description</label>
                    <textarea name="editDesc" id="editDesc" class="form-control" rows="3" required></textarea>
                </div>

                <div class="col-12"><button type="submit" class="btn btn-primary">Update Product</button></div>
            </form>
        </div>

        <!-- delete product tab -->
        <div class="tab-pane fade" id="deleteTab">
            <form method="POST" action="modify.php">
                <input type="hidden" name="action" value="delete">
                <div class="col-12 mb-3">
                    <label>Select Product</label>
                    <select name="deleteProduct" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['MENU_ID']; ?>"><?php echo htmlspecialchars($p['PRODUCT_NAME']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger">Delete Product</button>
            </form>
        </div>
    </div>

    </div>
    <br><br><br>
    <!-- footer -->
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            // add tab no size toggle
            const addNoSize = document.getElementById('no_size');
            addNoSize.addEventListener('change', function() {
                const checked = this.checked;
                document.getElementById('size_prices').style.display = checked?'none':'block';
                document.getElementById('single_price_div').style.display = checked?'block':'none';
                document.getElementById('reg_price').required = !checked;
                document.getElementById('single_price').required = checked;
            });

            // auto fill medium and large prices based on regular
            document.getElementById('reg_price').addEventListener('input', function(){
                if(!addNoSize.checked){
                    const regPrice = parseFloat(this.value) || 0;
                    document.getElementById('med_price').value = (regPrice+10).toFixed(2);
                    document.getElementById('lar_price').value = (regPrice+30).toFixed(2);
                }
            });

            // edit tab no size toggle
            const editNoSize = document.getElementById('edit_no_size');
            editNoSize.addEventListener('change', function(){
                const checked = this.checked;
                document.getElementById('edit_size_prices').style.display = checked?'none':'block';
                document.getElementById('edit_single_price_div').style.display = checked?'block':'none';
                document.getElementById('editRegPrice').required = !checked;
                document.getElementById('editSinglePrice').required = checked;
            });

            // populate edit tab fields when selecting product
            const products = <?php echo json_encode($products); ?>;
            const select = document.getElementById('editProductSelect');
            if(select){
                select.addEventListener('change', function(){
                    const id = parseInt(this.value);
                    const product = products.find(p => parseInt(p.MENU_ID) === id);
                    if(product){
                        document.getElementById('editCategory').value = product.CATEGORY;
                        document.getElementById('editName').value = product.PRODUCT_NAME;
                        if(product.MED_PRICE===null){
                            editNoSize.checked=true;
                            document.getElementById('edit_size_prices').style.display='none';
                            document.getElementById('edit_single_price_div').style.display='block';
                            document.getElementById('editSinglePrice').value = product.REG_PRICE;
                        } else {
                            editNoSize.checked=false;
                            document.getElementById('edit_size_prices').style.display='block';
                            document.getElementById('edit_single_price_div').style.display='none';
                            document.getElementById('editRegPrice').value = product.REG_PRICE;
                            document.getElementById('editMedPrice').value = product.MED_PRICE;
                            document.getElementById('editLarPrice').value = product.LAR_PRICE;
                        }
                        document.getElementById('editDesc').value = product.DESCRIPTION;
                    }
                });
            }
        });
    </script>
    </body>
</html>
