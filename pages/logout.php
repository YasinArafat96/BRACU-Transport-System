<?php
/**
 * Logout script for University Bus Booking System
 * Handles user logout and session destruction
 */

require_once '../includes/config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>