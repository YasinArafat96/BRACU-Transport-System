<?php
/**
 * Fix ride_share_participants table - add missing created_at column
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Fixing Participants Table</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
    .success { color: green; }
    .error { color: red; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; }
</style>";
echo "</head><body>";
echo "<h1>🔧 Fixing Ride Share Participants Table</h1>";

try {
    // Check if created_at column exists
    $columns = $pdo->query("PRAGMA table_info(ride_share_participants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('created_at', $columns)) {
        // Add created_at column
        $pdo->exec("ALTER TABLE ride_share_participants ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        echo "<p class='success'>✅ Added created_at column to ride_share_participants table</p>";
    } else {
        echo "<p>⏩ created_at column already exists</p>";
    }
    
    // Also check for and add any other missing columns that might be needed
    if (!in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE ride_share_participants ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        echo "<p class='success'>✅ Added updated_at column</p>";
    }
    
    echo "<div class='box success'>";
    echo "<h2>✅ Table fixed successfully!</h2>";
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