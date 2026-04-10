<?php
echo "<h1>Simple MySQL Connection Test</h1>";

// Test 1: mysqli_connect
echo "<h2>Test 1: mysqli_connect</h2>";
$conn = mysqli_connect('localhost', 'root', '');

if (!$conn) {
    echo "<p style='color:red'>❌ mysqli_connect failed: " . mysqli_connect_error() . "</p>";
} else {
    echo "<p style='color:green'>✅ mysqli_connect successful!</p>";
    mysqli_close($conn);
}

// Test 2: PDO
echo "<h2>Test 2: PDO</h2>";
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    echo "<p style='color:green'>✅ PDO connection successful!</p>";
} catch(PDOException $e) {
    echo "<p style='color:red'>❌ PDO failed: " . $e->getMessage() . "</p>";
}

// Test 3: Create database
echo "<h2>Test 3: Create Database</h2>";
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS test_db");
    echo "<p style='color:green'>✅ Database 'test_db' created successfully!</p>";
    
    // Clean up
    $pdo->exec("DROP DATABASE test_db");
    echo "<p style='color:green'>✅ Test database cleaned up</p>";
    
} catch(PDOException $e) {
    echo "<p style='color:red'>❌ Database creation failed: " . $e->getMessage() . "</p>";
}

echo "<h2>System Info:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQLi: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
?>