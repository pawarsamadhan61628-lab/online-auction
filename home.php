<?php
session_start();
if (!isset($_SESSION['buyer_id'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Home</title>
</head>
<body>
  <h1>Welcome, <?php echo $_SESSION['buyer_name']; ?> 🎉</h1>
  <p>This is your buyer home page.</p>
  <a href="logout.php">Logout</a>
</body>
</html>