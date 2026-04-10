<?php
/**
 * Admin Dashboard page for University Bus Booking System
 * Administrative functions and statistics
 */

require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isAdmin($pdo, $_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

// Get real statistics from database
try {
    // Total users
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Total buses
    $stats['total_buses'] = $pdo->query("SELECT COUNT(*) FROM buses")->fetchColumn();
    
    // Today's bookings
    $stats['today_bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(booking_time) = DATE('now')")->fetchColumn();
    
    // Total revenue (sum of all fares from confirmed bookings)
    $revenue_stmt = $pdo->query("
        SELECT COALESCE(SUM(r.fare), 0) as total 
        FROM bookings b 
        JOIN buses bus ON b.bus_id = bus.id 
        JOIN routes r ON bus.route_id = r.id 
        WHERE b.status = 'confirmed'
    ");
    $stats['total_revenue'] = $revenue_stmt->fetchColumn();
    
    // Recent activity (last 5 bookings)
    $recent_stmt = $pdo->query("
        SELECT b.*, u.first_name, u.last_name, bus.bus_number 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN buses bus ON b.bus_id = bus.id 
        ORDER BY b.booking_time DESC 
        LIMIT 5
    ");
    $recent_activity = $recent_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $stats = ['total_users' => 0, 'total_buses' => 0, 'today_bookings' => 0, 'total_revenue' => 0];
    $recent_activity = [];
}

require_once '../includes/header.php';
?>

<h1 class="page-title">Admin Dashboard <i class="fas fa-shield-alt"></i></h1>

<div class="dashboard-cards">
    <div class="dashboard-card">
        <i class="fas fa-users"></i>
        <h3><?php echo $stats['total_users']; ?></h3>
        <p>Total Users</p>
    </div>
    <div class="dashboard-card">
        <i class="fas fa-bus"></i>
        <h3><?php echo $stats['total_buses']; ?></h3>
        <p>Active Buses</p>
    </div>
    <div class="dashboard-card">
        <i class="fas fa-ticket-alt"></i>
        <h3><?php echo $stats['today_bookings']; ?></h3>
        <p>Today's Bookings</p>
    </div>
    <div class="dashboard-card">
        <i class="fas fa-money-bill-wave"></i>
        <h3>$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
        <p>Total Revenue</p>
    </div>
</div>

<div class="card">
    <h2><i class="fas fa-cog"></i> Admin Actions</h2>
    <div class="quick-actions">
        <a href="manage-users.php" class="btn btn-primary">
            <i class="fas fa-user-cog"></i> Manage Users
        </a>
        <a href="manage-routes.php" class="btn btn-success">
            <i class="fas fa-route"></i> Manage Routes
        </a>
        <a href="manage-buses.php" class="btn btn-warning">
            <i class="fas fa-bus"></i> Manage Buses
        </a>
        <a href="reports.php" class="btn btn-info">
            <i class="fas fa-chart-bar"></i> View Reports
        </a>
    </div>
</div>

<div class="card">
    <h2><i class="fas fa-history"></i> Recent Activity</h2>
    <?php if (!empty($recent_activity)): ?>
        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>User</th>
                    <th>Bus</th>
                    <th>Seat</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_activity as $activity): ?>
                <tr>
                    <td>#<?php echo $activity['id']; ?></td>
                    <td><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($activity['bus_number']); ?></td>
                    <td><?php echo $activity['seat_number']; ?></td>
                    <td>
                        <span class="booking-status <?php echo strtolower($activity['status']); ?>">
                            <?php echo ucfirst($activity['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date("M j, g:i A", strtotime($activity['booking_time'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No recent activity found.</p>
    <?php endif; ?>
</div>

<style>
.booking-status { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
.booking-status.confirmed { background-color: var(--secondary); color: white; }
.booking-status.pending { background-color: var(--warning); color: white; }
.booking-status.cancelled { background-color: var(--danger); color: white; }
</style>

<?php require_once '../includes/footer.php'; ?>