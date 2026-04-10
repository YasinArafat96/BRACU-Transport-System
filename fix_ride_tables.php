<?php
/**
 * Fix missing tables for ride sharing
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Fixing Ride Tables</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
    .success { color: green; }
    .error { color: red; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; }
</style>";
echo "</head><body>";
echo "<h1>🔧 Creating Missing Ride Sharing Tables</h1>";

try {
    // Create fare_splits table
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ride_share_id) REFERENCES ride_shares(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(ride_share_id, user_id)
        )
    ");
    echo "<p class='success'>✅ Created fare_splits table</p>";
    
    // Create chat_messages table
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
    echo "<p class='success'>✅ Created chat_messages table</p>";
    
    // Create ride_ratings table
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
    echo "<p class='success'>✅ Created ride_ratings table</p>";
    
    echo "<div class='box success'>";
    echo "<h2>✅ All tables created successfully!</h2>";
    echo "<p>Your ride sharing page should now work without errors.</p>";
    echo "<p><a href='pages/ride-sharing.php'>Go to Ride Sharing</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>