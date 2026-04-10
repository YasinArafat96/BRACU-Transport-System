<?php
/**
 * Update database schema for enhanced user profiles
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Database Update</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; }
    h1 { color: #333; }
</style>";
echo "</head><body>";
echo "<h1>Updating Database Schema for Enhanced Profiles</h1>";

try {
    // Check if we're using SQLite
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "<p>Using database driver: <strong>" . $driver . "</strong></p>";
    
    if ($driver == 'sqlite') {
        // SQLite version - add columns if they don't exist
        echo "<h2>Adding columns to users table...</h2>";
        
        // Check existing columns
        $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        
        $new_columns = [
            'phone' => 'VARCHAR(20)',
            'profile_pic' => 'VARCHAR(255) DEFAULT "default-avatar.png"',
            'address' => 'TEXT',
            'date_of_birth' => 'DATE',
            'gender' => 'VARCHAR(20) DEFAULT "prefer_not_to_say"',
            'emergency_contact_name' => 'VARCHAR(100)',
            'emergency_contact_phone' => 'VARCHAR(20)',
            'preferred_payment_method' => 'VARCHAR(50) DEFAULT "wallet"',
            'bio' => 'TEXT',
            'last_login' => 'DATETIME',
            'is_active' => 'INTEGER DEFAULT 1',
            'email_verified' => 'INTEGER DEFAULT 0',
            'verification_token' => 'VARCHAR(100)'
        ];
        
        foreach ($new_columns as $column => $type) {
            if (!in_array($column, $columns)) {
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN $column $type");
                    echo "<p class='success'>✅ Added column: $column</p>";
                } catch (Exception $e) {
                    echo "<p class='warning'>⚠️ Could not add $column: " . $e->getMessage() . "</p>";
                }
            } else {
                echo "<p>⏩ Column already exists: $column</p>";
            }
        }
        
        // Create user_settings table
        echo "<h2>Creating user_settings table...</h2>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                email_notifications INTEGER DEFAULT 1,
                sms_notifications INTEGER DEFAULT 1,
                push_notifications INTEGER DEFAULT 1,
                dark_mode INTEGER DEFAULT 0,
                language VARCHAR(10) DEFAULT 'en',
                currency VARCHAR(10) DEFAULT 'USD',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "<p class='success'>✅ user_settings table created</p>";
        
        // Create user_favorites table
        echo "<h2>Creating user_favorites table...</h2>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_favorites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                location_name VARCHAR(100) NOT NULL,
                address TEXT NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                location_type VARCHAR(50) DEFAULT 'other',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "<p class='success'>✅ user_favorites table created</p>";
        
        // Create user_ratings table
        echo "<h2>Creating user_ratings table...</h2>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_ratings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rater_id INTEGER NOT NULL,
                rated_user_id INTEGER NOT NULL,
                ride_id INTEGER,
                rating INTEGER NOT NULL,
                review TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (rated_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (ride_id) REFERENCES uber_rides(id) ON DELETE SET NULL
            )
        ");
        echo "<p class='success'>✅ user_ratings table created</p>";
        
        // Create default settings for existing users
        echo "<h2>Creating default settings for users...</h2>";
        $users = $pdo->query("SELECT id FROM users")->fetchAll();
        $count = 0;
        foreach ($users as $user) {
            $check = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = ?");
            $check->execute([$user['id']]);
            if (!$check->fetch()) {
                $insert = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
                $insert->execute([$user['id']]);
                $count++;
            }
        }
        echo "<p class='success'>✅ Created settings for $count users</p>";
        
    } else {
        // MySQL version - similar but with different syntax
        echo "<p class='warning'>⚠️ MySQL detected - would need different ALTER syntax</p>";
    }
    
    echo "<div class='box success'>";
    echo "<h2 style='color:green;'>✅ Database update complete!</h2>";
    echo "<p>You can now access your profile page:</p>";
    echo "<p><a href='pages/profile.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Go to Profile Page</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>