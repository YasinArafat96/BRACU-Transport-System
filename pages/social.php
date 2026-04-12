<?php
/**
 * Social Billboard Page
 * Admins can post announcements, users can comment
 */

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$is_admin = isAdmin($pdo, $user_id);
$success = '';
$error = '';

// Handle comment submission - THIS MUST BE AT THE TOP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    $post_id = (int)$_POST['post_id'];
    $comment = trim($_POST['comment']);
    
    if (!empty($comment)) {
        // Limit comment to 200 characters
        $comment = substr($comment, 0, 200);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO billboard_comments (post_id, user_id, comment)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$post_id, $user_id, $comment]);
            
            $_SESSION['success'] = "Comment added successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to add comment: " . $e->getMessage();
        }
    }
    
    header("Location: social.php#post-$post_id");
    exit;
}

// Handle like/unlike
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_like'])) {
    $post_id = (int)$_POST['post_id'];
    
    // Check if already liked
    $check = $pdo->prepare("SELECT id FROM billboard_likes WHERE post_id = ? AND user_id = ?");
    $check->execute([$post_id, $user_id]);
    
    if ($check->fetch()) {
        // Unlike
        $pdo->prepare("DELETE FROM billboard_likes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $user_id]);
        $_SESSION['success'] = "Post unliked";
    } else {
        // Like
        $pdo->prepare("INSERT INTO billboard_likes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $user_id]);
        $_SESSION['success'] = "Post liked";
    }
    
    header("Location: social.php#post-$post_id");
    exit;
}

// Handle admin post creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin && isset($_POST['create_post'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $media_type = $_POST['media_type'];
    $media_url = trim($_POST['media_url'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    if (empty($title) || empty($content)) {
        $error = "Title and content are required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO billboard_posts (admin_id, title, content, media_type, media_url, is_pinned)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $title, $content, $media_type, $media_url, $is_pinned]);
            
            $_SESSION['success'] = "Post created successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to create post: " . $e->getMessage();
        }
    }
    
    header("Location: social.php");
    exit;
}

// Handle admin post edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin && isset($_POST['edit_post'])) {
    $post_id = (int)$_POST['post_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $media_type = $_POST['media_type'];
    $media_url = trim($_POST['media_url'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE billboard_posts SET 
                title = ?, content = ?, media_type = ?, media_url = ?, is_pinned = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND admin_id = ?
        ");
        $stmt->execute([$title, $content, $media_type, $media_url, $is_pinned, $post_id, $user_id]);
        
        $_SESSION['success'] = "Post updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update post: " . $e->getMessage();
    }
    
    header("Location: social.php");
    exit;
}

// Handle admin post delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin && isset($_POST['delete_post'])) {
    $post_id = (int)$_POST['post_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM billboard_posts WHERE id = ? AND admin_id = ?");
        $stmt->execute([$post_id, $user_id]);
        
        $_SESSION['success'] = "Post deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete post: " . $e->getMessage();
    }
    
    header("Location: social.php");
    exit;
}

// Handle comment deletion (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_comment']) && $is_admin) {
    $comment_id = (int)$_POST['comment_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM billboard_comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        
        $_SESSION['success'] = "Comment deleted";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete comment";
    }
    
    header("Location: social.php");
    exit;
}

// Get all posts with stats
$posts = $pdo->query("
    SELECT p.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM billboard_comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM billboard_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM billboard_likes WHERE post_id = p.id AND user_id = $user_id) as user_liked
    FROM billboard_posts p
    JOIN users u ON p.admin_id = u.id
    WHERE p.is_active = 1
    ORDER BY p.is_pinned DESC, p.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<style>
:root {
    --billboard-primary: #4a90e2;
    --billboard-secondary: #f5a623;
    --billboard-bg: #f9f9f9;
}

.billboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 30px;
    text-align: center;
}

.billboard-title {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 10px;
}

.billboard-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
}

.admin-panel {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-left: 5px solid var(--billboard-primary);
}

.post-card {
    background: white;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s;
}

.post-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.post-card.pinned {
    border: 2px solid gold;
    position: relative;
}

.post-card.pinned::before {
    content: '📌 PINNED';
    position: absolute;
    top: 10px;
    right: 10px;
    background: gold;
    color: #333;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: bold;
    z-index: 1;
}

.post-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.post-admin {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.admin-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: bold;
    color: white;
}

.admin-info h3 {
    margin: 0;
    font-size: 1.2rem;
}

.admin-info p {
    margin: 5px 0 0;
    opacity: 0.8;
    font-size: 0.9rem;
}

