<?php
/**
 * Update Ride Sharing System with new features
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Updating Ride Sharing</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; background: #f5f7fa; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; background: white; }
    h1 { color: #333; }
    .progress { width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin: 20px 0; }
    .progress-bar { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); width: 0%; transition: width 0.5s; }
</style>";
echo "</head><body>";
echo "<h1>🚗 Updating Ride Sharing System</h1>";
echo "<div class='progress'><div class='progress-bar' id='progress' style='width: 0%'></div></div>";

try {
    // 1. Add new columns to ride_shares table
    echo "<p>📝 Adding new columns to ride_shares table...</p>";
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS estimated_duration INTEGER DEFAULT 30");
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS flexible_time INTEGER DEFAULT 0");
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS allow_smoking INTEGER DEFAULT 0");
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS allow_pets INTEGER DEFAULT 0");
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS allow_luggage INTEGER DEFAULT 1");
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS music_preference VARCHAR(50) DEFAULT 'any'");
    $pdo->exec("ALTER TABLE ride_shares ADD COLUMN IF NOT EXISTS chat_group_id INTEGER");
    echo "<script>document.getElementById('progress').style.width = '20%';</script>";
    
    // 2. Create chat_messages table
    echo "<p>💬 Creating chat_messages table...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ride_share_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            message_type VARCHAR(20) DEFAULT 'text',
            is_system INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ride_share_id) REFERENCES ride_shares(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<script>document.getElementById('progress').style.width = '40%';</script>";
    
    // 3. Create fare_splits table
    echo "<p>💰 Creating fare_splits table...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fare_splits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ride_share_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            amount_owed DECIMAL(10,2) NOT NULL,
            amount_paid DECIMAL(10,2) DEFAULT 0,
            payment_status VARCHAR(20) DEFAULT 'pending',
            payment_method VARCHAR(50),
            paid_at DATETIME,
            FOREIGN KEY (ride_share_id) REFERENCES ride_shares(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(ride_share_id, user_id)
        )
    ");
    echo "<script>document.getElementById('progress').style.width = '60%';</script>";
    
    // 4. Create ride_ratings table
    echo "<p>⭐ Creating ride_ratings table...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ride_ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ride_share_id INTEGER NOT NULL,
            rater_id INTEGER NOT NULL,
            ratee_id INTEGER NOT NULL,
            rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ride_share_id) REFERENCES ride_shares(id) ON DELETE CASCADE,
            FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (ratee_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<script>document.getElementById('progress').style.width = '80%';</script>";
    
    // 5. Update existing ride shares with sample data
    echo "<p>🔄 Updating existing ride shares with sample data...</p>";
    $pdo->exec("
        UPDATE ride_shares SET 
            estimated_duration = ABS(RANDOM() % 60 + 15),
            flexible_time = ABS(RANDOM() % 2),
            allow_smoking = 0,
            allow_pets = ABS(RANDOM() % 2),
            allow_luggage = 1,
            music_preference = CASE ABS(RANDOM() % 4)
                WHEN 0 THEN 'any'
                WHEN 1 THEN 'quiet'
                WHEN 2 THEN 'conversation'
                WHEN 3 THEN 'music'
            END
        WHERE estimated_duration IS NULL
    ");
    echo "<script>document.getElementById('progress').style.width = '100%';</script>";
    
    echo "<div class='box success'>";
    echo "<h2 style='color:green;'>✅ Ride Sharing System Updated Successfully!</h2>";
    echo "<p>New features added:</p>";
    echo "<ul>";
    echo "<li>🚗 Enhanced ride details (preferences, duration, flexibility)</li>";
    echo "<li>💬 Real-time group chat</li>";
    echo "<li>💰 Split fare calculation with payment tracking</li>";
    echo "<li>⭐ Rating system for riders</li>";
    echo "</ul>";
    echo "<p><a href='pages/ride-sharing.php' style='display: inline-block; padding: 12px 25px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Enhanced Ride Sharing</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>