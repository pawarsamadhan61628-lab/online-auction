<?php
// -----------------------
// 1. DATABASE CONNECTION
// -----------------------
$host = "localhost";
$user = "root";
$pass = "Nishka@2002";
$dbname = "auction_items";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// -----------------------
// 2. GET FORM DATA
// -----------------------
$title         = trim($_POST['title'] ?? '');
$description   = trim($_POST['description'] ?? '');
$starting_bid  = $_POST['starting_bid'] ?? 0;
$min_increment = $_POST['min_increment'] ?? 0;
$history       = trim($_POST['history'] ?? '');
$auction_date_input = $_POST['auction_date'] ?? '';
$auction_time_input = $_POST['auction_time'] ?? '';

// Basic validation
if (empty($title) || empty($starting_bid) || empty($min_increment)) {
    die("<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 5px;'>
            <h2>❌ Missing Required Fields</h2>
            <p>Title, starting bid, and minimum increment are required!</p>
            <a href='add_item.html' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go Back</a>
         </div>");
}

// Validate numeric fields
$starting_bid = floatval($starting_bid);
$min_increment = floatval($min_increment);

if ($starting_bid <= 0) {
    die("Starting bid must be a positive number!");
}

if ($min_increment <= 0) {
    die("Minimum increment must be a positive number!");
}

// -----------------------
// 3. HANDLE BID ENDING DATE & TIME
// -----------------------
$bid_ending_datetime = NULL;
$display_ending_time = "Not specified";

if (!empty($auction_date_input) && !empty($auction_time_input)) {
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $auction_date_input)) {
        die("Invalid date format. Use YYYY-MM-DD");
    }
    
    // Validate time format (HH:MM)
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $auction_time_input)) {
        die("Invalid time format. Use HH:MM (24-hour format)");
    }
    
    // Check if date is valid
    $date_parts = explode('-', $auction_date_input);
    if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        die("Invalid date provided");
    }
    
    // Combine date and time for database
    $bid_ending_datetime = $auction_date_input . " " . $auction_time_input . ":00";
    
    // Validate that bid ending time is in the future
    $current_datetime = date("Y-m-d H:i:s");
    if (strtotime($bid_ending_datetime) <= strtotime($current_datetime)) {
        // If in the past, automatically set to tomorrow same time
        $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
        $bid_ending_datetime = $tomorrow_date . " " . $auction_time_input . ":00";
        
        echo "<div style='color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>
                ⚠️ Bid ending time was in the past. Automatically set to tomorrow at the same time.
              </div>";
    }
    
    // Create user-friendly display format
    $display_ending_time = date("l, F j, Y", strtotime($auction_date_input)) . " at " . date("g:i A", strtotime($auction_time_input));
    
} elseif (!empty($auction_time_input)) {
    // If only time is provided (for backward compatibility)
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $auction_time_input)) {
        die("Invalid time format. Use HH:MM (24-hour format)");
    }
    
    // Use today's date with provided time
    $today = date("Y-m-d");
    $bid_ending_datetime = $today . " " . $auction_time_input . ":00";
    
    // Check if it's in the past
    $current_datetime = date("Y-m-d H:i:s");
    if (strtotime($bid_ending_datetime) <= strtotime($current_datetime)) {
        // If in the past, use tomorrow
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $bid_ending_datetime = $tomorrow . " " . $auction_time_input . ":00";
        $display_ending_time = "Tomorrow at " . date("g:i A", strtotime($auction_time_input));
    } else {
        $display_ending_time = "Today at " . date("g:i A", strtotime($auction_time_input));
    }
}

// Calculate Unix timestamp for JavaScript countdown
$js_end_timestamp = $bid_ending_datetime ? strtotime($bid_ending_datetime) * 1000 : 0;

// -----------------------
// 4. HANDLE FILE UPLOAD
// -----------------------
$uploadedPhotos = [];
$uploadDir = "uploads/";

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die("Failed to create upload directory.");
    }
}

