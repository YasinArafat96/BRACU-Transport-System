<?php
/**
 * Setup Points & Rewards System
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Setting Up Points System</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; background: #f5f7fa; }
    .success { color: green; }
    .error { color: red; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; background: white; }
    h1 { color: #333; }
</style>";
echo "</head><body>";
echo "<h1>⭐ Setting Up Points & Rewards System</h1>";

try {
    // Check if tables already exist
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    // Create points_history table if not exists
    if (!in_array('points_history', $tables)) {
        $pdo->exec("
            CREATE TABLE points_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                points INTEGER NOT NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                reference_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "<p class='success'>✅ Created points_history table</p>";
    } else {
        echo "<p>⏩ points_history table already exists</p>";
    }
    
    // Create rewards table
    if (!in_array('rewards', $tables)) {
        $pdo->exec("
            CREATE TABLE rewards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                points_required INTEGER NOT NULL,
                discount_type VARCHAR(20) DEFAULT 'percentage',
                discount_value INTEGER NOT NULL,
                icon VARCHAR(50) DEFAULT 'fa-gift',
                color VARCHAR(20) DEFAULT '#667eea',
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p class='success'>✅ Created rewards table</p>";
        
        // Insert sample rewards
        $rewards = [
            ['5% Off Next Ride', 'Get 5% discount on your next bus or Uber ride', 50, 'percentage', 5, 'fa-ticket-alt', '#4CAF50'],
            ['10% Off Next Ride', 'Get 10% discount on your next ride', 100, 'percentage', 10, 'fa-ticket-alt', '#2196F3'],
            ['20% Off Next Ride', 'Get 20% discount on your next ride', 200, 'percentage', 20, 'fa-ticket-alt', '#9C27B0'],
            ['Free Ride (Up to $10)', 'Get a free ride worth up to $10', 300, 'fixed', 10, 'fa-taxi', '#FF9800'],
            ['Free Ride (Up to $20)', 'Get a free ride worth up to $20', 500, 'fixed', 20, 'fa-taxi', '#f44336'],
            ['VIP Status for a Month', 'Enjoy VIP benefits for 30 days', 1000, 'vip', 30, 'fa-crown', '#FFD700']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO rewards (name, description, points_required, discount_type, discount_value, icon, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($rewards as $r) {
            $stmt->execute($r);
        }
        echo "<p class='success'>✅ Added sample rewards</p>";
    } else {
        echo "<p>⏩ rewards table already exists</p>";
    }
    
    // Create user_rewards table
    if (!in_array('user_rewards', $tables)) {
        $pdo->exec("
            CREATE TABLE user_rewards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                reward_id INTEGER NOT NULL,
                points_spent INTEGER NOT NULL,
                discount_code VARCHAR(50) UNIQUE,
                is_used INTEGER DEFAULT 0,
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                used_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE
            )
        ");
        echo "<p class='success'>✅ Created user_rewards table</p>";
    } else {
        echo "<p>⏩ user_rewards table already exists</p>";
    }
    
    // Create weekly_leaderboard table if not exists
    if (!in_array('weekly_leaderboard', $tables)) {
        $pdo->exec("
            CREATE TABLE weekly_leaderboard (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                week_number INTEGER NOT NULL,
                year INTEGER NOT NULL,
                total_points INTEGER NOT NULL,
                rank_position INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, week_number, year)
            )
        ");
        echo "<p class='success'>✅ Created weekly_leaderboard table</p>";
    } else {
        echo "<p>⏩ weekly_leaderboard table already exists</p>";
    }
    
    // Add total_points column to users if not exists
    $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('total_points', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN total_points INTEGER DEFAULT 0");
        echo "<p class='success'>✅ Added total_points column to users</p>";
    } else {
        echo "<p>⏩ total_points column already exists</p>";
    }
    
    // Update existing users with random points for testing
    $users = $pdo->query("SELECT id FROM users")->fetchAll();
    foreach ($users as $user) {
        $random_points = rand(50, 500);
        $pdo->prepare("UPDATE users SET total_points = ? WHERE id = ?")->execute([$random_points, $user['id']]);
    }
    echo "<p class='success'>✅ Updated users with sample points</p>";
    
    echo "<div class='box success'>";
    echo "<h2 style='color:green;'>✅ Points System Ready!</h2>";
    echo "<p>You can now access:</p>";
    echo "<ul>";
    echo "<li><a href='pages/leaderboard.php'>Leaderboard Page</a></li>";
    echo "<li><a href='pages/rewards.php'>Rewards Store</a></li>";
    echo "<li><a href='pages/my-points.php'>My Points Dashboard</a></li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>