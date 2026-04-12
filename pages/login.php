<?php
/**
 * Login page 
 */

require_once '../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: ../index.php");
    exit;
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: login.php");
        exit();
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, student_id, first_name, last_name, email, password, user_type, total_points FROM users WHERE student_id = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, start a new session
        $_SESSION["loggedin"] = true;
        $_SESSION["id"] = $user['id'];
        $_SESSION["student_id"] = $user['student_id'];
        $_SESSION["first_name"] = $user['first_name'];
        $_SESSION["last_name"] = $user['last_name'];
        $_SESSION["user_type"] = $user['user_type'];
        
        // Get wallet balance
        $wallet_stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
        $wallet_stmt->execute([$user['id']]);
        $wallet = $wallet_stmt->fetch();
        $_SESSION['balance'] = $wallet ? $wallet['balance'] : 0;
        
        // Redirect user based on user type
        if ($user['user_type'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: ../index.php");
        }
        exit();
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: login.php");
        exit();
    }
}

require_once '../includes/header.php';
?>

<h1 class="page-title">Login to Your Account</h1>

<div class="card">
    <form method="post" action="login.php">
        <div class="form-group">
            <label for="username">Student ID or Email</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your student ID or email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </div>
        <div style="text-align: center;">
            <a href="forgot-password.php" style="color: var(--primary);">Forgot Password?</a>
            <p style="margin-top: 15px;">Don't have an account? <a href="register.php" style="color: var(--primary);">Register here</a></p>
        </div>
    </form>
</div>

<?php
require_once '../includes/footer.php';
?>