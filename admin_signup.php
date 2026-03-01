<?php
// ---------- DATABASE CONNECTION ----------
$conn = new mysqli("localhost", "root", "", "auction_db");
if ($conn->connect_error) die("DB Error");

// ---------- ACTIONS ----------
if (isset($_GET['approve_item'])) {
    $id = (int)$_GET['approve_item'];
    $conn->query("UPDATE items SET approved=1, status='active' WHERE id=$id");
}

if (isset($_GET['block_user'])) {
    $id = (int)$_GET['block_user'];
    $conn->query("UPDATE users SET blocked = !blocked WHERE id=$id");
}

$page = $_GET['page'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Panel</title>

<style>
body {
    font-family: Arial;
    background: #f4f6f8;
    margin: 0;
}

.sidebar {
    width: 220px;
    height: 100vh;
    background: #222;
    color: white;
    position: fixed;
    padding: 20px;
}

.sidebar a {
    display: block;
    color: white;
    padding: 10px;
    text-decoration: none;
}

.sidebar a:hover {
    background: #444;
}

.content {
    margin-left: 240px;
    padding: 20px;
}

table {
    width: 100%;
    background: white;
    border-collapse: collapse;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

button {
    padding: 5px 10px;
    border: none;
    cursor: pointer;
    color: white;
    background: #28a745;
}

button.block {
    background: #dc3545;
}
</style>

</head>
<body>

<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="?page=dashboard">Dashboard</a>
    <a href="?page=users">Users</a>
    <a href="?page=items">Auction Items</a>
    <a href="?page=auctions">Auctions</a>
</div>

<div class="content">

<?php
// ---------- DASHBOARD ----------
if ($page == "dashboard") {
    $users = $conn->query("SELECT COUNT(*) total FROM users")->fetch_assoc();
    $pending = $conn->query("SELECT COUNT(*) total FROM items WHERE approved=0")->fetch_assoc();
    $active = $conn->query("SELECT COUNT(*) total FROM items WHERE status='active'")->fetch_assoc();

    echo "<h1>Dashboard</h1>";
    echo "<p>Total Users: <b>{$users['total']}</b></p>";
    echo "<p>Pending Items: <b>{$pending['total']}</b></p>";
    echo "<p>Active Auctions: <b>{$active['total']}</b></p>";
}

// ---------- USERS ----------
if ($page == "users") {
    echo "<h2>Users</h2><table>
    <tr><th>Name</th><th>Email</th><th>Status</th><th>Action</th></tr>";

    $result = $conn->query("SELECT * FROM users");
    while ($row = $result->fetch_assoc()) {
        $status = $row['blocked'] ? "Blocked" : "Active";
        echo "<tr>
        <td>{$row['name']}</td>
        <td>{$row['email']}</td>
        <td>$status</td>
        <td>
            <a href='?page=users&block_user={$row['id']}'>
                <button class='block'>Toggle Block</button>
            </a>
        </td>
        </tr>";
    }
    echo "</table>";
}

// ---------- ITEMS ----------
if ($page == "items") {
    echo "<h2>Pending Auction Items</h2><table>
    <tr><th>Title</th><th>Start Price</th><th>Action</th></tr>";

    $result = $conn->query("SELECT * FROM items WHERE approved=0");
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
        <td>{$row['title']}</td>
        <td>\${$row['start_price']}</td>
        <td>
            <a href='?page=items&approve_item={$row['id']}'>
                <button>Approve</button>
            </a>
        </td>
        </tr>";
    }
    echo "</table>";
}

// ---------- AUCTIONS ----------
if ($page == "auctions") {
    echo "<h2>All Auctions</h2><table>
    <tr><th>Item</th><th>Status</th><th>Current Price</th></tr>";

    $result = $conn->query("SELECT * FROM items");
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
        <td>{$row['title']}</td>
        <td>{$row['status']}</td>
        <td>\${$row['current_price']}</td>
        </tr>";
    }
    echo "</table>";
}
?>

</div>
</body>
</html>
