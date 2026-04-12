<?php
/**
 * Ride Tracking Page
 * Shows real-time ride information for active rides
 */

require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$ride_id = isset($_GET['ride_id']) ? (int)$_GET['ride_id'] : 0;

// Handle demo mode actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['demo_action'])) {
    $ride_id = (int)$_POST['ride_id'];
    
    // Get the ride to check if it's a demo ride
    $check_ride = $pdo->prepare("SELECT * FROM uber_rides WHERE id = ?");
    $check_ride->execute([$ride_id]);
    $current_ride = $check_ride->fetch();
    
    // If this is a demo ride (no real rider), we need to handle the progression
    if ($current_ride && $current_ride['rider_id'] == 0) {
        
        if ($_POST['demo_action'] == 'accept_ride') {
            // Get demo rider ID
            $demo_rider = $pdo->query("SELECT r.id FROM riders r JOIN users u ON r.user_id = u.id WHERE u.student_id = 'DEMO001'")->fetch();
            
            if ($demo_rider) {
                // Update ride with demo rider and set status to accepted
                $update = $pdo->prepare("UPDATE uber_rides SET rider_id = ?, status = 'accepted', start_time = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$demo_rider['id'], $ride_id]);
                
                $_SESSION['success'] = "Demo rider is on the way to pick you up!";
            } else {
                $_SESSION['error'] = "Demo rider not found. Please run the setup script.";
            }
            
            header("Location: ride-tracking.php?ride_id=" . $ride_id);
            exit;
        }
        
        if ($_POST['demo_action'] == 'rider_arrived') {
            // Update ride status to started (rider arrived, trip started)
            $update = $pdo->prepare("UPDATE uber_rides SET status = 'started' WHERE id = ?");
            $update->execute([$ride_id]);
            
            $_SESSION['success'] = "You're now on the way to your destination!";
            header("Location: ride-tracking.php?ride_id=" . $ride_id);
            exit;
        }
        
        if ($_POST['demo_action'] == 'complete_ride') {
            $pdo->beginTransaction();
            
            try {
                // Update ride status
                $update = $pdo->prepare("UPDATE uber_rides SET status = 'completed', end_time = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$ride_id]);
                
                // Get ride details for payment
                $ride = $pdo->prepare("SELECT * FROM uber_rides WHERE id = ?");
                $ride->execute([$ride_id]);
                $ride_data = $ride->fetch();
                
                // Deduct fare from passenger's wallet
                $wallet = $pdo->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
                $wallet->execute([$ride_data['fare'], $ride_data['passenger_id']]);
                
                // Record transaction
                $trans = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', 'Uber ride payment (Demo)')");
                $trans->execute([$ride_data['passenger_id'], $ride_data['fare']]);
                
                // Award points
                $points = 10;
                awardPoints($pdo, $ride_data['passenger_id'], $points, 'uber_passenger', 'Uber demo ride completed', $ride_id);
                
                $pdo->commit();
                
                $_SESSION['completed_ride'] = $ride_id;
                $_SESSION['success'] = "Trip completed! You've arrived at your destination.";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Failed to complete ride: " . $e->getMessage();
            }
            
            header("Location: ride-tracking.php?ride_id=" . $ride_id . "&completed=1");
            exit;
        }
    }
}

if ($ride_id === 0) {
    $_SESSION['error'] = "No ride specified";
    header("Location: uber.php");
    exit;
}

// Get ride details with demo rider info if applicable
$ride_stmt = $pdo->prepare("
    SELECT ur.*, 
           r.user_id as rider_user_id,
           r.vehicle_type, r.vehicle_model, r.vehicle_number,
           ru.first_name as rider_first_name, ru.last_name as rider_last_name, ru.phone as rider_phone,
           pu.first_name as passenger_first_name, pu.last_name as passenger_last_name, pu.phone as passenger_phone
    FROM uber_rides ur
    LEFT JOIN riders r ON ur.rider_id = r.id
    LEFT JOIN users ru ON r.user_id = ru.id
    JOIN users pu ON ur.passenger_id = pu.id
    WHERE ur.id = ? AND (ur.passenger_id = ? OR r.user_id = ?)
");
$ride_stmt->execute([$ride_id, $user_id, $user_id]);
$ride = $ride_stmt->fetch();

if (!$ride) {
    $_SESSION['error'] = "Ride not found";
    header("Location: uber.php");
    exit;
}

// Determine user role
$user_role = ($ride['passenger_id'] == $user_id) ? 'passenger' : 'rider';

// Check if this is a demo ride (no real rider name)
$is_demo_ride = ($ride['rider_id'] > 0 && $ride['rider_first_name'] == 'Demo' && $ride['rider_last_name'] == 'Rider');

require_once '../includes/header.php';
?>

<style>
:root {
    --uber-black: #000000;
    --uber-green: #06C167;
    --demo-purple: #9b59b6;
    --waiting-orange: #f39c12;
    --arrived-blue: #3498db;
    --travel-green: #2ecc71;
}

.ride-header {
    background: linear-gradient(135deg, #000000, #1a1a1a);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.ride-status-badge {
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: bold;
    font-size: 1.1rem;
}

.ride-status-badge.requested { background: #f39c12; color: white; }
.ride-status-badge.accepted { background: #3498db; color: white; }
.ride-status-badge.started { background: #2ecc71; color: white; }
.ride-status-badge.completed { background: #95a5a6; color: white; }

.demo-badge {
    background: var(--demo-purple);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    display: inline-block;
    margin-left: 10px;
}

.map-container {
    height: 300px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.mini-map {
    width: 100%;
    height: 100%;
    position: relative;
}

.map-marker {
    position: absolute;
    width: 20px;
    height: 20px;
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(0,0,0,0.3);
}

.map-marker.pickup {
    background: #f39c12;
    left: 30%;
    top: 40%;
    animation: pulse 2s infinite;
}

.map-marker.rider {
    background: var(--uber-green);
    box-shadow: 0 0 20px rgba(6, 193, 103, 0.5);
}

.map-marker.demo-rider {
    background: var(--demo-purple);
    box-shadow: 0 0 20px rgba(155, 89, 182, 0.5);
}

.map-marker.rider.accepted {
    left: 20%;
    top: 30%;
    animation: approachPickup 3s infinite alternate;
}

.map-marker.rider.started {
    left: 45%;
    top: 35%;
    animation: travelToDestination 5s infinite alternate;
}

.map-marker.destination {
    background: #e74c3c;
    left: 70%;
    top: 60%;
    animation: pulse 2s infinite;
}

.map-route {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: repeating-linear-gradient(90deg, transparent, transparent 20px, rgba(255,255,255,0.3) 20px, rgba(255,255,255,0.3) 40px);
    opacity: 0.3;
    pointer-events: none;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.5); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes approachPickup {
    0% { left: 15%; top: 25%; }
    100% { left: 30%; top: 40%; }
}

@keyframes travelToDestination {
    0% { left: 30%; top: 40%; }
    100% { left: 70%; top: 60%; }
}

.ride-info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #666;
    font-weight: 500;
}

.info-value {
    font-weight: bold;
    color: #333;
}

.stage-indicator {
    display: flex;
    justify-content: space-between;
    margin: 30px 0;
    position: relative;
}

.stage-indicator::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 2px;
    background: #ddd;
    z-index: 1;
}

.stage {
    position: relative;
    z-index: 2;
    background: white;
    text-align: center;
    flex: 1;
}

.stage-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ddd;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    transition: all 0.3s;
}

.stage.active .stage-icon {
    background: var(--uber-green);
    transform: scale(1.1);
    box-shadow: 0 0 20px rgba(6, 193, 103, 0.3);
}

.stage.completed .stage-icon {
    background: var(--uber-green);
}

.stage.waiting .stage-icon {
    background: var(--waiting-orange);
    animation: pulse 2s infinite;
}

.stage-label {
    font-size: 0.9rem;
    color: #666;
}

.stage.active .stage-label {
    color: var(--uber-green);
    font-weight: bold;
}

.driver-info {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin: 20px 0;
}

.driver-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--uber-green), #0a8043);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
}

.driver-avatar.demo {
    background: linear-gradient(135deg, var(--demo-purple), #8e44ad);
    animation: pulse 2s infinite;
}

.driver-details h3 {
    margin: 0;
    font-size: 1.2rem;
}

.driver-details p {
    margin: 5px 0 0;
    color: #666;
}

.ride-timer {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--uber-green);
    text-align: center;
    padding: 20px;
    background: linear-gradient(135deg, #f6f9fc, #e9f2f9);
    border-radius: 10px;
    margin: 20px 0;
}

.eta-message {
    text-align: center;
    font-size: 1.1rem;
    color: #666;
    margin: 10px 0;
}

.eta-message i {
    margin-right: 5px;
}

.progress-container {
    height: 8px;
    background: #eee;
    border-radius: 4px;
    margin: 20px 0;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--uber-green);
    width: 0%;
    transition: width 1s;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.action-btn {
    flex: 1;
    min-width: 200px;
    padding: 15px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.action-btn.primary {
    background: var(--uber-green);
    color: white;
}

.action-btn.secondary {
    background: #e74c3c;
    color: white;
}

.action-btn.demo {
    background: var(--demo-purple);
    color: white;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.demo-section {
    background: linear-gradient(135deg, #f8f0ff, #f3e5ff);
    border: 2px dashed var(--demo-purple);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    text-align: center;
}

.demo-title {
    color: var(--demo-purple);
    font-size: 1.3rem;
    margin-bottom: 15px;
}

.demo-title i {
    font-size: 2rem;
}

.trip-summary {
    background: linear-gradient(135deg, #f6f9fc, #e9f2f9);
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    margin: 20px 0;
}

.trip-summary i {
    font-size: 4rem;
    color: var(--uber-green);
    margin-bottom: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    font-size: 1.1rem;
}

.summary-row.total {
    font-size: 1.3rem;
    font-weight: bold;
    color: var(--uber-green);
    border-bottom: none;
}

.payment-methods {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 25px 0;
    flex-wrap: wrap;
}

.payment-method {
    background: white;
    border: 2px solid #ddd;
    border-radius: 10px;
    padding: 15px 25px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.payment-method:hover {
    border-color: var(--uber-green);
    transform: translateY(-2px);
}

.payment-method.selected {
    border-color: var(--uber-green);
    background: #f0fff0;
}

.payment-method i {
    font-size: 1.5rem;
    color: var(--uber-green);
}

.live-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.live-dot {
    width: 10px;
    height: 10px;
    background: #2ecc71;
    border-radius: 50%;
    animation: livePulse 1.5s infinite;
}

@keyframes livePulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.5); }
    100% { opacity: 1; transform: scale(1); }
}

.back-button {
    display: inline-block;
    padding: 10px 20px;
    background: #f8f9fa;
    color: #333;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.back-button:hover {
    background: #e9ecef;
    transform: translateX(-5px);
}

@media (max-width: 768px) {
    .ride-header {
        flex-direction: column;
        text-align: center;
    }
    
    .driver-info {
        flex-direction: column;
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
    }
    
    .stage-label {
        font-size: 0.7rem;
    }
}
</style>

<div class="container">
    <!-- Back Button -->
    <a href="uber.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Uber
    </a>

    <!-- Ride Header -->
    <div class="ride-header">
        <div>
            <h1 style="margin: 0;">
                <i class="fas fa-taxi"></i> Ride #<?php echo $ride['id']; ?>
                <?php if($is_demo_ride): ?>
                    <span class="demo-badge"><i class="fas fa-robot"></i> DEMO MODE</span>
                <?php endif; ?>
            </h1>
            <div class="live-indicator" style="margin-top: 10px;">
                <span class="live-dot"></span>
                <span>Live Tracking Active</span>
            </div>
        </div>
        <div class="ride-status-badge <?php echo $ride['status']; ?>">
            <?php 
            if($ride['status'] == 'requested') echo '⏳ Searching for rider...';
            elseif($ride['status'] == 'accepted') echo '✅ Rider on the way to pick you up';
            elseif($ride['status'] == 'started') echo '🚗 On the way to destination';
            elseif($ride['status'] == 'completed') echo '🏁 Ride Completed';
            ?>
        </div>
    </div>

    <!-- Journey Stage Indicator -->
    <div class="ride-info-card">
        <div class="stage-indicator">
            <div class="stage <?php echo $ride['status'] == 'requested' ? 'waiting' : ($ride['status'] != 'requested' ? 'completed' : ''); ?>">
                <div class="stage-icon <?php echo $ride['status'] == 'requested' ? 'waiting' : ''; ?>">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stage-label">Searching</div>
            </div>
            <div class="stage <?php echo $ride['status'] == 'accepted' ? 'active' : ($ride['status'] == 'started' || $ride['status'] == 'completed' ? 'completed' : ''); ?>">
                <div class="stage-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="stage-label">Rider Approaching</div>
            </div>
            <div class="stage <?php echo $ride['status'] == 'started' ? 'active' : ($ride['status'] == 'completed' ? 'completed' : ''); ?>">
                <div class="stage-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stage-label">En Route</div>
            </div>
            <div class="stage <?php echo $ride['status'] == 'completed' ? 'completed' : ''; ?>">
                <div class="stage-icon">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <div class="stage-label">Completed</div>
            </div>
        </div>
    </div>

    <!-- Mini Map with Animated Markers -->
    <div class="map-container">
        <div class="mini-map">
            <div class="map-route"></div>
            <div class="map-marker pickup" title="Pickup location"></div>
            
            <?php if($ride['status'] == 'accepted'): ?>
                <div class="map-marker rider <?php echo $is_demo_ride ? 'demo-rider' : ''; ?> accepted" title="Rider approaching"></div>
            <?php elseif($ride['status'] == 'started'): ?>
                <div class="map-marker rider <?php echo $is_demo_ride ? 'demo-rider' : ''; ?> started" title="Traveling to destination"></div>
            <?php endif; ?>
            
            <div class="map-marker destination" title="Destination"></div>
        </div>
    </div>

    <!-- DEMO MODE SECTION - Interactive Journey -->
    <?php if($user_role == 'passenger' && $is_demo_ride): ?>
        
        <?php if($ride['status'] == 'requested'): ?>
            <!-- Stage 1: Searching for rider -->
            <div class="demo-section">
                <div class="demo-title">
                    <i class="fas fa-robot"></i> Demo Mode
                </div>
                <p style="margin-bottom: 20px; font-size: 1.1rem;">No real riders available. Experience a complete demo ride!</p>
                
                <form method="post">
                    <input type="hidden" name="demo_action" value="accept_ride">
                    <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                    <button type="submit" class="action-btn demo" style="padding: 15px 50px; font-size: 1.2rem;">
                        <i class="fas fa-play"></i> Start Demo Ride
                    </button>
                </form>
            </div>
            
        <?php elseif($ride['status'] == 'accepted'): ?>
            <!-- Stage 2: Rider approaching -->
            <div class="demo-section">
                <div class="demo-title">
                    <i class="fas fa-robot"></i> Rider Approaching
                </div>
                
                <div style="display: flex; align-items: center; gap: 20px; margin: 20px 0; background: white; padding: 20px; border-radius: 10px;">
                    <div class="driver-avatar demo" style="width: 80px; height: 80px; font-size: 2rem;">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div style="text-align: left;">
                        <h3 style="margin: 0 0 10px 0;">Demo Rider</h3>
                        <p><i class="fas fa-car"></i> Toyota Prius 2024 - White</p>
                        <p><i class="fas fa-id-card"></i> DEMO-1234</p>
                        <p class="eta-message" style="margin: 10px 0 0 0; text-align: left;">
                            <i class="fas fa-clock"></i> Arriving in 2 minutes
                        </p>
                    </div>
                </div>
                
                <div class="progress-container">
                    <div class="progress-bar" style="width: 30%;"></div>
                </div>
                
                <p><i class="fas fa-map-marker-alt"></i> Rider is 1.5 km away from your location</p>
                
                <form method="post" style="margin-top: 20px;">
                    <input type="hidden" name="demo_action" value="rider_arrived">
                    <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                    <button type="submit" class="action-btn primary" style="padding: 15px 40px;">
                        <i class="fas fa-hand-peace"></i> I'm Picked Up - Start Trip
                    </button>
                </form>
            </div>
            
        <?php elseif($ride['status'] == 'started'): ?>
            <!-- Stage 3: Traveling to destination -->
            <div class="demo-section">
                <div class="demo-title">
                    <i class="fas fa-robot"></i> En Route to Destination
                </div>
                
                <div style="display: flex; align-items: center; gap: 20px; margin: 20px 0; background: white; padding: 20px; border-radius: 10px;">
                    <div class="driver-avatar demo">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div style="text-align: left;">
                        <h3 style="margin: 0;">Demo Rider</h3>
                        <p><i class="fas fa-star" style="color: gold;"></i> 4.9 • 1,234 rides</p>
                    </div>
                </div>
                
                <div class="ride-timer" id="rideTimer">00:00</div>
                
                <div class="progress-container">
                    <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
                </div>
                
                <p class="eta-message" id="etaMessage">
                    <i class="fas fa-clock"></i> Traveling to destination...
                </p>
                
                <div style="background: white; border-radius: 10px; padding: 15px; margin: 20px 0;">
                    <div class="info-row">
                        <span><i class="fas fa-road"></i> Distance remaining:</span>
                        <span id="distanceRemaining"><?php echo $ride['distance_km'] ?? '2.5'; ?> km</span>
                    </div>
                    <div class="info-row">
                        <span><i class="fas fa-dollar-sign"></i> Current fare:</span>
                        <span id="currentFare">$<?php echo number_format($ride['fare'], 2); ?></span>
                    </div>
                </div>
                
                <form method="post">
                    <input type="hidden" name="demo_action" value="complete_ride">
                    <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                    <button type="submit" class="action-btn demo" onclick="return confirm('Arrive at destination and complete the ride?')" style="padding: 15px 40px;">
                        <i class="fas fa-flag-checkered"></i> Arrive at Destination
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>

    <!-- Timer and Progress (for started rides) -->
    <?php if($ride['status'] == 'started' && !$is_demo_ride): ?>
    <div class="ride-info-card">
        <div class="ride-timer" id="rideTimer">00:00</div>
        <div class="progress-container">
            <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
        </div>
        <p class="eta-message" id="etaMessage">
            <i class="fas fa-clock"></i> Traveling to destination
        </p>
    </div>
    <?php endif; ?>

    <!-- Driver/Passenger Info -->
    <div class="ride-info-card">
        <h2><i class="fas fa-user-circle"></i> 
            <?php 
            if($is_demo_ride && $user_role == 'passenger') {
                echo 'Your Driver (Demo)';
            } else {
                echo $user_role == 'passenger' ? 'Your Driver' : 'Your Passenger';
            }
            ?>
        </h2>
        
        <div class="driver-info">
            <div class="driver-avatar <?php echo ($is_demo_ride && $user_role == 'passenger') ? 'demo' : ''; ?>">
                <?php 
                if($is_demo_ride && $user_role == 'passenger') {
                    echo '<i class="fas fa-robot"></i>';
                } else {
                    $name = $user_role == 'passenger' ? $ride['rider_first_name'] : $ride['passenger_first_name'];
                    echo strtoupper(substr($name, 0, 1));
                }
                ?>
            </div>
            <div class="driver-details">
                <h3>
                    <?php 
                    if($is_demo_ride && $user_role == 'passenger') {
                        echo 'Demo Rider';
                    } elseif($user_role == 'passenger') {
                        echo htmlspecialchars($ride['rider_first_name'] . ' ' . $ride['rider_last_name']);
                    } else {
                        echo htmlspecialchars($ride['passenger_first_name'] . ' ' . $ride['passenger_last_name']);
                    }
                    ?>
                </h3>
                <p><i class="fas fa-phone"></i> 
                    <?php 
                    if($is_demo_ride && $user_role == 'passenger') {
                        echo '+880 1XXX-XXXXXX (Demo)';
                    } else {
                        echo $user_role == 'passenger' ? ($ride['rider_phone'] ?? '+880 1XXXXXXXXX') : ($ride['passenger_phone'] ?? '+880 1XXXXXXXXX');
                    }
                    ?>
                </p>
            </div>
        </div>

        <?php if($user_role == 'passenger' && $ride['status'] != 'requested'): ?>
        <div style="margin-top: 15px;">
            <p><i class="fas fa-car"></i> <strong>Vehicle:</strong> 
                <?php 
                if($is_demo_ride) {
                    echo 'Toyota Prius 2024 - White';
                } else {
                    echo ucfirst($ride['vehicle_type']) . ' - ' . $ride['vehicle_model']; 
                }
                ?>
            </p>
            <p><i class="fas fa-id-card"></i> <strong>Number:</strong> 
                <?php echo $is_demo_ride ? 'DEMO-1234' : $ride['vehicle_number']; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Ride Details -->
    <div class="ride-info-card">
        <h2><i class="fas fa-route"></i> Trip Details</h2>
        
        <div class="info-row">
            <span class="info-label"><i class="fas fa-circle" style="color: #f39c12;"></i> Pickup</span>
            <span class="info-value"><?php echo htmlspecialchars($ride['pickup_location']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label"><i class="fas fa-flag-checkered" style="color: #e74c3c;"></i> Destination</span>
            <span class="info-value"><?php echo htmlspecialchars($ride['dropoff_location']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label"><i class="fas fa-road"></i> Distance</span>
            <span class="info-value"><?php echo $ride['distance_km'] ?? '2.5'; ?> km</span>
        </div>
        
        <div class="info-row">
            <span class="info-label"><i class="fas fa-clock"></i> Estimated Time</span>
            <span class="info-value"><?php echo isset($ride['distance_km']) ? round($ride['distance_km'] * 3) : '8'; ?> mins</span>
        </div>
        
        <div class="info-row">
            <span class="info-label"><i class="fas fa-dollar-sign"></i> Fare</span>
            <span class="info-value" style="font-size: 1.3rem; color: var(--uber-green);">$<?php echo number_format($ride['fare'], 2); ?></span>
        </div>
    </div>

    <!-- Completed Ride Summary -->
    <?php if($ride['status'] == 'completed'): ?>
    <div class="trip-summary">
        <i class="fas fa-check-circle"></i>
        <h2>Trip Complete!</h2>
        <p>Thanks for riding with <?php echo $is_demo_ride ? 'Demo Rider' : htmlspecialchars($ride['rider_first_name']); ?></p>
        
        <div style="margin: 30px 0;">
            <div class="summary-row">
                <span>Pickup:</span>
                <span><?php echo htmlspecialchars($ride['pickup_location']); ?></span>
            </div>
            <div class="summary-row">
                <span>Destination:</span>
                <span><?php echo htmlspecialchars($ride['dropoff_location']); ?></span>
            </div>
            <div class="summary-row">
                <span>Distance:</span>
                <span><?php echo $ride['distance_km'] ?? '2.5'; ?> km</span>
            </div>
            <div class="summary-row">
                <span>Time:</span>
                <span>8 minutes</span>
            </div>
            <div class="summary-row">
                <span>Base fare:</span>
                <span>$3.00</span>
            </div>
            <div class="summary-row">
                <span>Distance charge:</span>
                <span>$<?php echo number_format(($ride['distance_km'] ?? 2.5) * 2, 2); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total:</span>
                <span>$<?php echo number_format($ride['fare'], 2); ?></span>
            </div>
        </div>
        
        <h3>Payment</h3>
        <p style="color: #666; margin-bottom: 20px;">Amount deducted from your wallet</p>
        
        <div class="payment-methods">
            <div class="payment-method selected">
                <i class="fas fa-wallet"></i>
                <span>Wallet Balance: $<?php echo number_format($_SESSION['balance'] ?? 0, 2); ?></span>
            </div>
        </div>
        
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
            <a href="uber.php" class="action-btn primary" style="text-decoration: none;">
                <i class="fas fa-taxi"></i> Book Another Ride
            </a>
        </div>
        
        <p style="margin-top: 20px; color: #999;"><i class="fas fa-star"></i> You earned 10 points for this ride!</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Timer functionality for demo ride
<?php if($is_demo_ride && $ride['status'] == 'started'): ?>
let seconds = 0;
let timerInterval;
let distanceRemaining = <?php echo $ride['distance_km'] ?? 2.5; ?>;
let totalDistance = distanceRemaining;

function startTimer() {
    timerInterval = setInterval(function() {
        seconds++;
        let mins = Math.floor(seconds / 60);
        let secs = seconds % 60;
        document.getElementById('rideTimer').textContent = 
            (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs);
        
        // Update progress bar and distance
        let totalTime = 480; // 8 minutes in seconds
        let progress = Math.min(100, (seconds / totalTime) * 100);
        document.getElementById('progressBar').style.width = progress + '%';
        
        let remainingDist = totalDistance * (1 - progress/100);
        if (document.getElementById('distanceRemaining')) {
            document.getElementById('distanceRemaining').textContent = remainingDist.toFixed(1) + ' km';
        }
        
        // Update ETA message
        let etaMsg = document.getElementById('etaMessage');
        if (etaMsg) {
            if (progress < 30) {
                etaMsg.innerHTML = '<i class="fas fa-clock"></i> Just started, ' + remainingDist.toFixed(1) + ' km to destination';
            } else if (progress < 60) {
                etaMsg.innerHTML = '<i class="fas fa-map-marker-alt"></i> Halfway there! ' + remainingDist.toFixed(1) + ' km remaining';
            } else if (progress < 90) {
                etaMsg.innerHTML = '<i class="fas fa-flag-checkered"></i> Almost there! ' + remainingDist.toFixed(1) + ' km to go';
            } else {
                etaMsg.innerHTML = '<i class="fas fa-check-circle"></i> Arriving at destination';
            }
        }
    }, 1000);
}

// Start the timer
startTimer();

// Clean up timer on page unload
window.addEventListener('beforeunload', function() {
    if(timerInterval) {
        clearInterval(timerInterval);
    }
});
<?php endif; ?>

// Auto refresh for status updates (every 5 seconds for demo rides)
<?php if($ride['status'] != 'completed' && $is_demo_ride): ?>
setTimeout(function() {
    location.reload();
}, 5000);
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>