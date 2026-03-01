<?php
// register_buyer.php

// Database connection settings
$servername = "localhost";   // or your DB server
$username   = "root";        // your DB username
$password   = "Nishka@2002"; // your DB password
$dbname     = "auction_items";  // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect form data safely
$name     = trim($_POST['name']);
$email    = trim($_POST['email']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT); // hash password
$phone    = trim($_POST['phone']);

// Validate phone number (10 digits, numeric only)
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    die("❌ Error: Phone number must be exactly 10 digits (numeric only).");
}

// Validate email pattern using regex
$email_pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
if (!preg_match($email_pattern, $email)) {
    die("❌ Error: Please enter a valid email address (e.g., user@example.com).");
}

// Prepare SQL statement to prevent SQL injection
$stmt = $conn->prepare("INSERT INTO buyers (name, email, password, phone) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $password, $phone);

// Execute and check result
if ($stmt->execute()) {
    // Redirect to buyer dashboard or login page
    header("Location: Buyer.php"); 
    exit();
} else {
    echo "❌ Error: " . $stmt->error;
}

// Close connections
$stmt->close();
$conn->close();
?>