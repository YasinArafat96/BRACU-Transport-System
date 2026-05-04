
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Redirect admins away from my-bookings page
if (isAdmin($pdo, $_SESSION['id'])) {
    $_SESSION['info'] = "Booking functionality is for students only.";
    header("Location: admin.php");
    exit;
}

$user_id = $_SESSION['id'];

// Get user's full information from database
$user_info = getUserInfo($pdo, $user_id);
if ($user_info) {
    $_SESSION['student_id'] = $user_info['student_id'];
    $_SESSION['first_name'] = $user_info['first_name'];
    $_SESSION['last_name'] = $user_info['last_name'];
}

// Fetch user's bookings with route and bus details
$bookings_stmt = $pdo->prepare("
    SELECT b.*, 
           bus.bus_number, bus.departure_time, bus.arrival_time,
           rt.name as route_name, rt.start_point, rt.end_point, rt.fare
    FROM bookings b
    JOIN buses bus ON b.bus_id = bus.id
    JOIN routes rt ON bus.route_id = rt.id
    WHERE b.user_id = ?
    ORDER BY b.booking_time DESC
");
$bookings_stmt->execute([$user_id]);
$bookings = $bookings_stmt->fetchAll();

require_once '../includes/header.php';
?>

<h1 class="page-title">My Bookings</h1>

<?php if (count($bookings) > 0): ?>
    <div class="bookings-container">
        <?php foreach ($bookings as $booking): 
            $departure_time = strtotime($booking['departure_time']);
            $current_time = time();
            
            if ($current_time >= $departure_time && $booking['status'] === 'confirmed') {
                $status = 'completed';
            } else {
                $status = strtolower($booking['status']);
            }
