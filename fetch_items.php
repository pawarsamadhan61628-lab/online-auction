<?php

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("<p style='color:red;'>Invalid Request</p>");
}

// ---------------------
//  Database Connection
// ---------------------
$conn = new mysqli("localhost", "root", "Nishka@2002", "auction_items");

if ($conn->connect_error) {
    die("<p style='color:red;'>Database Connection Failed</p>");
}

// ---------------------
//   Fetch Auction Items
// ---------------------
$sql = "SELECT id, title, starting_bid, photo1, photo2, photo3 FROM auctions ORDER BY id DESC";
$result = $conn->query($sql);

// ---------------------
// Return HTML output
// ---------------------
echo "<div class='items'>";

if ($result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        // choose first available image
        $photo = "uploads/default.jpg";
        if (!empty($row['photo1'])) $photo = "uploads/" . $row['photo1'];
        else if (!empty($row['photo2'])) $photo = "uploads/" . $row['photo2'];
        else if (!empty($row['photo3'])) $photo = "uploads/" . $row['photo3'];

        echo "
        <div class='item'>
            <img src='$photo' alt='Item Image'>
            <h3>{$row['title']}</h3>
            <p>Starting Bid: ₹{$row['starting_bid']}</p>
            <a href='betpay.php?id={$row['id']}'>
                <button>Bid Now</button>
            </a>
            <button>❤️ Save</button>
        </div>
        ";
    }

} else {
    echo "<p>No auction items available.</p>";
}

echo "</div>";

$conn->close();
?>