.admin-badge {
    display: inline-block;
    background: gold;
    color: #333;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: bold;
    margin-left: 10px;
}

.post-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin: 15px 0 10px;
}

.post-content {
    padding: 20px;
    line-height: 1.6;
}

.post-media {
    margin: 15px 0;
    text-align: center;
}

.post-media img {
    max-width: 100%;
    max-height: 400px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.post-media video {
    max-width: 100%;
    border-radius: 10px;
}

.post-stats {
    display: flex;
    gap: 20px;
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #666;
}

.stat-item i {
    font-size: 1.1rem;
}

.stat-item.liked {
    color: #e74c3c;
}

.stat-item.liked i {
    color: #e74c3c;
}

.post-actions {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    flex-wrap: wrap;
}

.action-btn {
    flex: 1;
    min-width: 120px;
    padding: 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.action-btn.like {
    background: #f0f0f0;
    color: #666;
}

.action-btn.like:hover {
    background: #ff6b6b;
    color: white;
}

.action-btn.like.liked {
    background: #ff6b6b;
    color: white;
}

.action-btn.comment {
    background: var(--billboard-primary);
    color: white;
}

.action-btn.comment:hover {
    background: #3a7bd5;
    transform: translateY(-2px);
}

.action-btn.view-comments {
    background: #6c757d;
    color: white;
}

.action-btn.view-comments:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.quick-comment {
    background: #f8f9fa;
    border-top: 1px solid #eee;
    padding: 20px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.comment-input {
    flex: 1;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.comment-input:focus {
    border-color: var(--billboard-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
}

.comment-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.comment-form .input-group {
    display: flex;
    gap: 10px;
}

.comments-section {
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
}

.comment {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.comment-content {
    flex: 1;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    flex-wrap: wrap;
}

.comment-author {
    font-weight: bold;
    color: #333;
}

.comment-date {
    color: #999;
    font-size: 0.8rem;
}

.comment-text {
    color: #666;
    line-height: 1.5;
    word-wrap: break-word;
}

.comment-expand {
    color: var(--billboard-primary);
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: underline;
    margin-left: 5px;
}

.comment-expand:hover {
    color: #3a7bd5;
}

.char-counter {
    text-align: right;
    font-size: 0.8rem;
    transition: color 0.3s;
    margin-top: 5px;
    color: #999;
}

.char-counter.near-limit {
    color: #ff9800;
}

.char-counter.at-limit {
    color: #f44336;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
}

.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 20px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 1.5rem;
    cursor: pointer;
    color: #999;
    transition: color 0.3s;
}

.close-modal:hover {
    color: #333;
}

.media-preview {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    display: none;
}

.media-preview img,
.media-preview video {
    max-width: 100%;
    max-height: 200px;
    border-radius: 5px;
}

@media (max-width: 768px) {
    .post-actions {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
    }
    
    .comment {
        flex-direction: column;
    }
    
    .comment-header {
        flex-direction: column;
        gap: 5px;
    }
    
    .post-admin {
        flex-direction: column;
        text-align: center;
    }
    
    .admin-avatar {
        margin: 0 auto;
    }
}
</style>

<div class="container">
    <!-- Header -->
    <div class="billboard-header">
        <div class="billboard-title">
            <i class="fas fa-bullhorn"></i> Community Billboard
        </div>
        <div class="billboard-subtitle">
            Announcements, discussions, and community updates
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="notification error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="notification success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- Admin Panel (only visible to admins) -->
    <?php if ($is_admin): ?>
    <div class="admin-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
            <h2 style="margin: 0;"><i class="fas fa-crown" style="color: gold;"></i> Admin Controls</h2>
            <button class="btn btn-primary" onclick="document.getElementById('createPostModal').style.display='flex'">
                <i class="fas fa-plus"></i> Create New Post
            </button>
        </div>
        <p style="color: #666; margin: 0;">As an admin, you can create, edit, and delete posts. Your posts will appear with the admin badge.</p>
    </div>
    <?php endif; ?>
    
    <!-- Posts Feed -->
    <?php if (count($posts) > 0): ?>
        <?php foreach ($posts as $post): ?>
        <div class="post-card <?php echo $post['is_pinned'] ? 'pinned' : ''; ?>" id="post-<?php echo $post['id']; ?>">
            <!-- Post Header -->
            <div class="post-header">
                <div class="post-admin">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($post['first_name'], 0, 1) . substr($post['last_name'], 0, 1)); ?>
                    </div>
                    <div class="admin-info">
                        <h3><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                            <span class="admin-badge">ADMIN</span>
                        </h3>
                        <p><?php echo date('F j, Y g:i A', strtotime($post['created_at'])); ?>
                            <?php if ($post['updated_at'] != $post['created_at']): ?>
                                <span style="opacity: 0.7;"> (edited)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if ($is_admin && $post['admin_id'] == $user_id): ?>
                    <div style="margin-left: auto; display: flex; gap: 10px;">
                        <button class="btn btn-sm btn-secondary" onclick='editPost(<?php echo json_encode($post); ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="post" onsubmit="return confirm('Delete this post? All comments will also be deleted.')" style="display: inline;">
                            <input type="hidden" name="delete_post" value="1">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Post Content -->
            <div class="post-content">
                <h2 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                
                <div style="white-space: pre-line;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                
                <?php if ($post['media_type'] == 'image' && !empty($post['media_url'])): ?>
                <div class="post-media">
                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Post image">
                </div>
                <?php elseif ($post['media_type'] == 'video' && !empty($post['media_url'])): ?>
                <div class="post-media">
                    <video controls>
                        <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Post Stats -->
            <div class="post-stats">
                <div class="stat-item <?php echo $post['user_liked'] ? 'liked' : ''; ?>">
                    <i class="fas fa-heart"></i> <?php echo $post['like_count']; ?> likes
                </div>
                <div class="stat-item">
                    <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> comments
                </div>
                <div class="stat-item">
                    <i class="fas fa-eye"></i> <?php echo number_format($post['views']); ?> views
                </div>
            </div>
            
            <!-- Post Actions -->
            <div class="post-actions">
                <form method="post" style="flex: 1;">
                    <input type="hidden" name="toggle_like" value="1">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" class="action-btn like <?php echo $post['user_liked'] ? 'liked' : ''; ?>">
                        <i class="fas fa-heart"></i> <?php echo $post['user_liked'] ? 'Unlike' : 'Like'; ?>
                    </button>
                </form>
                
                <button class="action-btn comment" onclick="showCommentInput(<?php echo $post['id']; ?>)">
                    <i class="fas fa-comment"></i> Comment
                </button>
                
                <button class="action-btn view-comments" onclick="toggleComments(<?php echo $post['id']; ?>)">
                    <i class="fas fa-comments"></i> View Comments (<?php echo $post['comment_count']; ?>)
                </button>
            </div>
            
            <!-- Quick Comment Input (hidden by default) -->
            <div class="quick-comment" id="quick-comment-<?php echo $post['id']; ?>" style="display: none;">
                <form method="post" class="comment-form">
                    <input type="hidden" name="add_comment" value="1">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="comment" class="comment-input" placeholder="Write a comment... (max 200 characters)" maxlength="200" required>
                        <button type="submit" class="btn btn-primary">Post</button>
                    </div>
                    <small class="char-counter">0/200 characters</small>
                </form>
            </div>
            
            <!-- Comments Section (hidden by default) -->
            <div class="comments-section" id="comments-<?php echo $post['id']; ?>" style="display: none;">
                <?php
                $comments = $pdo->prepare("
                    SELECT c.*, u.first_name, u.last_name
                    FROM billboard_comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.post_id = ?
                    ORDER BY c.created_at DESC
                ");
                $comments->execute([$post['id']]);
                $post_comments = $comments->fetchAll();
                ?>
                
                <?php if (count($post_comments) > 0): ?>
                    <?php foreach ($post_comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-avatar">
                            <?php echo strtoupper(substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1)); ?>
                        </div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-author">
                                    <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                </span>
                                <span class="comment-date">
                                    <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                </span>
                            </div>
                            <div class="comment-text">
                                <?php 
                                $comment_text = htmlspecialchars($comment['comment']);
                                if (strlen($comment_text) > 200) {
                                    echo nl2br(substr($comment_text, 0, 200));
                                    echo '<span class="comment-expand" onclick="expandComment(this)" data-full="' . htmlspecialchars($comment_text) . '">... read more</span>';
                                } else {
                                    echo nl2br($comment_text);
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if ($is_admin): ?>
                        <form method="post" onsubmit="return confirm('Delete this comment?')" style="margin-left: auto;">
                            <input type="hidden" name="delete_comment" value="1">
                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 20px;">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bullhorn"></i>
            <h3>No posts yet</h3>
            <p>Check back later for announcements from the admin!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Create Post Modal (Admin only) -->
<?php if ($is_admin): ?>
<div class="modal" id="createPostModal">
    <div class="modal-content">
        <span class="close-modal" onclick="document.getElementById('createPostModal').style.display='none'">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Create New Post</h2>
        
        <form method="post">
            <input type="hidden" name="create_post" value="1">
            
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" class="form-control" placeholder="Post title" required>
            </div>
            
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" class="form-control" rows="5" placeholder="Write your post content..." required></textarea>
            </div>
            
            <div class="form-group">
                <label>Media Type</label>
                <select name="media_type" class="form-control" onchange="toggleMediaInput(this.value)">
                    <option value="text">Text Only</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                </select>
            </div>
            
            <div class="form-group" id="mediaUrlGroup" style="display: none;">
                <label>Media URL</label>
                <input type="url" name="media_url" class="form-control" placeholder="Enter image/video URL">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_pinned"> Pin this post (stays at top)
                </label>
            </div>
            
            <button type="submit" class="btn btn-success btn-block">Create Post</button>
        </form>
    </div>
</div>

<!-- Edit Post Modal -->
<div class="modal" id="editPostModal">
    <div class="modal-content">
        <span class="close-modal" onclick="document.getElementById('editPostModal').style.display='none'">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Post</h2>
        
        <form method="post" id="editPostForm">
            <input type="hidden" name="edit_post" value="1">
            <input type="hidden" name="post_id" id="edit_post_id">
            
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" id="edit_content" class="form-control" rows="5" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Media Type</label>
                <select name="media_type" id="edit_media_type" class="form-control" onchange="toggleEditMediaInput(this.value)">
                    <option value="text">Text Only</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                </select>
            </div>
            
            <div class="form-group" id="editMediaUrlGroup" style="display: none;">
                <label>Media URL</label>
                <input type="url" name="media_url" id="edit_media_url" class="form-control">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_pinned" id="edit_is_pinned"> Pin this post
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Update Post</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function showCommentInput(postId) {
    const quickComment = document.getElementById('quick-comment-' + postId);
    
    // Hide all other quick comment inputs first
    document.querySelectorAll('[id^="quick-comment-"]').forEach(el => {
        if (el.id !== 'quick-comment-' + postId) {
            el.style.display = 'none';
        }
    });
    
    // Toggle the clicked one
    if (quickComment.style.display === 'none' || quickComment.style.display === '') {
        quickComment.style.display = 'block';
        // Focus the input
        setTimeout(() => {
            const input = quickComment.querySelector('.comment-input');
            if (input) input.focus();
        }, 100);
    } else {
        quickComment.style.display = 'none';
    }
}

function toggleComments(postId) {
    const comments = document.getElementById('comments-' + postId);
    if (comments.style.display === 'none' || comments.style.display === '') {
        comments.style.display = 'block';
    } else {
        comments.style.display = 'none';
    }
}

function expandComment(element) {
    const fullText = element.getAttribute('data-full');
    const parentDiv = element.parentElement;
    parentDiv.innerHTML = fullText;
}

function toggleMediaInput(type) {
    const mediaGroup = document.getElementById('mediaUrlGroup');
    mediaGroup.style.display = type === 'text' ? 'none' : 'block';
}

function toggleEditMediaInput(type) {
    const mediaGroup = document.getElementById('editMediaUrlGroup');
    mediaGroup.style.display = type === 'text' ? 'none' : 'block';
}

function editPost(post) {
    document.getElementById('edit_post_id').value = post.id;
    document.getElementById('edit_title').value = post.title;
    document.getElementById('edit_content').value = post.content;
    document.getElementById('edit_media_type').value = post.media_type;
    document.getElementById('edit_media_url').value = post.media_url || '';
    document.getElementById('edit_is_pinned').checked = post.is_pinned == 1;
    
    toggleEditMediaInput(post.media_type);
    
    document.getElementById('editPostModal').style.display = 'flex';
}

// Character counter for all comment inputs
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.comment-input').forEach(input => {
        input.addEventListener('input', function() {
            const form = this.closest('form');
            const counter = form ? form.querySelector('.char-counter') : null;
            if (counter) {
                const remaining = this.value.length;
                counter.textContent = remaining + '/200 characters';
                
                counter.classList.remove('near-limit', 'at-limit');
                if (remaining > 180) {
                    counter.classList.add('near-limit');
                }
                if (remaining >= 200) {
                    counter.classList.add('at-limit');
                    this.value = this.value.substring(0, 200);
                }
            }
        });
    });
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const createModal = document.getElementById('createPostModal');
    const editModal = document.getElementById('editPostModal');
    
    if (event.target == createModal) {
        createModal.style.display = 'none';
    }
    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>