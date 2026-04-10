<?php
/**
 * Installation script - Run this once to set up the SQLite database
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Installation</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
    .success { color: green; }
    .error { color: red; }
    .box { border: 1px solid #ccc; padding: 20px; margin: 10px 0; border-radius: 5px; }
</style>";
echo "</head><body>";
echo "<h1>University Transport System - Installation</h1>";

try {
    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id TEXT UNIQUE NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            phone TEXT,
            user_type TEXT DEFAULT 'student',
            total_points INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p class='success'>✓ Users table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS routes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            start_point TEXT NOT NULL,
            end_point TEXT NOT NULL,
            distance REAL,
            estimated_time INTEGER,
            fare REAL NOT NULL DEFAULT 100,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p class='success'>✓ Routes table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            route_id INTEGER NOT NULL,
            bus_number TEXT NOT NULL,
            capacity INTEGER NOT NULL DEFAULT 40,
            departure_time TEXT NOT NULL,
            arrival_time TEXT NOT NULL,
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Buses table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            bus_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL,
            booking_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT DEFAULT 'pending',
            points_earned INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Bookings table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            balance REAL DEFAULT 0,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Wallet table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            type TEXT NOT NULL,
            description TEXT,
            transaction_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Transactions table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT NOT NULL,
            expires DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p class='success'>✓ Password resets table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS riders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            vehicle_type TEXT NOT NULL,
            vehicle_model TEXT,
            vehicle_number TEXT,
            license_number TEXT,
            is_verified INTEGER DEFAULT 0,
            is_available INTEGER DEFAULT 1,
            current_latitude REAL,
            current_longitude REAL,
            last_location_update DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id)
        )
    ");
    echo "<p class='success'>✓ Riders table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS uber_rides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rider_id INTEGER NOT NULL,
            passenger_id INTEGER NOT NULL,
            pickup_location TEXT NOT NULL,
            dropoff_location TEXT NOT NULL,
            pickup_latitude REAL,
            pickup_longitude REAL,
            dropoff_latitude REAL,
            dropoff_longitude REAL,
            fare REAL NOT NULL,
            status TEXT DEFAULT 'requested',
            booking_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            start_time DATETIME,
            end_time DATETIME,
            distance_km REAL,
            points_earned INTEGER DEFAULT 0,
            FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
            FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Uber rides table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ride_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            creator_id INTEGER NOT NULL,
            vehicle_type TEXT NOT NULL,
            from_location TEXT NOT NULL,
            to_location TEXT NOT NULL,
            from_latitude REAL,
            from_longitude REAL,
            to_latitude REAL,
            to_longitude REAL,
            departure_time DATETIME NOT NULL,
            total_seats INTEGER NOT NULL,
            available_seats INTEGER NOT NULL,
            fare_per_person REAL NOT NULL,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Ride shares table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ride_share_participants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ride_share_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            seats_booked INTEGER NOT NULL DEFAULT 1,
            booking_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT DEFAULT 'confirmed',
            payment_status TEXT DEFAULT 'pending',
            points_earned INTEGER DEFAULT 0,
            FOREIGN KEY (ride_share_id) REFERENCES ride_shares(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, ride_share_id)
        )
    ");
    echo "<p class='success'>✓ Ride share participants table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS points_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            points INTEGER NOT NULL,
            source_type TEXT NOT NULL,
            source_id INTEGER NOT NULL,
            earned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            week_number INTEGER NOT NULL,
            year INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Points history table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS weekly_leaderboard (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            week_number INTEGER NOT NULL,
            year INTEGER NOT NULL,
            total_points INTEGER NOT NULL,
            rank_position INTEGER,
            voucher_type TEXT DEFAULT 'none',
            voucher_claimed INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, week_number, year)
        )
    ");
    echo "<p class='success'>✓ Weekly leaderboard table created</p>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vouchers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            voucher_code TEXT UNIQUE NOT NULL,
            discount_type TEXT DEFAULT 'percentage',
            discount_value INTEGER NOT NULL,
            week_number INTEGER NOT NULL,
            year INTEGER NOT NULL,
            is_used INTEGER DEFAULT 0,
            expiry_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p class='success'>✓ Vouchers table created</p>";

    // Insert sample data if tables are empty
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    if ($count == 0) {
        // Sample routes
        $routes = [
            ['Campus - Mirpur', 'University Main Gate', 'Mirpur Station', 5.2, 25, 100],
            ['Campus - Uttara', 'University Second Gate', 'Uttara Metro', 3.8, 20, 100],
            ['Campus - Dhanmondi', 'University Main Gate', 'Dhanmondi 32', 6.0, 30, 100],
            ['Campus - Banani', 'University Second Gate', 'Banani Circle', 4.0, 18, 100],
            ['Campus - Gulshan', 'University Main Gate', 'Gulshan 1', 4.8, 22, 100],
            ['Campus - Motijheel', 'University Second Gate', 'Motijheel', 7.5, 35, 100],
            ['Campus - Gazipur', 'Science Building', 'Gazipur Hall', 4.5, 22, 100],
            ['Campus - Mohammadpur', 'Science Building', 'Mohammadpur Bazar', 4.5, 22, 100],
            ['Campus - Old Dhaka', 'University Second Gate', 'Old Dhaka', 2.5, 20, 100]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO routes (name, start_point, end_point, distance, estimated_time, fare) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($routes as $r) {
            $stmt->execute($r);
        }
        echo "<p class='success'>✓ Sample routes inserted</p>";
        
        // Get route IDs
        $route_ids = $pdo->query("SELECT id FROM routes")->fetchAll(PDO::FETCH_COLUMN);
        
        // Sample buses
        $buses = [
            [$route_ids[0], 'G23', 40, '20:30:00', '22:30:00'],
            [$route_ids[1], 'G12', 40, '20:30:00', '22:30:00'],
            [$route_ids[2], 'G17', 40, '20:30:00', '22:30:00'],
            [$route_ids[3], 'G08', 40, '20:30:00', '22:30:00'],
            [$route_ids[4], 'G15', 40, '20:30:00', '22:30:00'],
            [$route_ids[5], 'G09', 40, '20:30:00', '22:30:00'],
            [$route_ids[6], 'G01', 40, '20:30:00', '22:30:00'],
            [$route_ids[7], 'G02', 40, '20:30:00', '22:30:00'],
            [$route_ids[8], 'G03', 40, '20:30:00', '22:30:00']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO buses (route_id, bus_number, capacity, departure_time, arrival_time) VALUES (?, ?, ?, ?, ?)");
        foreach ($buses as $b) {
            $stmt->execute($b);
        }
        echo "<p class='success'>✓ Sample buses inserted</p>";
        
        // Sample users (password: password123)
        $hashed = password_hash('password123', PASSWORD_DEFAULT);
        $users = [
            ['S12345', 'John', 'Doe', 'john.doe@university.edu', $hashed, 'student', 150],
            ['S12346', 'Jane', 'Smith', 'jane.smith@university.edu', $hashed, 'student', 200],
            ['S12347', 'Bob', 'Johnson', 'bob.johnson@university.edu', $hashed, 'student', 75],
            ['A12345', 'Admin', 'User', 'admin@university.edu', $hashed, 'admin', 0]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (student_id, first_name, last_name, email, password, user_type, total_points) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $stmt->execute($u);
        }
        echo "<p class='success'>✓ Sample users inserted</p>";
        
        // Get user IDs
        $user_ids = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        
        // Sample wallets
        $wallets = [
            [$user_ids[0], 250.00],
            [$user_ids[1], 300.00],
            [$user_ids[2], 100.00],
            [$user_ids[3], 0.00]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO wallet (user_id, balance) VALUES (?, ?)");
        foreach ($wallets as $w) {
            $stmt->execute($w);
        }
        echo "<p class='success'>✓ Sample wallets inserted</p>";
        
        // Sample riders
        $stmt = $pdo->prepare("INSERT INTO riders (user_id, vehicle_type, vehicle_model, vehicle_number, license_number, is_verified, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_ids[0], 'car', 'Toyota Camry', 'ABC-1234', 'LIC12345', 1, 1]);
        $stmt->execute([$user_ids[1], 'motorcycle', 'Honda CBR', 'XYZ-5678', 'LIC67890', 1, 1]);
        echo "<p class='success'>✓ Sample riders inserted</p>";
        
        // Sample ride shares
        $stmt = $pdo->prepare("INSERT INTO ride_shares (creator_id, vehicle_type, from_location, to_location, departure_time, total_seats, available_seats, fare_per_person) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $departure1 = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $departure2 = date('Y-m-d H:i:s', strtotime('+3 hours'));
        $stmt->execute([$user_ids[2], 'car', 'University Main Gate', 'Mirpur Station', $departure1, 4, 3, 50.00]);
        $stmt->execute([$user_ids[0], 'microbus', 'University Second Gate', 'Uttara Metro', $departure2, 8, 5, 40.00]);
        echo "<p class='success'>✓ Sample ride shares inserted</p>";
        
        // Sample leaderboard
        $week = date('W');
        $year = date('Y');
        $leaderboard = [
            [$user_ids[1], $week, $year, 200, 1, '100%_off'],
            [$user_ids[0], $week, $year, 150, 2, '50%_off'],
            [$user_ids[2], $week, $year, 75, 3, '30%_off']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO weekly_leaderboard (user_id, week_number, year, total_points, rank_position, voucher_type) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($leaderboard as $l) {
            $stmt->execute($l);
        }
        echo "<p class='success'>✓ Sample leaderboard inserted</p>";
    }
    
    echo "<div class='box'>";
    echo "<h2 class='success'>✅ Installation Complete!</h2>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>John Doe: john.doe@university.edu / password123</li>";
    echo "<li>Jane Smith: jane.smith@university.edu / password123</li>";
    echo "<li>Bob Johnson: bob.johnson@university.edu / password123</li>";
    echo "<li>Admin: admin@university.edu / password123</li>";
    echo "</ul>";
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>Go to Website</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Installation failed: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>