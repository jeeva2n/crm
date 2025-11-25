<?php
session_start();
require_once 'functions.php';

echo "<h1>🔧 PRODUCTS DEBUG MODE</h1>";
echo "<p>This will show exactly what's happening with your database</p>";

// Test database connection
echo "<h2>1. Testing Database Connection</h2>";
$conn = getDbConnection();
if ($conn) {
    echo "✅ Database connection successful!<br>";
    
    // Check if products table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Products table exists!<br>";
        
        // Show table structure
        $stmt = $conn->query("DESCRIBE products");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Products table does NOT exist!<br>";
    }
} else {
    echo "❌ Database connection FAILED!<br>";
}

// Test product functions
echo "<h2>2. Testing Product Functions</h2>";

// Test getProducts()
echo "<h3>Testing getProducts()</h3>";
$products = getProducts();
echo "Products in database: " . count($products) . "<br>";
if (count($products) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Serial No</th><th>Name</th><th>Dimensions</th></tr>";
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>{$product['id']}</td>";
        echo "<td>{$product['serial_no']}</td>";
        echo "<td>{$product['name']}</td>";
        echo "<td>{$product['dimensions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test addProduct()
echo "<h3>Testing addProduct()</h3>";
$testProduct = [
    'S.No' => 'TEST_' . time(),
    'Name' => 'Test Product',
    'Dimensions' => '100x50x25mm'
];

echo "Trying to add test product: " . $testProduct['S.No'] . "<br>";
if (addProduct($testProduct)) {
    echo "✅ Test product added successfully!<br>";
} else {
    echo "❌ Failed to add test product!<br>";
}

// Show products again
$products = getProducts();
echo "Total products after test: " . count($products) . "<br>";

echo "<h2>3. Testing Current products.php Logic</h2>";

// Simulate form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Form Submission Detected</h3>";
    
    if (isset($_POST['add_product'])) {
        echo "Add Product form submitted<br>";
        $sNo = $_POST['s_no'] ?? 'No S.No';
        $name = $_POST['name'] ?? 'No Name';
        $dimensions = $_POST['dimensions'] ?? 'No Dimensions';
        
        echo "Form Data: S.No=$sNo, Name=$name, Dimensions=$dimensions<br>";
        
        // Check if product exists
        if (productExists($sNo)) {
            echo "❌ Product already exists in database<br>";
        } else {
            echo "✅ Product does not exist, can be added<br>";
            
            $newProductData = [
                'S.No' => $sNo,
                'Name' => $name,
                'Dimensions' => $dimensions
            ];
            
            if (addProduct($newProductData)) {
                echo "✅ Product added to database successfully!<br>";
            } else {
                echo "❌ Failed to add product to database!<br>";
            }
        }
    }
}
?>

<h2>4. Test Add Product Form</h2>
<form method="post">
    <input type="text" name="s_no" value="TEST_<?= time() ?>" placeholder="S.No" required>
    <input type="text" name="name" value="Test Product" placeholder="Name" required>
    <input type="text" name="dimensions" value="100x50x25mm" placeholder="Dimensions" required>
    <button type="submit" name="add_product">Test Add Product</button>
</form>

<h2>5. Check Error Logs</h2>
<p>Check your PHP error logs for any database errors.</p>
<p>Common locations:</p>
<ul>
    <li>/var/log/apache2/error.log</li>
    <li>/var/log/nginx/error.log</li>
    <li>xampp/php/logs/php_error_log</li>
    <li>wamp/logs/php_error.log</li>
</ul>