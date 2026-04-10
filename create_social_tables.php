<?php
/**
 * Create Social Billboard tables
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Creating Social Billboard Tables</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; background: #f5f7fa; }
    .success { color: green; }
    .error { color: red; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; background: white; }
    h1 { color: #333; }
</style>";
echo "</head><body>";
echo "<h1>📢 Creating Social Billboard Tables</h1>";

try {
    // Create billboard_posts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS billboard_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            media_type VARCHAR(50) DEFAULT 'text',
            media_url VARCHAR(500),
            is_pinned INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            views INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✅ Created billboard_posts table</p>";
    
    // Create billboard_comments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS billboard_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            comment TEXT NOT NULL,
            likes INTEGER DEFAULT 0,
            is_edited INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES billboard_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✅ Created billboard_comments table</p>";
    
    // Create billboard_likes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS billboard_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES billboard_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(post_id, user_id)
        )
    ");
    echo "<p class='success'>✅ Created billboard_likes table</p>";
    
    // Add sample post for testing
    $check_posts = $pdo->query("SELECT COUNT(*) FROM billboard_posts")->fetchColumn();
    
    if ($check_posts == 0) {
        // Get first admin user
        $admin = $pdo->query("SELECT id FROM users WHERE user_type = 'admin' LIMIT 1")->fetch();
        
        if ($admin) {
            $admin_id = $admin['id'];
            
            // Sample posts
            $sample_posts = [
                [
                    'title' => 'Welcome to the Billboard! 🎉',
                    'content' => 'Welcome everyone to our new social billboard! This is a place where admins can share announcements, ask questions, and interact with the community. Feel free to comment and engage with posts!',
                    'media_type' => 'text',
                    'is_pinned' => 1
                ],
                [
                    'title' => 'New Features Coming Soon!',
                    'content' => 'We\'re working on some exciting new features for the platform. Stay tuned for updates on ride sharing enhancements, loyalty points, and more! What features would you like to see?',
                    'media_type' => 'text',
                    'is_pinned' => 0
                ],
                [
                    'title' => 'Community Guidelines',
                    'content' => 'Please remember to be respectful in your comments. This is a friendly community space for all students. Any inappropriate comments will be removed.',
                    'media_type' => 'text',
                    'is_pinned' => 1
                ]
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO billboard_posts (admin_id, title, content, media_type, is_pinned)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($sample_posts as $post) {
                $stmt->execute([$admin_id, $post['title'], $post['content'], $post['media_type'], $post['is_pinned']]);
            }
            
            echo "<p class='success'>✅ Added sample posts</p>";
            
            // Add sample comments
            $users = $pdo->query("SELECT id FROM users WHERE user_type = 'student' LIMIT 3")->fetchAll();
            $posts = $pdo->query("SELECT id FROM billboard_posts")->fetchAll();
            
            $comment_stmt = $pdo->prepare("
                INSERT INTO billboard_comments (post_id, user_id, comment)
                VALUES (?, ?, ?)
            ");
            
            $sample_comments = [
                'This is great! Looking forward to it.',
                'Thanks for the update!',
                'When will this be available?',
                'Awesome feature!',
                'Can we get more ride sharing options?'
            ];
            
            foreach ($posts as $post) {
                foreach ($users as $user) {
                    $random_comment = $sample_comments[array_rand($sample_comments)];
                    $comment_stmt->execute([$post['id'], $user['id'], $random_comment]);
                }
            }
            
            echo "<p class='success'>✅ Added sample comments</p>";
        }
    }
    
    echo "<div class='box success'>";
    echo "<h2 style='color:green;'>✅ Social Billboard tables created successfully!</h2>";
    echo "<p><a href='pages/social.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Go to Social Billboard</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2 style='color:red;'>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>