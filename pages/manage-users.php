<?php
require_once '../includes/config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isAdmin($pdo, $_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

// Get filter parameters
$filter_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_route = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;

// Build the query to get users with their bookings
$sql = "
    SELECT 
        u.id as user_id,
        u.student_id,
        u.first_name,
        u.last_name,
        u.email,
        u.user_type,
        COUNT(b.id) as total_bookings,
        SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
        MAX(b.booking_time) as last_booking_date
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    WHERE 1=1
";

$params = [];

if (!empty($filter_user_type)) {
    $sql .= " AND u.user_type = ?";
    $params[] = $filter_user_type;
}

if (!empty($filter_status) && $filter_status !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
}

if ($filter_route > 0) {
    $sql .= " AND b.bus_id IN (SELECT id FROM buses WHERE route_id = ?)";
    $params[] = $filter_route;
}

$sql .= " GROUP BY u.id ORDER BY total_bookings DESC, last_booking_date DESC";

$users_stmt = $pdo->prepare($sql);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll();

// Get all routes for filter dropdown
$routes = $pdo->query("SELECT id, name FROM routes WHERE active = 1 ORDER BY name")->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="page-title">Manage Users & Bookings</h1>

<div class="card">
    <h2><i class="fas fa-filter"></i> Filter Users</h2>
    <form method="get" class="filter-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="form-group">
            <label for="user_type">User Type</label>
            <select id="user_type" name="user_type" class="form-control">
                <option value="">All Types</option>
                <option value="student" <?php echo $filter_user_type === 'student' ? 'selected' : ''; ?>>Students</option>
                <option value="admin" <?php echo $filter_user_type === 'admin' ? 'selected' : ''; ?>>Admins</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="status">Booking Status</label>
            <select id="status" name="status" class="form-control">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="route_id">Route</label>
            <select id="route_id" name="route_id" class="form-control">
                <option value="0">All Routes</option>
                <?php foreach ($routes as $route): ?>
                    <option value="<?php echo $route['id']; ?>" <?php echo $filter_route == $route['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($route['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="manage-users.php" class="btn btn-secondary" style="margin-left: 10px;">Clear</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2><i class="fas fa-users"></i> Users with Bookings</h2>
        <span class="status-badge status-active"><?php echo count($users); ?> Users</span>
    </div>

    <?php if (count($users) > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User Info</th>
                        <th>User Type</th>
                        <th>Total Bookings</th>
                        <th>Booking Stats</th>
                        <th>Last Booking</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                            <small>ID: <?php echo htmlspecialchars($user['student_id']); ?></small><br>
                            <small>Email: <?php echo htmlspecialchars($user['email']); ?></small>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $user['user_type'] === 'admin' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <strong style="font-size: 1.2rem;"><?php echo $user['total_bookings']; ?></strong>
                        </td>
                        <td>
                            <?php if ($user['total_bookings'] > 0): ?>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <?php if ($user['confirmed_bookings'] > 0): ?>
                                        <span class="status-badge status-active" title="Confirmed">✓<?php echo $user['confirmed_bookings']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($user['pending_bookings'] > 0): ?>
                                        <span class="status-badge" style="background: var(--warning);" title="Pending">⏳<?php echo $user['pending_bookings']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($user['cancelled_bookings'] > 0): ?>
                                        <span class="status-badge status-inactive" title="Cancelled">✗<?php echo $user['cancelled_bookings']; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--grey);">No bookings</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['last_booking_date']): ?>
                                <?php echo date("M j, Y", strtotime($user['last_booking_date'])); ?><br>
                                <small><?php echo date("g:i A", strtotime($user['last_booking_date'])); ?></small>
                            <?php else: ?>
                                <span style="color: var(--grey);">Never</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['total_bookings'] > 0): ?>
                                <button class="btn btn-sm btn-primary view-bookings-btn" 
                                        data-user-id="<?php echo $user['user_id']; ?>"
                                        data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                    <i class="fas fa-list"></i> View Bookings
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px 20px; color: #666;">
            <i class="fas fa-users fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No users found with the selected filters.</p>
            <a href="manage-users.php" class="btn btn-primary">Clear Filters</a>
        </div>
    <?php endif; ?>
</div>

<!-- Bookings Modal -->
<div class="modal" id="bookingsModal">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close-modal">&times;</span>
        <h2 id="modalUserName">User Bookings</h2>
        <div id="bookingsList" style="max-height: 400px; overflow-y: auto;">
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-bookings-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const userId = this.getAttribute('data-user-id');
        const userName = this.getAttribute('data-user-name');
        
        document.getElementById('modalUserName').textContent = userName + "'s Bookings";
        document.getElementById('bookingsList').innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading bookings...</p>
            </div>
        `;
        
        fetch(`get-user-bookings.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.bookings.length > 0) {
                        html = `
                            <table style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Route</th>
                                        <th>Bus</th>
                                        <th>Seat</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Fare</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        
                        data.bookings.forEach(booking => {
                            html += `
                                <tr>
                                    <td>${booking.route_name}</td>
                                    <td>${booking.bus_number}</td>
                                    <td>${booking.seat_number}</td>
                                    <td>
                                        <span class="status-badge ${booking.status.toLowerCase()}">
                                            ${booking.status}
                                        </span>
                                    </td>
                                    <td>${new Date(booking.booking_time).toLocaleDateString()}</td>
                                    <td>$${parseFloat(booking.fare).toFixed(2)}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                </tbody>
                            </table>
                        `;
                    } else {
                        html = '<p style="text-align: center; padding: 20px; color: #666;">No bookings found for this user.</p>';
                    }
                    document.getElementById('bookingsList').innerHTML = html;
                } else {
                    document.getElementById('bookingsList').innerHTML = `
                        <p style="text-align: center; padding: 20px; color: var(--danger);">
                            Error loading bookings: ${data.message}
                        </p>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('bookingsList').innerHTML = `
                    <p style="text-align: center; padding: 20px; color: var(--danger);">
                        Error loading bookings. Please try again.
                    </p>
                `;
            });
        
        document.getElementById('bookingsModal').style.display = 'flex';
    });
});

document.querySelector('.close-modal').addEventListener('click', function() {
    document.getElementById('bookingsModal').style.display = 'none';
});

window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('bookingsModal')) {
        document.getElementById('bookingsModal').style.display = 'none';
    }
});
</script>

<style>
.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
table th { background-color: #f8f9fa; font-weight: 600; }
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 500; }
.status-active { background-color: var(--secondary); color: white; }
.status-inactive { background-color: var(--danger); color: white; }
.view-bookings-btn { padding: 6px 12px; font-size: 0.9rem; }
</style>

<?php require_once '../includes/footer.php'; ?>