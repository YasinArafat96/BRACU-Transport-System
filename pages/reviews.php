<?php
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['id'];

// Keep table available even on older DB files.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
        review_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_review_votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        review_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        vote_type TEXT NOT NULL CHECK (vote_type IN ('like', 'dislike')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (review_id) REFERENCES user_reviews(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (review_id, user_id)
    )
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_review'])) {
        $rating = (int) ($_POST['rating'] ?? 0);
        $review_text = trim((string) ($_POST['review_text'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            $_SESSION['error'] = 'Please select a rating between 1 and 5.';
        } elseif ($review_text === '') {
            $_SESSION['error'] = 'Please write your review before submitting.';
        } elseif (strlen($review_text) < 5) {
            $_SESSION['error'] = 'Review is too short. Please write at least 5 characters.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO user_reviews (user_id, rating, review_text) VALUES (?, ?, ?)');
            $stmt->execute([$user_id, $rating, $review_text]);
            $_SESSION['success'] = 'Thanks! Your review has been submitted.';
        }
        header('Location: reviews.php');
        exit;
    }
    
    if (isset($_POST['vote_review'])) {
        $review_id = (int) ($_POST['review_id'] ?? 0);
        $vote_type = (string) ($_POST['vote_type'] ?? '');
        if ($review_id < 1 || !in_array($vote_type, ['like', 'dislike'], true)) {
            $_SESSION['error'] = 'Invalid vote request.';
            header('Location: reviews.php');
            exit;
        }

        $owner_stmt = $pdo->prepare('SELECT user_id FROM user_reviews WHERE id = ?');
        $owner_stmt->execute([$review_id]);
        $review_owner_id = (int) $owner_stmt->fetchColumn();
        if ($review_owner_id < 1) {
            $_SESSION['error'] = 'Review not found.';
            header('Location: reviews.php');
            exit;
        }
        if ($review_owner_id === $user_id) {
            $_SESSION['error'] = 'You cannot vote on your own review.';
            header('Location: reviews.php');
            exit;
        }

        $current_vote_stmt = $pdo->prepare('SELECT vote_type FROM user_review_votes WHERE review_id = ? AND user_id = ?');
        $current_vote_stmt->execute([$review_id, $user_id]);
        $current_vote = $current_vote_stmt->fetchColumn();

        if ($current_vote === $vote_type) {
            $remove_stmt = $pdo->prepare('DELETE FROM user_review_votes WHERE review_id = ? AND user_id = ?');
            $remove_stmt->execute([$review_id, $user_id]);
        } else {
            $save_stmt = $pdo->prepare("
                INSERT INTO user_review_votes (review_id, user_id, vote_type, created_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(review_id, user_id)
                DO UPDATE SET vote_type = excluded.vote_type, created_at = CURRENT_TIMESTAMP
            ");
            $save_stmt->execute([$review_id, $user_id, $vote_type]);
        }

        header('Location: reviews.php');
        exit;
    }
}

$public_reviews_stmt = $pdo->prepare("
    SELECT
        ur.id,
        ur.user_id,
        ur.rating,
        ur.review_text,
        ur.created_at,
        u.first_name,
        u.last_name,
        COALESCE(SUM(CASE WHEN rv.vote_type = 'like' THEN 1 ELSE 0 END), 0) AS like_count,
        COALESCE(SUM(CASE WHEN rv.vote_type = 'dislike' THEN 1 ELSE 0 END), 0) AS dislike_count,
        MAX(CASE WHEN myv.user_id IS NOT NULL THEN myv.vote_type ELSE NULL END) AS my_vote
    FROM user_reviews ur
    INNER JOIN users u ON u.id = ur.user_id
    LEFT JOIN user_review_votes rv ON rv.review_id = ur.id
    LEFT JOIN user_review_votes myv ON myv.review_id = ur.id AND myv.user_id = ?
    GROUP BY ur.id, ur.user_id, ur.rating, ur.review_text, ur.created_at, u.first_name, u.last_name
    ORDER BY ur.created_at DESC
");
$public_reviews_stmt->execute([$user_id]);
$public_reviews = $public_reviews_stmt->fetchAll();

$my_reviews_stmt = $pdo->prepare("
    SELECT
        ur.id,
        ur.rating,
        ur.review_text,
        ur.created_at,
        COALESCE(SUM(CASE WHEN rv.vote_type = 'like' THEN 1 ELSE 0 END), 0) AS like_count,
        COALESCE(SUM(CASE WHEN rv.vote_type = 'dislike' THEN 1 ELSE 0 END), 0) AS dislike_count
    FROM user_reviews ur
    LEFT JOIN user_review_votes rv ON rv.review_id = ur.id
    WHERE ur.user_id = ?
    GROUP BY ur.id, ur.rating, ur.review_text, ur.created_at
    ORDER BY ur.created_at DESC
");
$my_reviews_stmt->execute([$user_id]);
$my_reviews = $my_reviews_stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="page-title">Community Reviews <i class="fas fa-star"></i></h1>

<div class="card">
    <h2><i class="fas fa-pen"></i> Submit a Review</h2>
    <form method="post">
        <input type="hidden" name="submit_review" value="1">
        <div class="form-group">
            <label for="rating">Rating</label>
            <select id="rating" name="rating" class="form-control" required>
                <option value="">Select rating</option>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Good</option>
                <option value="3">3 - Average</option>
                <option value="2">2 - Poor</option>
                <option value="1">1 - Very Poor</option>
            </select>
        </div>
        <div class="form-group">
            <label for="review_text">Review</label>
            <textarea id="review_text" name="review_text" class="form-control" rows="4" maxlength="1200" placeholder="Share your experience..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Submit Review
        </button>
    </form>
</div>

<div class="card">
    <h2><i class="fas fa-globe"></i> Public Review Feed</h2>
    <?php if (empty($public_reviews)): ?>
        <p style="color: #777; margin: 0;">No review has been posted yet.</p>
    <?php else: ?>
        <?php foreach ($public_reviews as $r): ?>
            <div style="border: 1px solid #eee; border-radius: 10px; padding: 14px; margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                    <strong><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></strong>
                    <small style="color: #666;"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($r['created_at']))); ?></small>
                </div>
                <div style="color: #f1b400; margin-top: 6px;"><?php echo str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']); ?> (<?php echo (int) $r['rating']; ?>/5)</div>
                <p style="margin: 10px 0 12px 0;"><?php echo nl2br(htmlspecialchars($r['review_text'])); ?></p>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <?php if ((int) $r['user_id'] !== $user_id): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="vote_review" value="1">
                        <input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>">
                        <input type="hidden" name="vote_type" value="like">
                        <button type="submit" class="btn btn-sm <?php echo ($r['my_vote'] === 'like') ? 'btn-success' : 'btn-secondary'; ?>">
                            <i class="fas fa-thumbs-up"></i> Like (<?php echo (int) $r['like_count']; ?>)
                        </button>
                    </form>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="vote_review" value="1">
                        <input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>">
                        <input type="hidden" name="vote_type" value="dislike">
                        <button type="submit" class="btn btn-sm <?php echo ($r['my_vote'] === 'dislike') ? 'btn-danger' : 'btn-secondary'; ?>">
                            <i class="fas fa-thumbs-down"></i> Dislike (<?php echo (int) $r['dislike_count']; ?>)
                        </button>
                    </form>
                    <?php else: ?>
                    <small style="color: #666;">Your review</small>
                    <span style="font-size: 0.9rem; color: #2e7d32;"><i class="fas fa-thumbs-up"></i> <?php echo (int) $r['like_count']; ?></span>
                    <span style="font-size: 0.9rem; color: #c62828;"><i class="fas fa-thumbs-down"></i> <?php echo (int) $r['dislike_count']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2><i class="fas fa-history"></i> My Review Activity</h2>
    <?php if (empty($my_reviews)): ?>
        <p style="color: #777; margin: 0;">You have not submitted any review yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Likes</th>
                    <th>Dislikes</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_reviews as $r): ?>
                <tr>
                    <td><?php echo (int) $r['rating']; ?>/5</td>
                    <td><?php echo nl2br(htmlspecialchars($r['review_text'])); ?></td>
                    <td><?php echo (int) $r['like_count']; ?></td>
                    <td><?php echo (int) $r['dislike_count']; ?></td>
                    <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($r['created_at']))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