// Process file uploads
if (isset($_FILES['photos'])) {
    
    // Check if it's a single file or array
    if (is_array($_FILES['photos']['name'])) {
        // Multiple files - take only the first one
        $name = $_FILES['photos']['name'][0];
        $tmpName = $_FILES['photos']['tmp_name'][0];
        $error = $_FILES['photos']['error'][0];
    } else {
        // Single file
        $name = $_FILES['photos']['name'];
        $tmpName = $_FILES['photos']['tmp_name'];
        $error = $_FILES['photos']['error'];
    }
    
    if ($error === UPLOAD_ERR_OK && !empty($name)) {
        // Check file size (5MB max)
        $size = is_array($_FILES['photos']['size']) ? $_FILES['photos']['size'][0] : $_FILES['photos']['size'];
        
        if ($size <= 5 * 1024 * 1024) {
            // Check if it's a valid image
            $imageInfo = @getimagesize($tmpName);
            if ($imageInfo !== false) {
                // Generate unique filename
                $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
                $uniqueFilename = uniqid('img_') . '_' . date('Ymd_His') . '.' . strtolower($fileExtension);
                $targetPath = $uploadDir . $uniqueFilename;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $uploadedPhotos[] = $uniqueFilename;
                    $upload_message = "✅ Successfully uploaded: $name (saved as $uniqueFilename)<br>";
                } else {
                    $upload_message = "❌ Failed to move file: $name<br>";
                }
            } else {
                $upload_message = "❌ File '$name' is not a valid image<br>";
            }
        } else {
            $upload_message = "❌ File '$name' is too large (max 5MB)<br>";
        }
    } else {
        $upload_message = "⚠️ No file uploaded or upload error<br>";
    }
}

// Use only the first photo (since your table might only have one photo column)
$photo = isset($uploadedPhotos[0]) ? $uploadedPhotos[0] : NULL;

// -----------------------
// 5. INSERT INTO DATABASE TABLE
// -----------------------
$sql = "INSERT INTO items 
        (title, description, starting_bid, min_increment, history, auction_time, photo1) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters - use $bid_ending_datetime for auction_time
$stmt->bind_param(
    "ssddsss",
    $title,
    $description,
    $starting_bid,
    $min_increment,
    $history,
    $bid_ending_datetime,
    $photo
);

