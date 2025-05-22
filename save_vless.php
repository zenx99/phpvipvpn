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

// Initialize variables
$message = '';
$messageType = '';
$vlessCode = '';
$expiryTime = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user has enough credits
    if ($credits < 4) {
        $messageType = 'error';
        $message = 'เครดิตไม่เพียงพอ ต้องใช้ 4 เครดิตในการสร้างโค้ด VPN';
    } else {
        $vpsType = $_POST['vpsType'] ?? 'IDC';
        $profileKey = $_POST['profileKey'] ?? '';
        $codeName = $_POST['codeName'] ?? '';
        $timeAmount = intval($_POST['timeAmount'] ?? 0);
        $timeUnit = $_POST['timeUnit'] ?? 'day';
        $gbLimit = intval($_POST['gbLimit'] ?? 0);
        $ipLimit = intval($_POST['ipLimit'] ?? 1);

        // Validate inputs
        if (empty($profileKey) || empty($codeName) || $timeAmount <= 0) {
            $messageType = 'error';
            $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } else {
            // Prepare API request
            $apiUrl = 'http://103.245.164.86:4040/client';
            $postData = json_encode([
                'vpsType' => $vpsType,
                'profileKey' => $profileKey,
                'codeName' => $codeName,
                'timeAmount' => $timeAmount,
                'timeUnit' => $timeUnit,
                'gbLimit' => $gbLimit,
                'ipLimit' => $ipLimit
            ]);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $postData
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $messageType = 'error';
                $message = 'การเชื่อมต่อผิดพลาด: ' . curl_error($ch);
            } elseif ($httpCode !== 200) {
                $messageType = 'error';
                $message = 'เซิร์ฟเวอร์ผิดพลาด (HTTP ' . $httpCode . ')';
            } else {
                $json = json_decode($response, true);
                if (isset($json['success']) && $json['success']) {
                    // Deduct credits
                    $updateStmt = $db->prepare('UPDATE users SET credits = credits - 4 WHERE id = :id');
                    $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                    $updateStmt->execute();

                    // Save VPN history
                    $historyStmt = $db->prepare('INSERT INTO vpn_history (user_id, code_name, profile_key, vless_code, expiry_time, gb_limit, ip_limit) VALUES (:user_id, :code_name, :profile_key, :vless_code, :expiry_time, :gb_limit, :ip_limit)');
                    $historyStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $historyStmt->bindValue(':code_name', $codeName, SQLITE3_TEXT);
                    $historyStmt->bindValue(':profile_key', $profileKey, SQLITE3_TEXT);
                    $historyStmt->bindValue(':vless_code', $json['clientCode'], SQLITE3_TEXT);
                    $historyStmt->bindValue(':expiry_time', $json['expiryTime'], SQLITE3_INTEGER);
                    $historyStmt->bindValue(':gb_limit', $gbLimit, SQLITE3_INTEGER);
                    $historyStmt->bindValue(':ip_limit', $ipLimit, SQLITE3_INTEGER);
                    $historyStmt->execute();

                    $messageType = 'success';
                    $message = 'สร้างโค้ด VPN สำเร็จ';
                    $vlessCode = $json['clientCode'];
                    $expiryTime = date('Y-m-d H:i:s', $json['expiryTime']);
                    
                    // Update credits for display
                    $credits -= 4;
                } else {
                    $messageType = 'error';
                    $message = 'เซิร์ฟเวอร์ตอบกลับผิดพลาด';
                }
            }
            curl_close($ch);
        }
    }
}

