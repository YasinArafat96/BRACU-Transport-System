<?php
/**
 * Track post views
 */

require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['post_id'])) {
    $post_id = (int)$_GET['post_id'];
    
    $stmt = $pdo->prepare("UPDATE billboard_posts SET views = views + 1 WHERE id = ?");
    $stmt->execute([$post_id]);
}

http_response_code(200);
?>