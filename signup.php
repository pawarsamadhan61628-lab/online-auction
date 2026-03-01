<?php
// Database connection settings
$servername = "localhost";
$username   = "root";
$password   = "Nishka@2002";
$dbname     = "auction_items";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect form data safely
$first_name       = trim($_POST['first_name']);
$last_name        = trim($_POST['last_name']);
$email            = trim($_POST['email']);
$password         = trim($_POST['password']);
$confirm_password = trim($_POST['confirm_password']);

// Check if passwords match
if ($password !== $confirm_password) {
    die("<script>alert('❌ Passwords do not match.'); window.location.href='signup.html';</script>");
}

// Hash the password before saving
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Prepare SQL statement
$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);

// Execute and check result
if ($stmt->execute()) {
    // ✅ Redirect to home.html using JavaScript
    echo "<script>
            alert('✅ Registration successful!');
            window.location.href = 'home.html';
          </script>";
    exit();
} else {
    echo "<script>
            alert('❌ Error: " . $stmt->error . "');
            window.location.href = 'signup.html';
          </script>";
}

// Close connections
$stmt->close();
$conn->close();
?>