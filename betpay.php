<?php
// Database connection
$conn = new mysqli("localhost", "root", "Nishka@2002", "auction_items");
if ($conn->connect_error) die("Connection failed!");

// Check and add current_bid column if it doesn't exist
$check_column = $conn->query("SHOW COLUMNS FROM items LIKE 'current_bid'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE items ADD COLUMN current_bid DECIMAL(10,2) DEFAULT 0");
    // Add winner columns
    $conn->query("ALTER TABLE items ADD COLUMN winner_name VARCHAR(100) DEFAULT NULL");
    $conn->query("ALTER TABLE items ADD COLUMN winner_email VARCHAR(100) DEFAULT NULL");
    // Initialize current_bid with starting_bid for existing items
    $conn->query("UPDATE items SET current_bid = starting_bid");
}

// Add created_at column to bids table if it doesn't exist
$check_bids_column = $conn->query("SHOW COLUMNS FROM bids LIKE 'created_at'");
if ($check_bids_column->num_rows == 0) {
    $conn->query("ALTER TABLE bids ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Initialize variables
$success = false;
$success_message = "";
$error = "";
$bid_amount = 0;

// Process bid if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bid_amount = floatval($_POST['bid_amount'] ?? 0);
    $current_bid = floatval($_POST['current_bid'] ?? 0);
    $min_inc = floatval($_POST['min_increment'] ?? 0);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || $bid_amount <= 0) {
        $error = "All fields are required and bid amount must be positive";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } else {
        $min_required = $current_bid + $min_inc;
        if ($bid_amount < $min_required) {
            $error = "Bid must be at least ₹" . number_format($min_required, 2);
        } else {
            // Insert bid
            $ip = $_SERVER['REMOTE_ADDR'];
            $browser = substr($_SERVER['HTTP_USER_AGENT'], 0, 255);
            $stmt = $conn->prepare("INSERT INTO bids (item_id, bidder_name, bidder_email, bidder_phone, bid_amount, ip_address, browser_info) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("isssdss", $item_id, $name, $email, $phone, $bid_amount, $ip, $browser);
            
            if ($stmt->execute()) {
                $bid_id = $stmt->insert_id;
                
                // Update item's current bid
                $update_stmt = $conn->prepare("UPDATE items SET current_bid = ? WHERE id = ?");
                $update_stmt->bind_param("di", $bid_amount, $item_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Get the highest bidder for this item
                $highest_check = $conn->prepare("SELECT bidder_name, bidder_email FROM bids WHERE item_id = ? ORDER BY bid_amount DESC, id DESC LIMIT 1");
                $highest_check->bind_param("i", $item_id);
                $highest_check->execute();
                $highest_result = $highest_check->get_result();
                
                if ($highest_result->num_rows > 0) {
                    $highest_bidder = $highest_result->fetch_assoc();
                    // Update winner information
                    $winner_stmt = $conn->prepare("UPDATE items SET winner_name = ?, winner_email = ? WHERE id = ?");
                    $winner_stmt->bind_param("ssi", $highest_bidder['bidder_name'], $highest_bidder['bidder_email'], $item_id);
                    $winner_stmt->execute();
                    $winner_stmt->close();
                }
                $highest_check->close();
                
                $success = true;
                $success_message = "Bid placed successfully!";
                
                // Check if this is now the highest bid
                $check_highest = $conn->prepare("SELECT MAX(bid_amount) as max_bid FROM bids WHERE item_id = ?");
                $check_highest->bind_param("i", $item_id);
                $check_highest->execute();
                $check_result = $check_highest->get_result();
                $highest_row = $check_result->fetch_assoc();
                
                if ($bid_amount == $highest_row['max_bid']) {
                    $success_message = "Bid placed successfully! You are currently the highest bidder! 🏆";
                }
                $check_highest->close();
            } else {
                $error = "Failed to save bid: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get item ID
$item_id = intval($_GET['id'] ?? ($_POST['item_id'] ?? 0));
if ($item_id <= 0) die("Invalid item!");

// Get item with prepared statement
$stmt = $conn->prepare("SELECT *, COALESCE(current_bid, starting_bid) as display_bid FROM items WHERE id=?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item_result = $stmt->get_result();
$item = $item_result->fetch_assoc();
if (!$item) die("Item not found!");
$stmt->close();

// Get current highest bid and winner information
$winner_query = $conn->prepare("SELECT bid_amount, bidder_name, bidder_email FROM bids WHERE item_id = ? ORDER BY bid_amount DESC, id DESC LIMIT 1");
$winner_query->bind_param("i", $item_id);
$winner_query->execute();
$winner_result = $winner_query->get_result();

if ($winner_result->num_rows > 0) {
    $winner = $winner_result->fetch_assoc();
    $current_bid = $winner['bid_amount'];
    $winner_name = $winner['bidder_name'];
    $winner_email = $winner['bidder_email'];
} else {
    // No bids yet, use starting bid
    $current_bid = $item['display_bid'];
    $winner_name = $item['winner_name'] ?? null;
    $winner_email = $item['winner_email'] ?? null;
}
$winner_query->close();

// Also get current bid from items table as backup
$current_bid_from_items = $item['current_bid'] ?? $item['starting_bid'];
// Use the higher of the two
$current_bid = max($current_bid, $current_bid_from_items);

// Calculate time
$time_remaining = "Not set";
$auction_active = true;
if (!empty($item['auction_time'])) {
    $end = strtotime($item['auction_time']);
    $now = time();
    if ($end > $now) {
        $time_left = $end - $now;
        $hours = floor($time_left / 3600);
        $minutes = floor(($time_left % 3600) / 60);
        $seconds = $time_left % 60;
        $time_remaining = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    } else {
        $time_remaining = "00:00:00";
        $auction_active = false;
    }
}

$min_bid = $current_bid + $item['min_increment'];
?>

<!DOCTYPE html>
<html>
<head>
    <title><?=htmlspecialchars($item['title'])?> - Bid</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:system-ui;background:#0f172a;color:#e5e7eb;min-height:100vh}
        .container{max-width:1000px;margin:0 auto;padding:20px}
        .grid{display:grid;grid-template-columns:1.2fr 1fr;gap:20px}
        .card{background:#111827;border:1px solid #1f2937;border-radius:12px;overflow:hidden}
        .card-header{padding:15px;border-bottom:1px solid #1f2937;display:flex;justify-content:space-between;align-items:center}
        .card-body{padding:20px}
        .title{font-size:18px;font-weight:600}
        .badge{padding:5px 10px;border-radius:20px;font-size:12px;background:#0b1324;border:1px solid #1e293b}
        .badge.ended{background:#1f2937;color:#ef4444}
        .badge.success{background:#14532d;color:#86efac}
        .badge.winner{background:#fbbf24;color:#78350f;animation:pulse 2s infinite}
        .item-img{width:100%;height:200px;object-fit:cover;border-radius:8px;margin-bottom:15px}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:15px 0}
        .stat{padding:10px;border:1px solid #1f2937;border-radius:8px;background:#0b1324}
        .stat-label{font-size:12px;color:#9ca3af}
        .stat-value{font-size:16px;font-weight:600;margin-top:4px}
        .input-group{margin:10px 0}
        .input-group input{width:100%;padding:10px;border:1px solid #1f2937;border-radius:8px;background:#0b1324;color:white;margin:5px 0}
        .btn{padding:10px 15px;border:none;border-radius:8px;cursor:pointer;width:100%;margin:5px 0;font-weight:600}
        .btn-primary{background:#22c55e;color:#07120c}
        .btn-secondary{background:#38bdf8;color:#06121a}
        .btn-winner{background:#fbbf24;color:#78350f}
        .btn:disabled{background:#374151;color:#9ca3af;cursor:not-allowed}
        .success-box{background:#111827;padding:30px;border-radius:12px;text-align:center;margin:20px 0;border:2px solid #22c55e}
        .error-box{background:#111827;padding:20px;border-radius:12px;margin:20px 0;border:2px solid #ef4444;color:#ef4444}
        .success-icon{color:#22c55e;font-size:40px;margin-bottom:15px}
        .bid-info{background:#0b1324;border:1px solid #1f2937;border-radius:8px;padding:15px;margin:15px 0}
        .info-row{display:flex;justify-content:space-between;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #1f2937}
        .info-row:last-child{border-bottom:none;margin-bottom:0}
        .alert{background:#fef3c7;color:#92400e;padding:10px;border-radius:8px;margin:10px 0;border-left:4px solid #f59e0b}
        
        /* Winner Display */
        .winner-display {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 2px solid #fbbf24;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .winner-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .winner-icon {
            font-size: 24px;
            color: #fbbf24;
        }
        
        .winner-name {
            font-size: 18px;
            font-weight: bold;
            color: #fbbf24;
        }
        
        .winner-email {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .winner-bid {
            font-size: 24px;
            font-weight: bold;
            color: #22c55e;
            text-align: center;
            margin: 10px 0;
        }
        
        /* Confetti */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #fbbf24;
            animation: confetti-fall 2s linear forwards;
            z-index: 9999;
            pointer-events: none;
        }
        
        @keyframes confetti-fall {
            to { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        @media (max-width:768px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="container">
        
        <?php if($success): ?>
        <!-- Success Message -->
        <div class="success-box">
            <div class="success-icon">✓</div>
            <h2 style="color:#22c55e;margin-bottom:15px"><?=$success_message?></h2>
            <div class="bid-info">
                <div class="info-row">
                    <span>Item:</span>
                    <strong><?=htmlspecialchars($item['title'])?></strong>
                </div>
                <div class="info-row">
                    <span>Your Bid:</span>
                    <strong style="color:#22c55e">₹<?=number_format($bid_amount,2)?></strong>
                </div>
                <div class="info-row">
                    <span>Status:</span>
                    <strong style="color:#fbbf24">
                        <?php 
                        $check_highest = $conn->prepare("SELECT MAX(bid_amount) as max_bid FROM bids WHERE item_id = ?");
                        $check_highest->bind_param("i", $item_id);
                        $check_highest->execute();
                        $check_result = $check_highest->get_result();
                        $highest_row = $check_result->fetch_assoc();
                        echo ($bid_amount == $highest_row['max_bid']) ? 'CURRENT HIGHEST BIDDER 🏆' : 'OUTBID';
                        $check_highest->close();
                        ?>
                    </strong>
                </div>
                <div class="info-row">
                    <span>Time:</span>
                    <strong><?=date('H:i:s')?></strong>
                </div>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='?id=<?=$item_id?>'">
                Back to Bidding
            </button>
        </div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- ITEM PANEL -->
            <div class="card">
                <div class="card-header">
                    <div class="title"><?=htmlspecialchars($item['title'])?></div>
                    <div class="badge <?=$auction_active?'success':'ended'?>" id="countdown">
                        <?=$auction_active?"Ends in $time_remaining":"Auction Ended"?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(!empty($item['photo1'])): ?>
                    <img class="item-img" src="uploads/<?=htmlspecialchars($item['photo1'])?>" 
                         onerror="this.src='https://via.placeholder.com/400x200?text=No+Image'" 
                         alt="<?=htmlspecialchars($item['title'])?>">
                    <?php else: ?>
                    <img class="item-img" src="https://via.placeholder.com/400x200?text=No+Image" 
                         alt="No Image Available">
                    <?php endif; ?>
                    
                    <!-- CURRENT WINNER DISPLAY -->
                    <?php if($winner_name): ?>
                    <div class="winner-display">
                        <div class="winner-header">
                            <div class="winner-icon">👑</div>
                            <div>
                                <div class="winner-name">Current Highest Bidder</div>
                                <div class="winner-email"><?=htmlspecialchars($winner_name)?></div>
                                <div class="winner-email"><?=htmlspecialchars($winner_email)?></div>
                            </div>
                        </div>
                        <div class="winner-bid">₹<?=number_format($current_bid,2)?></div>
                        <div style="text-align:center;font-size:12px;color:#94a3b8">
                            <?=$auction_active ? 'Leading the auction' : 'Auction Winner!'?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-label">Start Price</div>
                            <div class="stat-value">₹<?=number_format($item['starting_bid'],2)?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Current Bid</div>
                            <div class="stat-value" style="color:#22c55e">₹<?=number_format($current_bid,2)?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Min Increment</div>
                            <div class="stat-value">₹<?=number_format($item['min_increment'],2)?></div>
                        </div>
                    </div>
                    
                    <?php if(!empty($item['description'])): ?>
                    <div style="margin-top:15px;padding:10px;background:#0b1324;border:1px solid #1f2937;border-radius:8px">
                        <div class="stat-label">Description</div>
                        <div style="margin-top:5px"><?=nl2br(htmlspecialchars($item['description']))?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($error) && !empty($error)): ?>
                    <div class="error-box">
                        <strong>Error:</strong> <?=$error?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BID FORM -->
            <div class="card">
                <div class="card-header">
                    <div class="title">Place Your Bid</div>
                    <div class="badge <?=$auction_active?'success':'ended'?>">
                        <?=$auction_active?'Live Auction':'Auction Ended'?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if($auction_active): ?>
                    <form method="POST" id="bidForm">
                        <input type="hidden" name="item_id" value="<?=$item_id?>">
                        <input type="hidden" name="current_bid" value="<?=$current_bid?>">
                        <input type="hidden" name="min_increment" value="<?=$item['min_increment']?>">
                        
                        <div class="input-group">
                            <input type="text" name="name" placeholder="Your Name" required 
                                   value="<?=isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''?>">
                        </div>
                        
                        <div class="input-group">
                            <input type="email" name="email" placeholder="Email Address" required
                                   value="<?=isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''?>">
                        </div>
                        
                        <div class="input-group">
                            <input type="tel" name="phone" placeholder="Phone Number" required
                                   value="<?=isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''?>">
                        </div>
                        
                        <div class="input-group">
                            <input type="number" name="bid_amount" id="bidAmount" 
                                   min="<?=$min_bid?>" step="100" 
                                   placeholder="Bid Amount (₹)" required
                                   value="<?=isset($_POST['bid_amount']) ? htmlspecialchars($_POST['bid_amount']) : ''?>">
                            <small style="color:#9ca3af;font-size:12px">Minimum: ₹<?=number_format($min_bid,2)?></small>
                        </div>
                        
                        <button class="btn btn-primary" type="submit">
                            Place Bid & Try to Win 🏆
                        </button>
                        <button class="btn btn-secondary" type="button" onclick="resetForm()">Reset</button>
                    </form>
                    
                    <div style="margin-top:15px;padding:10px;background:#0b1324;border:1px dashed #1f2937;border-radius:8px;font-size:12px;color:#9ca3af">
                        <strong>🏆 Highest Bid Wins:</strong> 
                        The bidder with the highest amount when timer ends wins the auction.
                    </div>
                    <?php else: ?>
                    <!-- AUCTION ENDED - SHOW FINAL WINNER -->
                    <?php if($winner_name): ?>
                    <div style="text-align:center;padding:20px">
                        <div style="font-size:48px;margin-bottom:20px">🏆</div>
                        <h2 style="color:#fbbf24">Auction Completed!</h2>
                        <div style="margin:20px 0;padding:15px;background:#0b1324;border-radius:8px">
                            <div style="font-size:18px;font-weight:bold;color:#fbbf24">Winner: <?=htmlspecialchars($winner_name)?></div>
                            <div style="color:#94a3b8;margin:5px 0">Email: <?=htmlspecialchars($winner_email)?></div>
                            <div style="font-size:24px;font-weight:bold;color:#22c55e;margin:10px 0">
                                Winning Bid: ₹<?=number_format($current_bid,2)?>
                            </div>
                        </div>
                        <button class="btn btn-winner" onclick="celebrateWinner()">
                            Celebrate Winner! 🎉
                        </button>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center;padding:40px 20px">
                        <div style="font-size:48px;margin-bottom:20px">⏰</div>
                        <h3>Auction Ended</h3>
                        <p style="color:#94a3b8">No bids were placed</p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('bidForm')?.addEventListener('submit', function(e) {
            const bidAmount = parseFloat(document.getElementById('bidAmount').value) || 0;
            const minBid = <?=$min_bid?>;
            
            if (bidAmount < minBid) {
                alert(`Minimum bid is ₹${minBid.toLocaleString('en-IN')}`);
                e.preventDefault();
                return false;
            }
            
            return confirm(`Place bid of ₹${bidAmount.toLocaleString('en-IN')}? Highest bid wins!`);
        });
        
        // Reset form
        function resetForm() {
            document.getElementById('bidForm')?.reset();
        }
        
        // Winner celebration
        function celebrateWinner() {
            // Create confetti
            const colors = ['#fbbf24', '#22c55e', '#3b82f6', '#ef4444', '#8b5cf6'];
            for(let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = Math.random() * 10 + 5 + 'px';
                confetti.style.animationDelay = Math.random() * 1 + 's';
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), 3000);
            }
            
            // Show winner alert
            alert('🎉 CONGRATULATIONS! 🎉\n\n' +
                  'Winner: <?=addslashes(htmlspecialchars($winner_name))?>\n' +
                  'Email: <?=addslashes(htmlspecialchars($winner_email))?>\n' +
                  'Winning Bid: ₹<?=number_format($current_bid,2)?>\n\n' +
                  'The auction has been won!');
        }
        
        // Countdown timer
        <?php if($auction_active && !empty($item['auction_time'])): ?>
        let endTime = new Date("<?=$item['auction_time']?>").getTime();
        let timer = setInterval(() => {
            let now = new Date().getTime();
            let distance = endTime - now;
            
            if (distance < 0) {
                clearInterval(timer);
                document.getElementById('countdown').textContent = "Auction Ended";
                document.getElementById('countdown').className = "badge ended";
                location.reload();
                return;
            }
            
            let hours = Math.floor(distance / (1000 * 60 * 60));
            let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            let seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('countdown').textContent = 
                `Ends in ${hours}:${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;
        }, 1000);
        <?php endif; ?>
        
        // Auto-celebrate if just became winner
        <?php if($success && isset($bid_amount)): ?>
        <?php 
        // Check if this bid is the highest
        $check_highest = $conn->prepare("SELECT MAX(bid_amount) as max_bid FROM bids WHERE item_id = ?");
        $check_highest->bind_param("i", $item_id);
        $check_highest->execute();
        $check_result = $check_highest->get_result();
        $highest_row = $check_result->fetch_assoc();
        $is_highest = ($bid_amount == $highest_row['max_bid']);
        $check_highest->close();
        ?>
        <?php if($is_highest): ?>
        setTimeout(() => {
            celebrateWinner();
        }, 1000);
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>