// Create vpn_history table if it doesn't exist
$db->exec('
    CREATE TABLE IF NOT EXISTS vpn_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        code_name TEXT,
        profile_key TEXT,
        vless_code TEXT,
        expiry_time INTEGER,
        gb_limit INTEGER,
        ip_limit INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
');

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างโค้ด VPN - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .vless-form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .vless-result {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .vless-code {
            word-break: break-all;
            padding: 15px;
            background: #eef2ff;
            border-radius: 6px;
            margin: 10px 0;
            font-family: monospace;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .package-option {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .package-option:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
        }
        .package-option.selected {
            border-color: var(--primary-color);
            background: #eef2ff;
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
                <a href="topup.php" class="dropdown-item">
                    <i class="fas fa-plus-circle"></i> เติมเงิน
                </a>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> ตั้งค่าบัญชี
                </a>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>

    <div class="vless-form-container">
        <h2><i class="fas fa-plus-circle"></i> สร้างโค้ด VPN</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>-message">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($vlessCode)): ?>
            <div class="vless-result">
                <h3><i class="fas fa-check-circle"></i> สร้างโค้ดสำเร็จ</h3>
                <p>โค้ด VPN ของคุณ:</p>
                <div class="vless-code"><?php echo htmlspecialchars($vlessCode); ?></div>
                <p>วันหมดอายุ: <?php echo htmlspecialchars($expiryTime); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label><i class="fas fa-tag"></i> เลือกแพ็กเกจ</label>
                <div class="package-option" data-package="true_dtac_nopro">
                    <strong>True/Dtac ไม่จำกัด</strong>
                    <p>เหมาะสำหรับซิม True/Dtac ทุกแพ็กเกจ</p>
                </div>
                <div class="package-option" data-package="true_zoom">
                    <strong>True Zoom/Work</strong>
                    <p>เหมาะสำหรับซิม True แพ็กเกจ Zoom/Work/Learn</p>
                </div>
                <div class="package-option" data-package="ais">
                    <strong>AIS ไม่จำกัด</strong>
                    <p>เหมาะสำหรับซิม AIS ทุกแพ็กเกจ</p>
                </div>
                <div class="package-option" data-package="true_pro_facebook">
                    <strong>True Pro Facebook</strong>
                    <p>เหมาะสำหรับซิม True แพ็กเกจโปร Facebook</p>
                </div>
                <input type="hidden" name="profileKey" id="profileKey" required>
            </div>

            <div class="form-group">
                <label for="codeName"><i class="fas fa-file-signature"></i> ชื่อโค้ด VPN</label>
                <input type="text" id="codeName" name="codeName" placeholder="ระบุชื่อโค้ด VPN" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="timeAmount"><i class="fas fa-clock"></i> ระยะเวลา</label>
                    <input type="number" id="timeAmount" name="timeAmount" min="1" value="7" required>
                </div>
                <div class="form-group">
                    <label for="timeUnit"><i class="fas fa-calendar"></i> หน่วยเวลา</label>
                    <select id="timeUnit" name="timeUnit" required>
                        <option value="day">วัน</option>
                        <option value="month">เดือน</option>
                        <option value="year">ปี</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="gbLimit"><i class="fas fa-database"></i> จำกัดปริมาณข้อมูล (GB, 0 = ไม่จำกัด)</label>
                    <input type="number" id="gbLimit" name="gbLimit" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="ipLimit"><i class="fas fa-network-wired"></i> จำนวนอุปกรณ์ที่เชื่อมต่อพร้อมกัน</label>
                    <input type="number" id="ipLimit" name="ipLimit" min="1" max="10" value="1">
                </div>
            </div>

            <button type="submit" class="btn" style="width: 100%;">
                <i class="fas fa-plus-circle"></i> สร้างโค้ด VPN (4 เครดิต)
            </button>
        </form>

        <div style="margin-top: 20px; text-align: center;">
            <a href="vpn_history.php" class="btn" style="background: var(--dark-color);">
                <i class="fas fa-history"></i> ประวัติการสร้างโค้ด VPN
            </a>
            <a href="index.php" class="btn" style="background: #6c757d;">
                <i class="fas fa-arrow-left"></i> กลับไปหน้าหลัก
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Package selection
            const packageOptions = document.querySelectorAll('.package-option');
            const profileKeyInput = document.getElementById('profileKey');

            packageOptions.forEach(option => {
                option.addEventListener('click', function() {
                    packageOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    profileKeyInput.value = this.dataset.package;
                });
            });

            // User menu dropdown
            const menuToggle = document.querySelector('.user-info');
            const menuDropdown = document.querySelector('.user-dropdown');
            
            if (menuToggle && menuDropdown) {
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    menuDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!menuDropdown.contains(e.target) && !menuToggle.contains(e.target)) {
                        menuDropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>