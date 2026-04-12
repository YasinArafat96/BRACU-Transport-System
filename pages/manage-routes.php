<?php
require_once '../includes/config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isAdmin($pdo, $_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

// Handle route status toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['route_id']) && isset($_POST['active'])) {
    $route_id = (int)$_POST['route_id'];
    $active = (int)$_POST['active'];
    
    // Update route status
    $stmt = $pdo->prepare("UPDATE routes SET active = ? WHERE id = ?");
    $stmt->execute([$active, $route_id]);
    
    // If route is being deactivated, also deactivate all its buses
    if ($active == 0) {
        $bus_stmt = $pdo->prepare("UPDATE buses SET active = 0 WHERE route_id = ?");
        $bus_stmt->execute([$route_id]);
    }
    
    $_SESSION['success'] = "Route status updated successfully!";
    header("Location: manage-routes.php");
    exit;
}

// Get all routes with their active bus count
$routes_stmt = $pdo->query("
    SELECT r.*, 
           (SELECT COUNT(*) FROM buses WHERE route_id = r.id AND active = 1) as active_buses,
           (SELECT COUNT(*) FROM buses WHERE route_id = r.id) as total_buses
    FROM routes r 
    ORDER BY r.name
");
$routes = $routes_stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="page-title">Manage Routes & Buses</h1>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2><i class="fas fa-route"></i> All Routes</h2>
        <span class="status-badge <?php echo count($routes) > 0 ? 'status-active' : 'status-inactive'; ?>">
            <?php echo count($routes); ?> Routes
        </span>
    </div>
    
    <p>Toggle routes on/off. When a route is off, all its buses are automatically disabled.</p>
    
    <div class="routes-list">
        <?php if (count($routes) > 0): ?>
            <?php foreach ($routes as $route): ?>
            <div class="route-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee;">
                <div class="route-info" style="flex: 1;">
                    <h4><?php echo htmlspecialchars($route['name']); ?></h4>
                    <p style="margin: 5px 0; color: #666;">
                        <?php echo htmlspecialchars($route['start_point']); ?> to <?php echo htmlspecialchars($route['end_point']); ?>
                    </p>
                    <p style="margin: 0; color: #888; font-size: 0.9em;">
                        <span class="status-badge <?php echo $route['active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $route['active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                        </span>
                        • Buses: <?php echo $route['active_buses']; ?>/<?php echo $route['total_buses']; ?> active
                        • Fare: $<?php echo number_format($route['fare'], 2); ?>
                    </p>
                </div>
                
                <div class="route-actions" style="display: flex; align-items: center; gap: 15px;">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="route_id" value="<?php echo $route['id']; ?>">
                        <input type="hidden" name="active" value="<?php echo $route['active'] ? '0' : '1'; ?>">
                        
                        <label class="switch">
                            <input type="checkbox" 
                                <?php echo $route['active'] ? 'checked' : ''; ?> 
                                onchange="this.form.submit()">
                            <span class="slider round"></span>
                        </label>
                        <span style="margin-left: 10px; font-weight: 500;">
                            <?php echo $route['active'] ? 'ON' : 'OFF'; ?>
                        </span>     
                    </form>
                    
                    <a href="manage-buses.php?route_id=<?php echo $route['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-bus"></i> View Buses
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; color: #666;">
                <i class="fas fa-route fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No routes found in the system.</p>
            </div>
        <?php endif; ?>
    </div>
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