if ($stmt->execute()) {
    $item_id = $stmt->insert_id;
    
    // Output HTML with JavaScript for real-time countdown
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Auction Item Added</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 900px;
                margin: 30px auto;
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                border-radius: 8px;
                padding: 25px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .row {
                display: flex;
                flex-wrap: wrap;
                gap: 25px;
                margin-bottom: 25px;
            }
            .col {
                flex: 1;
                min-width: 300px;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 6px;
            }
            .countdown-box {
                background: #d1ecf1;
                padding: 20px;
                border-radius: 6px;
                margin-bottom: 15px;
                text-align: center;
            }
            .countdown {
                font-size: 1.8em;
                font-weight: bold;
                color: #dc3545;
                padding: 15px;
                background: white;
                border-radius: 5px;
                margin: 10px 0;
                font-family: monospace;
            }
            .time-block {
                display: inline-block;
                margin: 0 10px;
                min-width: 80px;
            }
            .time-number {
                font-size: 2em;
                display: block;
            }
            .time-label {
                font-size: 0.8em;
                color: #6c757d;
                display: block;
                margin-top: 5px;
            }
            .btn {
                padding: 12px 25px;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 0 10px;
                font-weight: bold;
                display: inline-block;
            }
            .btn-primary { background: #007bff; }
            .btn-success { background: #28a745; }
            .btn-secondary { background: #6c757d; }
            .photo-container {
                text-align: center;
                margin: 25px 0;
                padding: 20px;
                background: white;
                border-radius: 8px;
                border: 2px solid #bee5eb;
            }
            .item-photo {
                max-width: 100%;
                height: auto;
                max-height: 350px;
                border: 3px solid #dee2e6;
                border-radius: 6px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .expired {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 5px;
                font-size: 1.2em;
                font-weight: bold;
                text-align: center;
            }
        </style>
        <script>
            // JavaScript for real-time countdown
            function startCountdown(endTimestamp) {
                function updateCountdown() {
                    const now = new Date().getTime();
                    const timeLeft = endTimestamp - now;
                    
                    if (timeLeft < 0) {
                        document.getElementById('countdown').innerHTML = '<div class="expired">⏰ Bidding Has Ended!</div>';
                        clearInterval(timer);
                        return;
                    }
                    
                    const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                    
                    document.getElementById('days').textContent = days.toString().padStart(2, '0');
                    document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
                    document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                    document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
                    
                    // Update page title
                    document.title = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} - Auction`;
                }
                
                const timer = setInterval(updateCountdown, 1000);
                updateCountdown();
            }
            
            // Start countdown when page loads
            window.onload = function() {
                <?php if ($js_end_timestamp > 0): ?>
                    startCountdown(<?php echo $js_end_timestamp; ?>);
                <?php endif; ?>
            };
        </script>
    </head>
    <body>
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 25px; color: #0c5460;">✅ Auction Item Added Successfully!</h2>
            
            <div class="row">
                <div class="col">
                    <h3 style="color: #0c5460; border-bottom: 2px solid #bee5eb; padding-bottom: 10px;">📋 Item Details</h3>
                    <p><strong>🆔 Item ID:</strong> <span style="background: #bee5eb; padding: 4px 10px; border-radius: 4px; font-weight: bold;"><?php echo $item_id; ?></span></p>
                    <p><strong>📌 Title:</strong> <?php echo htmlspecialchars($title); ?></p>
                    
                    <?php if (!empty($description)): ?>
                        <p><strong>📝 Description:</strong><br><div style="background: white; padding: 10px; border-radius: 4px; margin-top: 5px;"><?php echo nl2br(htmlspecialchars($description)); ?></div></p>
                    <?php endif; ?>
                    
                    <p><strong>💰 Starting Bid:</strong> <span style="color: #28a745; font-weight: bold; font-size: 1.1em;">₹<?php echo number_format($starting_bid, 2); ?></span></p>
                    <p><strong>📈 Minimum Increment:</strong> ₹<?php echo number_format($min_increment, 2); ?></p>
                    
                    <?php if (!empty($history)): ?>
                        <p><strong>📜 History/Provenance:</strong><br><div style="background: white; padding: 10px; border-radius: 4px; margin-top: 5px; font-style: italic;"><?php echo nl2br(htmlspecialchars($history)); ?></div></p>
                    <?php endif; ?>
                </div>
                
                <div class="col">
                    <h3 style="color: #0c5460; border-bottom: 2px solid #bee5eb; padding-bottom: 10px;">⏰ Bid Ending Time</h3>
                    
                    <div class="countdown-box">
                        <h4 style="margin-top: 0; color: #0c5460;">🛑 Bidding Ends On:</h4>
                        <div style="font-size: 1.3em; font-weight: bold; color: #dc3545; text-align: center; padding: 15px; background: white; border-radius: 5px; margin: 10px 0;">
                            <?php echo $display_ending_time; ?>
                        </div>
                        
                        <?php if ($bid_ending_datetime): ?>
                            <div id="countdown" class="countdown">
                                <div class="time-block">
                                    <span class="time-number" id="days">00</span>
                                    <span class="time-label">Days</span>
                                </div>
                                <div class="time-block">
                                    <span class="time-number" id="hours">00</span>
                                    <span class="time-label">Hours</span>
                                </div>
                                <div class="time-block">
                                    <span class="time-number" id="minutes">00</span>
                                    <span class="time-label">Minutes</span>
                                </div>
                                <div class="time-block">
                                    <span class="time-number" id="seconds">00</span>
                                    <span class="time-label">Seconds</span>
                                </div>
                            </div>
                            <p style="text-align: center; margin-top: 15px;"><strong>Database Format:</strong> <?php echo date("Y-m-d H:i:s", strtotime($bid_ending_datetime)); ?></p>
                        <?php else: ?>
                            <div class="expired">Bidding end time not set</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($upload_message)): ?>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">
                            <h4 style="margin-top: 0; color: #495057;">📁 Upload Status</h4>
                            <?php echo $upload_message; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($photo && file_exists("uploads/$photo")): ?>
                <div class="photo-container">
                    <h4 style="color: #0c5460; margin-bottom: 15px;">📸 Item Photo</h4>
                    <img src="uploads/<?php echo $photo; ?>" class="item-photo">
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #bee5eb;">
                <a href="sellar.html" class="btn btn-primary">➕ Add Another Item</a>
                <a href="dashboard.html" class="btn btn-success">📊 Go to Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    
} else {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 5px;'>";
    echo "<h2>❌ Error Adding Auction Item</h2>";
    echo "<p><strong>Error:</strong> " . $stmt->error . "</p>";
    echo "</div>";
}

// -----------------------
// 6. CLEAN UP
// -----------------------
$stmt->close();
$conn->close();
?>