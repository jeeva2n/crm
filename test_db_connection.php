<?php
require_once 'db.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "✅ Database connected successfully!<br>";
    
    // Test inserting a product directly
    $stmt = $conn->prepare("INSERT INTO products (serial_no, name, dimensions) VALUES (?, ?, ?)");
    $testSerial = 'DIRECT_TEST_' . time();
    $result = $stmt->execute([$testSerial, 'Direct Test Product', '100x50x25mm']);
    
    if ($result) {
        echo "✅ Direct database insert worked! Product ID: " . $conn->lastInsertId() . "<br>";
    } else {
        echo "❌ Direct database insert failed!<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
?>