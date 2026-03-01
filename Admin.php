<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";       // your MySQL username
$pass = "Nishka@2002";           // your MySQL password
$db   = "auction_items";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    // Prepare statement
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? AND email = ? AND role = ?");
    $stmt->bind_param("sss", $username, $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_role'] = $row['role'];
            $_SESSION['admin_username'] = $row['username'];

            header("Location: admin_dashboard.php");
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "Admin not found or role mismatch.";
    }
}
?>