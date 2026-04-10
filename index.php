<?php
/**
 * Homepage for University Bus Booking System
 * Displays dashboard and available routes
 */

require_once 'includes/config.php';

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: pages/login.php");
    exit;
}

// Get user's wallet balance
$user_id = $_SESSION['id'];
$stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();
$balance = $wallet ? $wallet['balance'] : 0;
$_SESSION['balance'] = $balance;

// Fetch all routes from database
$routes = $pdo->query("SELECT * FROM routes ORDER BY name")->fetchAll();

// Get counts for dashboard
$routes_count = count($routes);
$buses_count = $pdo->query("SELECT COUNT(*) FROM buses WHERE active = 1")->fetchColumn();
$bookings_count = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?")->execute([$user_id]) ? $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id")->fetchColumn() : 0;
$students_count = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'student'")->fetchColumn();

require_once 'includes/header.php';
?>

<h1 class="page-title">University Bus Booking System</h1>

<!-- Dashboard Cards -->
<div class="dashboard-cards">
    <div class="dashboard-card">
        <i class="fas fa-route"></i>
        <h3><?php echo $routes_count; ?> Active Routes</h3>
        <p>Across campus and city</p>
    </div>
    <div class="dashboard-card">
        <i class="fas fa-bus"></i>
        <h3><?php echo $buses_count; ?> Buses</h3>
        <p>Serving students</p>
    </div>
    <div class="dashboard-card">
        <i class="fas fa-ticket-alt"></i>
        <h3><?php echo $bookings_count; ?> Bookings</h3>
        <p>This month</p>
    </div>
    <div class="dashboard-card">
        <i class="fas fa-users"></i>
        <h3><?php echo number_format($students_count); ?>+ Students</h3>
        <p>Using our service</p>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="card">
    <h2>Quick Actions</h2>
    <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
        <a href="pages/routes.php" class="btn btn-primary">
            <i class="fas fa-route"></i> View All Routes
        </a>
        <a href="pages/wallet.php" class="btn btn-success">
            <i class="fas fa-wallet"></i> My Wallet
        </a>
        <a href="pages/uber.php" class="btn btn-info" style="background: #17a2b8;">
            <i class="fas fa-car"></i> Uber
        </a>
        <a href="pages/ride-sharing.php" class="btn btn-warning">
            <i class="fas fa-users"></i> Ride Sharing
        </a>
        <a href="pages/leaderboard.php" class="btn btn-primary" style="background: #6f42c1;">
            <i class="fas fa-trophy"></i> Leaderboard
        </a>
    </div>
</div>

<!-- Featured Routes Section -->
<div class="card">
    <h2>Featured Routes</h2>
    <p>Here are some of our most popular routes. Click "View All Routes" to see the complete schedule.</p>
    
    <div class="route-grid">
        <?php 
        // Display only 3 featured routes on the homepage
        $featured_routes = array_slice($routes, 0, 3);
        foreach ($featured_routes as $route): ?>
        <div class="route-card" onclick="window.location.href='pages/buses.php?route_id=<?php echo $route['id']; ?>'">
            <div class="route-name"><?php echo htmlspecialchars($route['name']); ?></div>
            <div class="route-details">
                <i class="fas fa-map-marker-alt"></i>
                <span><?php echo htmlspecialchars($route['start_point']); ?> to <?php echo htmlspecialchars($route['end_point']); ?></span>
            </div>
            <div class="route-details">
                <i class="fas fa-road"></i>
                <span>Distance: <?php echo htmlspecialchars($route['distance']); ?> miles</span>
            </div>
            <div class="route-details">
                <i class="fas fa-clock"></i>
                <span>Estimated Time: <?php echo htmlspecialchars($route['estimated_time']); ?> minutes</span>
            </div>
            <div class="route-details">
                <i class="fas fa-dollar-sign"></i>
                <span>Fare: $<?php echo number_format($route['fare'], 2); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="pages/routes.php" class="btn btn-primary">View All Routes</a>
    </div>
</div>

<!-- Leaderboard Widget -->
<?php
$current_week = date('W');
$current_year = date('Y');
$top_users = $pdo->query("
    SELECT wl.*, u.first_name, u.last_name 
    FROM weekly_leaderboard wl
    JOIN users u ON wl.user_id = u.id
    WHERE wl.week_number = $current_week AND wl.year = $current_year AND wl.rank_position <= 3
    ORDER BY wl.rank_position ASC
")->fetchAll();
?>

<?php if (count($top_users) > 0): ?>
<div class="card">
    <h2><i class="fas fa-trophy"></i> Weekly Leaderboard</h2>
    <div style="display: flex; justify-content: space-around; margin: 20px 0; flex-wrap: wrap; gap: 20px;">
        <?php foreach ($top_users as $user): ?>
        <div style="text-align: center; min-width: 150px;">
            <?php if ($user['rank_position'] == 1): ?>
                <i class="fas fa-crown" style="color: gold; font-size: 3rem;"></i>
                <h3 style="color: gold;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p style="font-size: 1.2rem; color: var(--primary);"><?php echo $user['total_points']; ?> pts</p>
                <p class="status-badge status-active">100% OFF</p>
            <?php elseif ($user['rank_position'] == 2): ?>
                <i class="fas fa-medal" style="color: silver; font-size: 3rem;"></i>
                <h3 style="color: silver;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p style="font-size: 1.2rem; color: var(--primary);"><?php echo $user['total_points']; ?> pts</p>
                <p class="status-badge" style="background: silver;">50% OFF</p>
            <?php elseif ($user['rank_position'] == 3): ?>
                <i class="fas fa-medal" style="color: #cd7f32; font-size: 3rem;"></i>
                <h3 style="color: #cd7f32;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p style="font-size: 1.2rem; color: var(--primary);"><?php echo $user['total_points']; ?> pts</p>
                <p class="status-badge" style="background: #cd7f32;">30% OFF</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="text-align: center;">
        <a href="pages/leaderboard.php" class="btn btn-primary">View Full Leaderboard</a>
    </div>
</div>
<?php endif; ?>
<!-- Social Billboard Link -->
<div class="card" style="margin-top: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div>
            <h2 style="color: white; margin: 0;"><i class="fas fa-bullhorn"></i> Community Billboard</h2>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Join the conversation! See announcements and interact with the community.</p>
        </div>
        <a href="pages/social.php" class="btn btn-light" style="background: white; color: #667eea; padding: 12px 30px;">
            <i class="fas fa-arrow-right"></i> Visit Billboard
        </a>
    </div>
</div>
<?php
require_once 'includes/footer.php';
?>

