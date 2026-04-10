<?php
// test-connection.php
echo "<h1>MySQL Connection Test</h1>";

echo "<h2>Testing basic PHP:</h2>";
echo "PHP is working!<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br><br>";

echo "<h2>Testing MySQL connection:</h2>";

$servername = "localhost";
$username = "root";
$password = ""; // XAMPP default is empty

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    echo "<p style='color:red'>❌ Connection failed: " . $conn->connect_error . "</p>";
    echo "<h3>Troubleshooting steps:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running in XAMPP (should be green)</li>";
    echo "<li>Try stopping and starting MySQL</li>";
    echo "<li>Check if port 3306 is being used by another program</li>";
    echo "</ul>";
} else {
    echo "<p style='color:green'>✅ Connected to MySQL successfully!</p>";
    
    // Try to list databases
    $result = $conn->query("SHOW DATABASES");
    if ($result) {
        echo "<p>Available databases:</p><ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . $row['Database'] . "</li>";
        }
        echo "</ul>";
    }
    
    $conn->close();
}

echo "<h2>PHP Info:</h2>";
echo "MySQLi extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
echo "PDO extension: " . (extension_loaded('pdo_mysql') ? '✅ Loaded' : '❌ Not loaded') . "<br>";
?>