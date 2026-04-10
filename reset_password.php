<?php
/**
 * Password Reset Script
 * Use this to fix the password issue
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html>";
echo "<head>";
echo "    <title>Password Reset</title>";
echo "    <style>";
echo "        body { font-family: Arial, sans-serif; margin: 40px; }";
echo "        .success { color: green; font-weight: bold; }";
echo "        .error { color: red; font-weight: bold; }";
echo "        .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; }";
echo "    </style>";
echo "</head>";
echo "<body>";
echo "<h1>Password Reset Utility</h1>";

try {
    // Reset the password for the test user
    $password = 'password123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = :password WHERE student_id = 'S12345'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo "<div class='box'>";
        echo "<p class='success'>✓ Password reset successful!</p>";
        echo "<p>Student ID: <strong>S12345</strong></p>";
        echo "<p>Email: <strong>john.doe@university.edu</strong></p>";
        echo "<p>New Password: <strong>password123</strong></p>";
        echo "</div>";
    } else {
        echo "<p class='error'>✗ Password reset failed</p>";
    }
    
    // Also create a new test user
    $new_password = 'test123';
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (student_id, first_name, last_name, email, password) 
            VALUES ('S10001', 'Test', 'User', 'test.user@university.edu', :password)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':password', $new_hashed_password, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $user_id = $pdo->lastInsertId();
        
        // Add wallet entry
        $sql = "INSERT INTO wallet (user_id, balance) VALUES (:user_id, 50.00)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo "<div class='box'>";
        echo "<p class='success'>✓ New test user created!</p>";
        echo "<p>Student ID: <strong>S10001</strong></p>";
        echo "<p>Email: <strong>test.user@university.edu</strong></p>";
        echo "<p>Password: <strong>test123</strong></p>";
        echo "</div>";
    }
    
} catch(PDOException $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<div class='box'>";
echo "<h2>Test Login Credentials</h2>";
echo "<h3>Original User (Now Fixed):</h3>";
echo "<p>Student ID: <strong>S12345</strong> or Email: <strong>john.doe@university.edu</strong></p>";
echo "<p>Password: <strong>password123</strong></p>";
echo "<h3>New Test User:</h3>";
echo "<p>Student ID: <strong>S10001</strong> or Email: <strong>test.user@university.edu</strong></p>";
echo "<p>Password: <strong>test123</strong></p>";
echo "</div>";

echo "</body>";
echo "</html>";
?>