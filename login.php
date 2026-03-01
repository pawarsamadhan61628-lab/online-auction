<?php
session_start();

// Database connection
$servername = "localhost";
$username   = "root";
$password   = "Nishka@2002";
$dbname     = "auction_items";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email    = trim($_POST['email']);
$password = trim($_POST['password']);

// Fetch user by email
$stmt = $conn->prepare("SELECT id, name, password FROM buyers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $row['password'])) {
        $_SESSION['buyer_id'] = $row['id'];
        $_SESSION['buyer_name'] = $row['name'];

        // Redirect to home.html
        header("Location:home.html");
        exit();
    } else {
        echo "❌ Invalid password.";
    }
} else {
    echo "❌ No account found with that email.";
}

$stmt->close();
$conn->close();
?>