<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Auction Explorer</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
      background: #f9f9f9;
    }
    header {
      background: #007bff;
      color: white;
      padding: 20px;
      text-align: center;
    }
    header ul {
      text-align: right;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    header ul li {
      display: inline-block;
      margin-left: 15px;
    }
    header ul li a {
      color: white;
      text-decoration: none;
      font-weight: bold;
    }
    .container {
      max-width: 1100px;
      margin: auto;
      padding: 20px;
    }
    h2 {
      color: #2c3e50;
      margin-top: 40px;
    }
    .items {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
    }
    .item {
      background: white;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      padding: 15px;
      flex: 1;
      min-width: 220px;
    }
    .item img {
      width: 100%;
      height: 150px;
      object-fit: cover;
      border-radius: 4px;
    }
    .item h3 {
      margin: 10px 0 5px;
    }
    .item p {
      margin: 5px 0;
    }
    .item button {
      background: #3498db;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 4px;
      cursor: pointer;
      margin-right: 5px;
    }
    .item button:hover {
      background: #2980b9;
    }
    .notification {
      background: #eaf2ff;
      padding: 10px;
      border-left: 5px solid #3498db;
      margin-top: 20px;
      border-radius: 4px;
    }
  </style>
</head>
<body>

  <header>
    <h1>Explore Electronics Item Auctions</h1>
    <p>Bid, browse, save favorites, and win amazing items!</p>
    <ul>
      <li><a href="home.html">Home</a></li>
    </ul>
  </header>

  <div class="container">
    <h2>Available Electronics Auction Items</h2>

    <div class="items">
      <?php
// -----------------------
// DATABASE CONNECTION
// -----------------------
$conn = new mysqli("localhost", "root", "Nishka@2002", "auction_items");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// -----------------------
// FETCH ITEMS WITH min_increment
// -----------------------
$sql = "SELECT id, title, description, starting_bid, min_increment, history, auction_time, photo1
        FROM items
        ORDER BY auction_time ASC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div class='item'>";
        
        // Display image if exists
        if (!empty($row['photo1'])) {
            echo "<img src='uploads/" . htmlspecialchars($row['photo1']) . "' alt='" . htmlspecialchars($row['title']) . "'>";
        } else {
            echo "<img src='https://via.placeholder.com/300x150?text=No+Image' alt='No Image Available'>";
        }
        
        echo "<h3>" . htmlspecialchars($row['title']) . "</h3>";
        echo "<p><strong>Description:</strong> " . htmlspecialchars($row['description']) . "</p>";
        echo "<p><strong>Starting Bid:</strong> ₹" . number_format($row['starting_bid'], 2) . "</p>";
        echo "<p><strong>Min Increment:</strong> ₹" . number_format($row['min_increment'], 2) . "</p>";

        if (!empty($row['history'])) {
            echo "<p><strong>History:</strong> " . htmlspecialchars($row['history']) . "</p>";
        }

        if (!empty($row['auction_time'])) {
            echo "<p><strong>Auction Time:</strong> " . htmlspecialchars($row['auction_time']) . "</p>";
        }

        // Display additional photos if exist
        for ($i = 2; $i <= 3; $i++) {
            $photoField = "photo$i";
            if (!empty($row[$photoField])) {
                echo '<img src="uploads/' . htmlspecialchars($row[$photoField]) . '" style="width:100%; height:100px; object-fit:cover; margin-top:5px; border-radius:4px;"><br>';
            }
        }

        echo '<a href="betpay.php?id=' . $row['id'] . '"><button>Bid Now</button></a>';
        echo '<a href="betpay.php?id=' . $row['id'] . '"><button style="background:#2ecc71;">View Details</button></a>';
        
        echo "</div>";
    }
} else {
    echo "<p>No auction items available at the moment.</p>";
}

$conn->close();
?>
    </div>

    <div class="notification">
      🔔 You have 3 new bidding notifications. Check your dashboard to stay ahead!
    </div>
  </div>

</body>
</html>