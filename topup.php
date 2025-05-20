<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info from session
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Database connection
$db = new SQLite3(__DIR__ . '/vipvpn.db');

// Fetch user credits
$stmt = $db->prepare('SELECT credits FROM users WHERE id = :id');
$stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$credits = $row ? $row['credits'] : 0;

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher'])) {
    $phone = "0825658423"; // Default phone number for the service
    $voucherHash = trim($_POST['voucher']);
    
    function claimVoucher($phone, $voucherHash, $retries = 3, $retryDelay = 2)
    {
        $url = "https://store.cyber-safe.pro/api/topup/truemoney/angpaofree";
        $data = json_encode([
            "mobile" => $phone,
            "voucher_hash" => $voucherHash
        ]);

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Accept-Encoding: gzip, deflate"
                ],
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $error = "❌ เติมเงินล้มเหลว: " . curl_error($ch);
            } elseif ($httpCode !== 200) {
                $error = "❌ เติมเงินล้มเหลว (HTTP {$httpCode})";
            } else {
                $json = json_decode($response, true);
                if (isset($json["status"]["code"]) && $json["status"]["code"] === "SUCCESS") {
                    $amount = $json["data"]["amount_baht"] ?? 0;
                    // Update user credits in database
                    $updateStmt = $GLOBALS['db']->prepare('UPDATE users SET credits = credits + :amount WHERE id = :id');
                    $updateStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                    $updateStmt->bindValue(':id', $GLOBALS['user_id'], SQLITE3_INTEGER);
                    $updateStmt->execute();
                    
                    curl_close($ch);
                    return ["success" => true, "amount" => $amount];
                } elseif (!empty($json["success"])) {
                    curl_close($ch);
                    return ["success" => true, "amount" => null];
                } else {
                    $error = "❌ เติมเงินล้มเหลว: " . ($json["status"]["message"] ?? $json["error"] ?? "เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ");
                }
            }

            curl_close($ch);

            if ($attempt < $retries) {
                sleep($retryDelay);
            }
        }

        return ["success" => false, "error" => $error];
    }

    $result = claimVoucher($phone, $voucherHash);
    if ($result['success']) {
        $messageType = 'success';
        $message = 'เติมเงินสำเร็จ ' . ($result['amount'] ? $result['amount'] . ' บาท' : '');
        
        // Refresh credits
        $stmt = $db->prepare('SELECT credits FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $credits = $row ? $row['credits'] : 0;
    } else {
        $messageType = 'error';
        $message = 'เติมเงินล้มเหลว: ' . ($result['error'] ?? 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ');
    }
}

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เติมเงิน - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .topup-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .credit-display {
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
            color: #2ecc71;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn-topup {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #2ecc71, #27ae60);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-topup:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.4);
        }
    </style>
</head>
<body class="dashboard-body" style="display: block; padding-top: 80px; background: #f9fafc;">
    <div class="header">
        <div class="header-logo">
            <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo">
            <h1>VIP VPN</h1>
        </div>
        <div class="user-menu">
            <span class="user-info">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
            </span>
            <div class="user-dropdown">
                <div class="dropdown-header">บัญชีผู้ใช้</div>
                <div class="dropdown-item credit">
                    <i class="fas fa-coins"></i> เครดิต
                    <span class="credit-amount"><?php echo htmlspecialchars($credits); ?></span>
                </div>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> ตั้งค่าบัญชี
                </a>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>

    <div class="topup-container">
        <h2><i class="fas fa-coins"></i> เติมเงิน</h2>
        
        <div class="credit-display">
            <i class="fas fa-wallet"></i> ยอดเงินคงเหลือ: <?php echo htmlspecialchars($credits); ?> บาท
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="topup.php">
            <div class="form-group">
                <label for="voucher">ลิ้งค์อั่งเปา TrueMoney:</label>
                <input type="text" id="voucher" name="voucher" class="form-control" 
                       placeholder="วางลิ้งค์อั่งเปาที่นี่" required>
            </div>
            <button type="submit" class="btn-topup">
                <i class="fas fa-plus-circle"></i> เติมเงิน
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center; color: #666;">
            <small>
                <i class="fas fa-info-circle"></i> 
                วิธีเติมเงิน: คัดลอกลิ้งค์อั่งเปา TrueMoney แล้ววางในช่องด้านบน จากนั้นกดปุ่มเติมเงิน
            </small>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.user-info');
            const menuDropdown = document.querySelector('.user-dropdown');
            
            if (menuToggle && menuDropdown) {
                menuToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    menuDropdown.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!menuDropdown.contains(e.target) && !menuToggle.contains(e.target)) {
                        menuDropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>
