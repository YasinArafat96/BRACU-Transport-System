<?php
/**
 * Rewards Store - Redeem points for discounts and perks
 */

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$success = '';
$error = '';

// Handle reward redemption
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_reward'])) {
    $reward_id = (int)$_POST['reward_id'];
    
    // Get reward details
    $reward = $pdo->prepare("SELECT * FROM rewards WHERE id = ? AND is_active = 1");
    $reward->execute([$reward_id]);
    $reward_data = $reward->fetch();
    
    if (!$reward_data) {
        $error = "Reward not found";
    } else {
        // Check user points
        $user = $pdo->prepare("SELECT total_points FROM users WHERE id = ?");
        $user->execute([$user_id]);
        $user_points = $user->fetchColumn();
        
        if ($user_points < $reward_data['points_required']) {
            $error = "You don't have enough points! You need " . $reward_data['points_required'] . " points.";
        } else {
            // Generate unique discount code
            $discount_code = strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . $reward_id . '-' . $user_id;
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $pdo->beginTransaction();
            
            try {
                // Deduct points
                $pdo->prepare("UPDATE users SET total_points = total_points - ? WHERE id = ?")
                    ->execute([$reward_data['points_required'], $user_id]);
                
                // Record in points history (schema-aware)
                points_history_insert($pdo, $user_id, -$reward_data['points_required'], 'redeem', 'Redeemed: ' . $reward_data['name'], $reward_id);
                
                // Create user reward
                $pdo->prepare("INSERT INTO user_rewards (user_id, reward_id, points_spent, discount_code, expires_at) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$user_id, $reward_id, $reward_data['points_required'], $discount_code, $expires_at]);
                
                $pdo->commit();
                
                $_SESSION['success'] = "Reward redeemed successfully! Your code: " . $discount_code;
                header("Location: my-points.php");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to redeem: " . $e->getMessage();
            }
        }
    }
}

// Get all available rewards
$rewards = $pdo->query("SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_required ASC")->fetchAll();

// Get user's current points
$user_points = $pdo->prepare("SELECT total_points FROM users WHERE id = ?");
$user_points->execute([$user_id]);
$current_points = $user_points->fetchColumn();

require_once '../includes/header.php';
?>

<style>
:root {
    --gradient-gold: linear-gradient(135deg, #FFD700, #FFA500);
    --gradient-silver: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    --gradient-bronze: linear-gradient(135deg, #CD7F32, #8B4513);
}

.rewards-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.rewards-header::after {
    content: '⭐';
    position: absolute;
    top: -20px;
    right: -20px;
    font-size: 150px;
    opacity: 0.1;
    transform: rotate(20deg);
}

.points-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
}

.points-display {
    font-size: 3rem;
    font-weight: bold;
    color: #667eea;
}

.points-label {
    color: #666;
    font-size: 1.1rem;
}

.rewards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 30px;
}

.reward-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
}

.reward-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    border-color: #667eea;
}

.reward-card.unaffordable {
    opacity: 0.7;
    filter: grayscale(0.5);
}

.reward-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
}

.reward-name {
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 10px;
    text-align: center;
}

.reward-description {
    color: #666;
    text-align: center;
    margin-bottom: 20px;
    font-size: 0.95rem;
}

.reward-points {
    text-align: center;
    font-size: 1.5rem;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 20px;
}

.reward-points small {
    font-size: 0.9rem;
    color: #999;
    font-weight: normal;
}

.reward-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    color: white;
}

.reward-badge.popular {
    background: #FF6B6B;
}

.reward-badge.best-value {
    background: #4ECDC4;
}

.progress-bar {
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    margin: 15px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transition: width 0.3s;
}

.how-it-works {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 30px;
    margin-top: 40px;
}

.step-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.step {
    text-align: center;
    padding: 20px;
}

.step-number {
    width: 40px;
    height: 40px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto 15px;
}

@media (max-width: 768px) {
    .points-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
}
</style>

<div class="container">
    <!-- Header -->
    <div class="rewards-header">
        <h1 style="font-size: 3rem; margin-bottom: 10px;"><i class="fas fa-gift"></i> Rewards Store</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Redeem your points for awesome rewards and discounts!</p>
    </div>
    
    <!-- Points Display -->
    <div class="points-card">
        <div>
            <span class="points-label">Your Points Balance</span>
            <div class="points-display"><?php echo number_format($current_points); ?> ⭐</div>
        </div>
        <div>
            <a href="my-points.php" class="btn btn-primary" style="padding: 12px 25px;">
                <i class="fas fa-history"></i> View My Rewards
            </a>
            <a href="leaderboard.php" class="btn btn-secondary" style="padding: 12px 25px; margin-left: 10px;">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="notification error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="notification success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <!-- Rewards Grid -->
    <h2><i class="fas fa-star"></i> Available Rewards</h2>
    <div class="rewards-grid">
        <?php foreach ($rewards as $reward): 
            $can_afford = $current_points >= $reward['points_required'];
            $progress = min(100, ($current_points / $reward['points_required']) * 100);
        ?>
        <div class="reward-card <?php echo !$can_afford ? 'unaffordable' : ''; ?>">
            <?php if ($reward['points_required'] <= 100): ?>
                <div class="reward-badge popular">🔥 Popular</div>
            <?php elseif ($reward['points_required'] >= 500): ?>
                <div class="reward-badge best-value">⭐ Best Value</div>
            <?php endif; ?>
            
            <div class="reward-icon" style="background: <?php echo $reward['color']; ?>;">
                <i class="fas <?php echo $reward['icon']; ?>"></i>
            </div>
            
            <div class="reward-name"><?php echo htmlspecialchars($reward['name']); ?></div>
            <div class="reward-description"><?php echo htmlspecialchars($reward['description']); ?></div>
            
            <div class="reward-points">
                <?php echo number_format($reward['points_required']); ?> <small>points</small>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
            </div>
            
            <?php if ($can_afford): ?>
                <form method="post" onsubmit="return confirm('Redeem <?php echo htmlspecialchars($reward['name']); ?> for <?php echo $reward['points_required']; ?> points?');">
                    <input type="hidden" name="redeem_reward" value="1">
                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-gift"></i> Redeem Now
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-secondary btn-block" disabled>
                    Need <?php echo number_format($reward['points_required'] - $current_points); ?> more points
                </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- How It Works -->
    <div class="how-it-works">
        <h2 style="text-align: center;">How It Works</h2>
        <div class="step-grid">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Earn Points</h3>
                <p>Get points for every ride, referral, and activity on the platform</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Choose Reward</h3>
                <p>Browse our rewards store and pick what you want</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Redeem</h3>
                <p>Use your points to get discount codes and special perks</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h3>Enjoy</h3>
                <p>Use your rewards on your next ride and save money!</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>