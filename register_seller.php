<?php
// Connect to database
$conn = new mysqli("localhost", "root", "Nishka@2002", "auction_items");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect form data safely
$name      = trim($_POST['name']);
$email     = trim($_POST['email']);
$password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Validate email pattern
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("❌ Error: Please enter a valid email address (format: user@example.com).");
}

// Prepare SQL statement to prevent SQL injection
$stmt = $conn->prepare("INSERT INTO sellers (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $password);

// Execute and check result
if ($stmt->execute()) {
    // Redirect to seller dashboard or login page
    header("Location: sellar.html");
    exit();
} else {
    echo "❌ Error: " . $stmt->error;
}

// Close connections
$stmt->close();
$conn->close();
?>