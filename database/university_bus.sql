-- Create database
CREATE DATABASE IF NOT EXISTS university_bus;
USE university_bus;

-- ============================================
-- EXISTING TABLES (MODIFIED)
-- ============================================

-- Users table (modified to add user_type)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    user_type ENUM('student', 'admin', 'rider') DEFAULT 'student',
    total_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Routes table (existing)
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_point VARCHAR(100) NOT NULL,
    end_point VARCHAR(100) NOT NULL,
    distance DECIMAL(5,2),
    estimated_time INT, -- in minutes
    fare DECIMAL(5,2) NOT NULL DEFAULT 100,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Buses table (existing)
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    bus_number VARCHAR(20) NOT NULL,
    capacity INT NOT NULL DEFAULT 40,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
);

-- Bookings table (existing)
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bus_id INT NOT NULL,
    seat_number INT NOT NULL,
    booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('confirmed', 'pending', 'cancelled', 'completed') DEFAULT 'pending',
    user_type ENUM('student', 'admin') DEFAULT 'student',
    points_earned INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
);

-- Wallet table (existing)
CREATE TABLE wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transactions table (existing)
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    description VARCHAR(255),
    transaction_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Password resets table (existing)
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- NEW TABLES FOR UBER SYSTEM
-- ============================================

-- Riders table (users who want to offer rides)
CREATE TABLE riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_type ENUM('car', 'motorcycle', 'microbus') NOT NULL,
    vehicle_model VARCHAR(100),
    vehicle_number VARCHAR(20),
    license_number VARCHAR(50),
    is_verified BOOLEAN DEFAULT FALSE,
    is_available BOOLEAN DEFAULT TRUE,
    current_latitude DECIMAL(10,8),
    current_longitude DECIMAL(11,8),
    last_location_update TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rider_user (user_id)
);

-- Uber rides table
CREATE TABLE uber_rides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    passenger_id INT NOT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    dropoff_location VARCHAR(255) NOT NULL,
    pickup_latitude DECIMAL(10,8),
    pickup_longitude DECIMAL(11,8),
    dropoff_latitude DECIMAL(10,8),
    dropoff_longitude DECIMAL(11,8),
    fare DECIMAL(10,2) NOT NULL,
    status ENUM('requested', 'accepted', 'started', 'completed', 'cancelled') DEFAULT 'requested',
    booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    distance_km DECIMAL(5,2),
    points_earned INT DEFAULT 0,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- NEW TABLES FOR RIDE SHARING SYSTEM
-- ============================================

-- Ride share groups
CREATE TABLE ride_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    vehicle_type ENUM('car', 'motorcycle', 'microbus', 'bus') NOT NULL,
    from_location VARCHAR(255) NOT NULL,
    to_location VARCHAR(255) NOT NULL,
    from_latitude DECIMAL(10,8),
    from_longitude DECIMAL(11,8),
    to_latitude DECIMAL(10,8),
    to_longitude DECIMAL(11,8),
    departure_time DATETIME NOT NULL,
    total_seats INT NOT NULL,
    available_seats INT NOT NULL,
    fare_per_person DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'full', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ride share participants
CREATE TABLE ride_share_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_share_id INT NOT NULL,
    user_id INT NOT NULL,
    seats_booked INT NOT NULL DEFAULT 1,
    booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    points_earned INT DEFAULT 0,
    FOREIGN KEY (ride_share_id) REFERENCES ride_shares(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_ride (user_id, ride_share_id)
);

-- ============================================
-- NEW TABLES FOR POINTS & RANKING SYSTEM
-- ============================================

-- Points history table
CREATE TABLE points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    source_type ENUM('bus_booking', 'uber_rider', 'uber_passenger', 'ride_share_creator', 'ride_share_participant') NOT NULL,
    source_id INT NOT NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    week_number INT NOT NULL,
    year INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Weekly leaderboard
CREATE TABLE weekly_leaderboard (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_number INT NOT NULL,
    year INT NOT NULL,
    total_points INT NOT NULL,
    rank_position INT,
    voucher_type ENUM('100%_off', '50%_off', '30%_off', 'none') DEFAULT 'none',
    voucher_claimed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_week (user_id, week_number, year)
);

-- Vouchers table
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    voucher_code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    discount_value INT NOT NULL,
    week_number INT NOT NULL,
    year INT NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Sample routes
INSERT INTO routes (name, start_point, end_point, distance, estimated_time, fare) VALUES
('Campus - Mirpur', 'University Main Gate', 'Mirpur Station', 5.2, 25, 100),
('Campus - Uttara', 'University Second Gate', 'Uttara Metro', 3.8, 20, 100),
('Campus - Dhanmondi', 'University Main Gate', 'Dhanmondi 32', 6.0, 30, 100),
('Campus - Banani', 'University Second Gate', 'Banani Circle', 4.0, 18, 100),
('Campus - Gulshan', 'University Main Gate', 'Gulshan 1', 4.8, 22, 100);

-- Sample buses
INSERT INTO buses (route_id, bus_number, capacity, departure_time, arrival_time) VALUES
(1, 'B23', 40, '08:30:00', '09:00:00'),
(1, 'B24', 40, '17:30:00', '18:00:00'),
(2, 'B17', 40, '08:45:00', '09:15:00'),
(2, 'B18', 40, '17:45:00', '18:15:00'),
(3, 'B15', 40, '09:00:00', '09:30:00'),
(3, 'B16', 40, '18:00:00', '18:30:00');

-- Sample users with hashed passwords (password: password123 for all)
-- You'll need to run reset_password.php to set proper hashes
INSERT INTO users (student_id, first_name, last_name, email, password, user_type, total_points) VALUES
('S12345', 'John', 'Doe', 'john.doe@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 150),
('S12346', 'Jane', 'Smith', 'jane.smith@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 200),
('S12347', 'Bob', 'Johnson', 'bob.johnson@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 75),
('A12345', 'Admin', 'User', 'admin@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0);

-- Sample wallets
INSERT INTO wallet (user_id, balance) VALUES
(1, 250.00),
(2, 300.00),
(3, 100.00),
(4, 0.00);

-- Sample riders
INSERT INTO riders (user_id, vehicle_type, vehicle_model, vehicle_number, license_number, is_verified, is_available) VALUES
(1, 'car', 'Toyota Camry', 'ABC-1234', 'LIC12345', TRUE, TRUE),
(2, 'motorcycle', 'Honda CBR', 'XYZ-5678', 'LIC67890', TRUE, TRUE);

-- Sample ride shares
INSERT INTO ride_shares (creator_id, vehicle_type, from_location, to_location, departure_time, total_seats, available_seats, fare_per_person) VALUES
(3, 'car', 'University Main Gate', 'Mirpur Station', DATE_ADD(NOW(), INTERVAL 2 HOUR), 4, 3, 50.00),
(1, 'microbus', 'University Second Gate', 'Uttara Metro', DATE_ADD(NOW(), INTERVAL 3 HOUR), 8, 5, 40.00);

-- Sample weekly leaderboard
INSERT INTO weekly_leaderboard (user_id, week_number, year, total_points, rank_position, voucher_type) VALUES
(2, WEEK(NOW()), YEAR(NOW()), 200, 1, '100%_off'),
(1, WEEK(NOW()), YEAR(NOW()), 150, 2, '50%_off'),
(3, WEEK(NOW()), YEAR(NOW()), 75, 3, '30%_off');