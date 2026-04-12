<?php
require_once '../includes/config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isAdmin($pdo, $_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

$route_id = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;

// Handle bus status toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bus_id']) && isset($_POST['active'])) {
    $bus_id = (int)$_POST['bus_id'];
    $active = (int)$_POST['active'];
    
    // Update bus status
    $stmt = $pdo->prepare("UPDATE buses SET active = ? WHERE id = ?");
    $stmt->execute([$active, $bus_id]);
    
    $_SESSION['success'] = "Bus status updated successfully!";
    
    // Redirect back to same page
    $redirect_url = "manage-buses.php";
    if ($route_id > 0) {
        $redirect_url .= "?route_id=" . $route_id;
    }
    header("Location: " . $redirect_url);
    exit;
}

// Get route information if specific route is selected
$route = [];
if ($route_id > 0) {
    $route_stmt = $pdo->prepare("SELECT * FROM routes WHERE id = ?");
    $route_stmt->execute([$route_id]);
    $route = $route_stmt->fetch();
}

// Get all buses
if ($route_id > 0) {
    // Buses for specific route
    $buses_stmt = $pdo->prepare("
        SELECT b.*, r.name as route_name, r.active as route_active,
               (SELECT COUNT(*) FROM bookings WHERE bus_id = b.id AND status = 'confirmed') as booked_seats
        FROM buses b 
        JOIN routes r ON b.route_id = r.id 
        WHERE b.route_id = ? 
        ORDER BY b.departure_time
    ");
    $buses_stmt->execute([$route_id]);
} else {
    // All buses across all routes
    $buses_stmt = $pdo->query("
        SELECT b.*, r.name as route_name, r.active as route_active,
               (SELECT COUNT(*) FROM bookings WHERE bus_id = b.id AND status = 'confirmed') as booked_seats
        FROM buses b 
        JOIN routes r ON b.route_id = r.id 
        ORDER BY r.name, b.departure_time
    ");
}
$buses = $buses_stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="page-title">Manage Buses</h1>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>
            <i class="fas fa-bus"></i> 
            <?php echo $route ? htmlspecialchars($route['name']) . ' Buses' : 'All Buses'; ?>
        </h2>
        <span class="status-badge status-active">
            <?php echo count($buses); ?> Buses
        </span>
    </div>

    <?php if ($route_id > 0): ?>
    <div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid <?php echo $route['active'] ? 'var(--secondary)' : 'var(--danger)'; ?>;">
        <h4>Route Status: 
            <span class="status-badge <?php echo $route['active'] ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $route['active'] ? 'ACTIVE' : 'INACTIVE'; ?>
            </span>
        </h4>
        <?php if (!$route['active']): ?>
        <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #666;">
            <i class="fas fa-info-circle"></i> Route is inactive. Buses can still be managed individually.
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (count($buses) > 0): ?>
    <div class="buses-list">
        <?php foreach ($buses as $bus): ?>
        <div class="bus-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee;">
            <div class="bus-info" style="flex: 1;">
                <h4>Bus <?php echo htmlspecialchars($bus['bus_number']); ?></h4>
                <p style="margin: 5px 0; color: #666;">
                    <strong>Route:</strong> <?php echo htmlspecialchars($bus['route_name']); ?><br>
                    <strong>Time:</strong> <?php echo date("h:i A", strtotime($bus['departure_time'])); ?> 
                    → <?php echo date("h:i A", strtotime($bus['arrival_time'])); ?>
                </p>
                <p style="margin: 0; color: #888; font-size: 0.9em;">
                    <span class="status-badge <?php echo $bus['active'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $bus['active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                    </span>
                    • Capacity: <?php echo $bus['capacity']; ?> seats
                    • Booked: <?php echo $bus['booked_seats']; ?>
                    <?php if (!$bus['route_active']): ?>
                    • <span style="color: var(--danger);">Route Inactive</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="bus-actions" style="display: flex; align-items: center; gap: 15px;">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                    <input type="hidden" name="active" value="<?php echo $bus['active'] ? '0' : '1'; ?>">
                    
                    <label class="switch">
                        <input type="checkbox" 
                            <?php echo $bus['active'] ? 'checked' : ''; ?> 
                            onchange="this.form.submit()"
                            <?php echo !$bus['route_active'] ? '' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                    <span style="margin-left: 10px; font-weight: 500;">
                        <?php echo $bus['active'] ? 'ON' : 'OFF'; ?>
                    </span>
                </form>
                
                <a href="manage-routes.php" class="btn btn-primary">
                    <i class="fas fa-route"></i> View Route
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 40px 20px; color: #666;">
        <i class="fas fa-bus fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
        <p>No buses found<?php echo $route_id > 0 ? ' for this route' : ''; ?>.</p>
        <?php if ($route_id > 0): ?>
        <a href="manage-routes.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Routes
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Toggle Switch Styles -->
<style>
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 34px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 26px;
  width: 26px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}

input:checked + .slider {
  background-color: #2196F3;
}

input:focus + .slider {
  box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
  -webkit-transform: translateX(26px);
  -ms-transform: translateX(26px);
  transform: translateX(26px);
}

.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}
</style>

<?php require_once '../includes/footer.php'; ?>