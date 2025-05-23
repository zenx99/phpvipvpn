<?php
session_start();

// Set timezone to Asia/Bangkok (Thailand timezone)
date_default_timezone_set('Asia/Bangkok');

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
$timeAmount = 0;
$creditCost = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vpsType = $_POST['vpsType'] ?? 'IDC';
    $profileKey = $_POST['profileKey'] ?? '';
    $codeName = $_POST['codeName'] ?? '';
    $timeAmount = intval($_POST['timeAmount'] ?? 0);
    // Calculate credit cost (4 credits per day)
    $creditCost = 4 * $timeAmount;
    // Always treat duration as days (timeUnit removed)
    $timeUnit = 'day';
    $gbLimit = intval($_POST['gbLimit'] ?? 0);
    $ipLimit = intval($_POST['ipLimit'] ?? 1);
    
    // Check if user has enough credits based on selected days
    if ($credits < $creditCost) {
        $messageType = 'error';
        $message = "เครดิตไม่เพียงพอ ต้องใช้ {$creditCost} เครดิตสำหรับ {$timeAmount} วัน";
    } else {
        // ตัวแปรถูกกำหนดค่าที่ด้านบนแล้ว

        // First check if server has capacity (max 30 users)
        $totalOnlineUsers = 0;
        $validProfiles = ['true_dtac_nopro', 'true_zoom', 'ais', 'true_pro_facebook'];
        $serverIsFull = false;
        
        try {
            $apiUrl = 'http://103.245.164.86:4040/client/onlines';
            $processedUsers = [];  // Track unique users
            
            foreach ($validProfiles as $checkProfileKey) { // ใช้ตัวแปรอื่นเพื่อไม่ให้ทับตัวแปร $profileKey ที่ได้จาก POST
                $postData = json_encode([
                    'vpsType' => 'IDC',
                    'profileKey' => $checkProfileKey
                ]);
        
                $ch = curl_init($apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => $postData,
                    CURLOPT_TIMEOUT => 2 // Set timeout to 2 seconds
                ]);
        
                $response = curl_exec($ch);
                
                if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                    $json = json_decode($response, true);
                    if (isset($json['success']) && $json['success'] && isset($json['list'])) {
                        foreach ($json['list'] as $clientName) {
                            $clientNameClean = trim($clientName);
                            if (!empty($clientNameClean) && !isset($processedUsers[$clientNameClean])) {
                                $processedUsers[$clientNameClean] = true;
                                $totalOnlineUsers++;
                            }
                        }
                    }
                }
                curl_close($ch);
            }
            
            // Check if server is full (30 users max)
            if ($totalOnlineUsers >= 30) {
                $serverIsFull = true;
            }
        } catch (Exception $e) {
            // If API fails, we'll assume server is not full to allow creation
            $serverIsFull = false;
        }
        
        // If server is full, show error message
        if ($serverIsFull) {
            $messageType = 'error';
            $message = 'เซิร์ฟเวอร์เต็มแล้ว (30/30) ไม่สามารถสร้างโค้ด VPN เพิ่มได้ กรุณารอให้มีผู้ใช้ออฟไลน์ก่อน';
        }
        // Validate inputs
        else if (empty($profileKey) || empty($codeName) || $timeAmount <= 0) {
            $messageType = 'error';
            $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } else {
            // Maximum duration: 30 days
            if ($timeAmount > 30) {
                $messageType = 'error';
                $message = 'อยากเกิน 30 วันเหรอ? สูงสุดได้แค่ 30 วันเท่านั้น!';
            } else {
                // Prepare API request
                $apiUrl = 'http://103.245.164.86:4040/client';
                $postData = json_encode([
                    'vpsType' => $vpsType,
                    'profileKey' => $profileKey,
                    'codeName' => $codeName,
                    'timeAmount' => $timeAmount,
                    'timeUnit' => 'day',
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
                    $message = 'เซิร์ฟเวอร์ผิดพลาด (HTTP ' . $httpCode . ')' . ' Data: ' . $postData;
                } else {
                    $json = json_decode($response, true);
                    if (isset($json['success']) && $json['success']) {
                        // Deduct credits based on selected days
                        $updateStmt = $db->prepare('UPDATE users SET credits = credits - :cost WHERE id = :id');
                        $updateStmt->bindValue(':cost', $creditCost, SQLITE3_INTEGER);
                        $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                        $updateStmt->execute();

                        // Save VPN history - use current date/time in Thailand timezone
                        $currentDateTime = date('Y-m-d H:i:s');
                        $historyStmt = $db->prepare('INSERT INTO vpn_history (user_id, code_name, profile_key, vless_code, expiry_time, gb_limit, ip_limit, is_enabled, created_at) VALUES (:user_id, :code_name, :profile_key, :vless_code, :expiry_time, :gb_limit, :ip_limit, :is_enabled, :created_at)');
                        $historyStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                        $historyStmt->bindValue(':code_name', $codeName, SQLITE3_TEXT);
                        $historyStmt->bindValue(':profile_key', $profileKey, SQLITE3_TEXT);
                        $historyStmt->bindValue(':vless_code', $json['clientCode'], SQLITE3_TEXT);
                        $historyStmt->bindValue(':expiry_time', $json['expiryTime'], SQLITE3_INTEGER);
                        $historyStmt->bindValue(':gb_limit', $gbLimit, SQLITE3_INTEGER);
                        $historyStmt->bindValue(':ip_limit', $ipLimit, SQLITE3_INTEGER);
                        $historyStmt->bindValue(':is_enabled', 1, SQLITE3_INTEGER);
                        $historyStmt->bindValue(':created_at', $currentDateTime, SQLITE3_TEXT);
                        $historyStmt->execute();

                        $messageType = 'success';
                        $message = 'สร้างโค้ด VPN สำเร็จ';
                        $vlessCode = $json['clientCode'];
                        $expiryTime = date('Y-m-d H:i:s', $json['expiryTime']);
                        
                        // Update credits for display
                        $credits -= $creditCost;
                    } else {
                        $messageType = 'error';
                        $message = 'เซิร์ฟเวอร์ตอบกลับผิดพลาด: ' . (isset($json['message']) ? $json['message'] : 'ไม่ทราบสาเหตุ') . ' [Profile: ' . $profileKey . ']';
                    }
                }
                curl_close($ch);
            }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สร้างโค้ด VPN - VIP VPN</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode.js@1.0.0/qrcode.min.js"></script>
    <style>
        /* Custom glassmorphism effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        /* Space-themed background */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #0d1b2a, #1b263b, #3c096c);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }

        /* Gradient animation */
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Input focus animation */
        input:focus, select:focus {
            transform: scale(1.02);
            transition: transform 0.2s ease-in-out;
        }

        /* Button hover animation */
        .btn-primary:hover, .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        /* Fade-in animation for messages */
        .error-message, .success-message {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Package option styling */
        .package-option.selected {
            border-color: #22d3ee;
            background: rgba(34, 211, 238, 0.1);
        }

        .package-option.selected::before {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #22d3ee;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .logo {
                height: 2.5rem;
            }
            .form-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="relative min-h-screen">
    <div id="particles-js"></div>
    <header class="fixed top-0 left-0 w-full bg-white bg-opacity-10 backdrop-filter backdrop-blur-lg z-20">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="logo h-10">
                <h1 class="text-xl font-bold text-white">VIP VPN</h1>
            </div>
            <div class="relative user-menu">
                <button class="flex items-center space-x-2 text-gray-200 hover:text-white">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </button>
                <div class="user-dropdown hidden absolute right-0 mt-2 w-64 bg-white bg-opacity-10 backdrop-filter backdrop-blur-lg rounded-lg shadow-lg z-10">
                    <div class="px-4 py-2 text-sm font-semibold text-gray-200 border-b border-gray-600">บัญชีผู้ใช้</div>
                    <div class="px-4 py-2 text-sm text-gray-200">
                        <i class="fas fa-coins mr-2"></i>เครดิต: <span class="font-semibold"><?php echo htmlspecialchars($credits); ?></span>
                    </div>
                    <a href="topup.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-600 hover:bg-opacity-20">
                        <i class="fas fa-plus-circle mr-2"></i>เติมเงิน
                    </a>
                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-600 hover:bg-opacity-20">
                        <i class="fas fa-cog mr-2"></i>ตั้งค่าบัญชี
                    </a>
                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-600 hover:bg-opacity-20">
                        <i class="fas fa-sign-out-alt mr-2"></i>ออกจากระบบ
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 pt-24 pb-8 z-10">
        <div class="glass-card rounded-2xl p-8 max-w-3xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6"><i class="fas fa-plus-circle mr-2"></i>สร้างโค้ด VPN</h2>

            <!-- Loading Modal -->
            <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="glass-card rounded-2xl p-8 max-w-md mx-auto text-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-cyan-500 border-opacity-75 mx-auto mb-4"></div>
                    <h3 class="text-xl font-semibold text-white mb-4">กำลังสร้างโค้ด VPN</h3>
                    <p class="text-gray-300 mb-6">โปรดรอสักครู่ กำลังดำเนินการ (12-20 วินาที)</p>
                    <div id="processingCounter" class="text-cyan-400 mb-6 text-xl font-semibold">0s</div>
                </div>
            </div>
            
            <!-- Success Modal -->
            <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="glass-card rounded-2xl p-8 max-w-md mx-auto">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-white mb-2">สร้างโค้ด VPN สำเร็จ</h3>
                        <p class="text-gray-300">โค้ด VPN ของคุณพร้อมใช้งานแล้ว</p>
                    </div>
                    <button id="confirmBtn" class="btn-primary w-full bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                        <i class="fas fa-check mr-2"></i>ตกลง
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>-message bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-500 bg-opacity-30 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-200 p-4 rounded-lg mb-6 flex items-center border border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-400">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($vlessCode)): ?>
                <div class="glass-card p-6 mb-6">
                    <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-check-circle mr-2"></i>สร้างโค้ดสำเร็จ</h3>
                    <p class="text-gray-300 mb-4">โค้ด VPN ของคุณพร้อมใช้งานแล้ว:</p>
                    <div class="relative bg-white bg-opacity-10 p-4 rounded-lg">
                        <code class="vless-code text-gray-200 text-sm break-all"><?php echo htmlspecialchars($vlessCode); ?></code>
                        <button class="copy-btn absolute top-2 right-2 bg-white bg-opacity-20 text-gray-200 px-3 py-1 rounded-lg hover:bg-opacity-30 transition duration-200" id="copyBtn" data-clipboard-text="<?php echo htmlspecialchars($vlessCode); ?>">
                            <i class="fas fa-copy mr-1"></i>คัดลอก
                        </button>
                    </div>
                    <div class="flex justify-between items-center mt-4">
                        <span class="text-gray-300"><i class="fas fa-calendar-alt mr-2"></i>หมดอายุ: <?php echo htmlspecialchars($expiryTime); ?></span>
                        <button id="showQRBtn" class="btn-secondary bg-gray-600 bg-opacity-20 text-gray-200 px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                            <i class="fas fa-qrcode mr-2"></i>แสดง QR Code
                        </button>
                    </div>
                    <div id="qrCode" class="hidden mt-4 text-center">
                        <div id="qrCodeCanvas" class="mx-auto max-w-xs"></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-tag mr-2"></i>เลือกแพ็กเกจ <span class="text-red-300">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="package-option glass-card p-4 rounded-lg cursor-pointer relative" data-package="true_dtac_nopro">
                            <strong class="block text-white"><i class="fas fa-signal mr-2"></i>True/Dtac ไม่จำกัด</strong>
                            <p class="text-gray-300 text-sm">เหมาะสำหรับซิม True/Dtac ทุกแพ็กเกจ</p>
                        </div>
                        <div class="package-option glass-card p-4 rounded-lg cursor-pointer relative" data-package="true_zoom">
                            <strong class="block text-white"><i class="fas fa-video mr-2"></i>True Zoom/Work</strong>
                            <p class="text-gray-300 text-sm">เหมาะสำหรับซิม True แพ็กเกจ Zoom/Work/Learn</p>
                        </div>
                        <div class="package-option glass-card p-4 rounded-lg cursor-pointer relative" data-package="ais">
                            <strong class="block text-white"><i class="fas fa-bolt mr-2"></i>AIS ไม่จำกัด</strong>
                            <p class="text-gray-300 text-sm">เหมาะสำหรับซิม AIS ทุกแพ็กเกจ</p>
                        </div>
                        <div class="package-option glass-card p-4 rounded-lg cursor-pointer relative" data-package="true_pro_facebook">
                            <strong class="block text-white"><i class="fab fa-facebook mr-2"></i>True Pro Facebook</strong>
                            <p class="text-gray-300 text-sm">เหมาะสำหรับซิม True แพ็กเกจโปร Facebook</p>
                        </div>
                    </div>
                    <input type="hidden" name="profileKey" id="profileKey" required>
                    <div id="packageError" class="text-red-300 text-sm mt-2 hidden">โปรดเลือกแพ็กเกจ</div>
                </div>

                <div>
                    <label for="codeName" class="block text-sm font-medium text-gray-200 mb-2">
                        <i class="fas fa-file-signature mr-2"></i>ชื่อโค้ด VPN <span class="text-red-300">*</span>
                    </label>
                    <input type="text" id="codeName" name="codeName" placeholder="เช่น My Phone, iPad ของแก้ว" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="timeAmount" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-clock mr-2"></i>ระยะเวลา (วัน) <span class="text-red-300">*</span>
                        </label>
                        <input type="number" id="timeAmount" name="timeAmount" min="1" max="30" value="7" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>
                    <div>
                        <label for="gbLimit" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-database mr-2"></i>จำกัดปริมาณข้อมูล (GB)
                        </label>
                        <input type="number" id="gbLimit" name="gbLimit" min="0" value="0" placeholder="0 = ไม่จำกัด" class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>
                    <div>
                        <label for="ipLimit" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-network-wired mr-2"></i>จำนวนอุปกรณ์
                        </label>
                        <input type="number" id="ipLimit" name="ipLimit" min="1" max="10" value="1" class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                    <i class="fas fa-plus-circle mr-2"></i>สร้างโค้ด VPN <span id="creditCostDisplay" class="ml-2 bg-red-500 bg-opacity-30 text-red-200 px-2 py-1 rounded">4 เครดิต</span>
                </button>
            </form>

            <!-- Confirmation Modal -->
            <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="glass-card rounded-2xl p-8 max-w-md mx-auto">
                    <h3 class="text-xl font-semibold text-white mb-4">ยืนยันการสร้างโค้ด VPN</h3>
                    <div class="space-y-4 mb-6">
                        <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                            <span class="text-gray-300"><i class="fas fa-tag mr-2"></i>แพ็กเกจ:</span>
                            <span id="confirm-package" class="text-white font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                            <span class="text-gray-300"><i class="fas fa-file-signature mr-2"></i>ชื่อโค้ด:</span>
                            <span id="confirm-codeName" class="text-white font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                            <span class="text-gray-300"><i class="fas fa-clock mr-2"></i>ระยะเวลา:</span>
                            <span id="confirm-timeAmount" class="text-white font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                            <span class="text-gray-300"><i class="fas fa-database mr-2"></i>ปริมาณข้อมูล:</span>
                            <span id="confirm-gbLimit" class="text-white font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                            <span class="text-gray-300"><i class="fas fa-network-wired mr-2"></i>จำนวนอุปกรณ์:</span>
                            <span id="confirm-ipLimit" class="text-white font-medium"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300"><i class="fas fa-coins mr-2"></i>เครดิตที่ใช้:</span>
                            <span id="confirm-credits" class="text-white font-medium text-xl"></span>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button id="cancelBtn" class="w-1/2 bg-gray-600 text-white py-3 rounded-lg hover:bg-gray-700 transition duration-200 flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i>ยกเลิก
                        </button>
                        <button id="confirmSubmitBtn" class="w-1/2 bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                            <i class="fas fa-check mr-2"></i>ยืนยัน
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 mt-6">
                <a href="vpn_history.php" class="btn-secondary w-full bg-gray-600 bg-opacity-20 text-gray-200 py-3 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center justify-center">
                    <i class="fas fa-history mr-2"></i>ประวัติการสร้างโค้ด VPN
                </a>
                <a href="index.php" class="btn-secondary w-full bg-gray-600 bg-opacity-20 text-gray-200 py-3 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>กลับไปหน้าหลัก
                </a>
            </div>
        </div>
    </main>

    <script>
        // Initialize Particles.js
        particlesJS('particles-js', {
            particles: {
                number: { value: 100, density: { enable: true, value_area: 800 } },
                color: { value: ['#ffffff', '#a5b4fc', '#f0abfc'] },
                shape: { type: 'circle' },
                opacity: { value: 0.6, random: true },
                size: { value: 2, random: true },
                line_linked: { enable: false },
                move: { enable: true, speed: 1, direction: 'none', random: true, straight: false, out_mode: 'out', bounce: false }
            },
            interactivity: {
                detect_on: 'canvas',
                events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: true, mode: 'push' }, resize: true },
                modes: { repulse: { distance: 100 }, push: { particles_nb: 4 } }
            },
            retina_detect: true
        });

        // User menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = this.querySelector('.user-dropdown');
                    dropdown.classList.toggle('hidden');
                });

                document.addEventListener('click', function(e) {
                    const dropdown = userMenu.querySelector('.user-dropdown');
                    if (!userMenu.contains(e.target)) {
                        dropdown.classList.add('hidden');
                    }
                });
            }

            // Package selection
            const packageOptions = document.querySelectorAll('.package-option');
            const profileKeyInput = document.getElementById('profileKey');
            const packageError = document.getElementById('packageError');
            const formElement = document.querySelector('form');

            packageOptions.forEach(option => {
                option.addEventListener('click', function() {
                    packageOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    profileKeyInput.value = this.dataset.package;
                    packageError.classList.add('hidden');
                });
            });

            if (formElement) {
                formElement.addEventListener('submit', function(e) {
                    if (!profileKeyInput.value) {
                        e.preventDefault();
                        packageError.classList.remove('hidden');
                        packageError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }

            // Copy to clipboard
            const copyBtn = document.getElementById('copyBtn');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    const codeText = this.getAttribute('data-clipboard-text');
                    navigator.clipboard.writeText(codeText).then(() => {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check mr-1"></i>คัดลอกแล้ว';
                        this.classList.add('bg-green-500', 'bg-opacity-30');
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.classList.remove('bg-green-500', 'bg-opacity-30');
                        }, 2000);
                    });
                });
            }

            // QR Code toggle
            const showQRBtn = document.getElementById('showQRBtn');
            const qrCodeDiv = document.getElementById('qrCode');
            const qrCodeCanvas = document.getElementById('qrCodeCanvas');

            if (showQRBtn && qrCodeDiv && qrCodeCanvas) {
                showQRBtn.addEventListener('click', function() {
                    if (qrCodeDiv.classList.contains('hidden')) {
                        qrCodeDiv.classList.remove('hidden');
                        const vlessCode = document.querySelector('.vless-code').textContent.trim();
                        QRCode.toCanvas(qrCodeCanvas, vlessCode, { 
                            width: 200,
                            margin: 2,
                            color: { dark: '#1f2937', light: '#ffffff' }
                        });
                        this.innerHTML = '<i class="fas fa-eye-slash mr-2"></i>ซ่อน QR Code';
                    } else {
                        qrCodeDiv.classList.add('hidden');
                        this.innerHTML = '<i class="fas fa-qrcode mr-2"></i>แสดง QR Code';
                    }
                });
            }

            // Auto-hide message
            const messageDiv = document.querySelector('.message');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.5s';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => {
                        messageDiv.remove();
                        window.location.replace(window.location.pathname);
                    }, 500);
                }, 5000);
            }

            // Processing counter
            const loadingModal = document.getElementById('loadingModal');
            const processingCounter = document.getElementById('processingCounter');

            if (loadingModal && processingCounter) {
                let counter = 0;
                const interval = setInterval(() => {
                    counter++;
                    processingCounter.textContent = `${counter}s`;
                    if (counter >= 20) {
                        clearInterval(interval);
                    }
                }, 1000);

                // Show loading modal on form submit
                formElement.addEventListener('submit', function(e) {
                    // Prevent default form submission
                    e.preventDefault();
                    
                    // Get form values
                    const packageElement = document.querySelector('.package-option.selected');
                    const packageName = packageElement ? packageElement.querySelector('strong').textContent : '';
                    const codeName = document.getElementById('codeName').value;
                    const timeAmount = document.getElementById('timeAmount').value;
                    const gbLimit = document.getElementById('gbLimit').value;
                    const ipLimit = document.getElementById('ipLimit').value;
                    const creditsUsed = 4 * timeAmount;
                    
                    // Update cost display
                    document.getElementById('creditCostDisplay').textContent = `${creditsUsed} เครดิต`;
                    
                    // Populate confirmation modal
                    document.getElementById('confirm-package').textContent = packageName;
                    document.getElementById('confirm-codeName').textContent = codeName;
                    document.getElementById('confirm-timeAmount').textContent = `${timeAmount} วัน`;
                    document.getElementById('confirm-gbLimit').textContent = gbLimit > 0 ? `${gbLimit} GB` : 'ไม่จำกัด';
                    document.getElementById('confirm-ipLimit').textContent = ipLimit;
                    document.getElementById('confirm-credits').textContent = `${creditsUsed} เครดิต`;
                    
                    // Show confirmation modal
                    document.getElementById('confirmationModal').classList.remove('hidden');
                });

                // Hide loading modal on success
                const successModal = document.getElementById('successModal');
                const confirmBtn = document.getElementById('confirmBtn');

                if (successModal && confirmBtn) {
                    confirmBtn.addEventListener('click', function() {
                        successModal.classList.add('hidden');
                        window.location.reload();
                    });
                }
                
                // Confirmation modal handlers
                const confirmationModal = document.getElementById('confirmationModal');
                const cancelBtn = document.getElementById('cancelBtn');
                const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
                
                if (confirmationModal && cancelBtn && confirmSubmitBtn) {
                    cancelBtn.addEventListener('click', function() {
                        confirmationModal.classList.add('hidden');
                    });
                    
                    confirmSubmitBtn.addEventListener('click', function() {
                        confirmationModal.classList.add('hidden');
                        loadingModal.classList.remove('hidden');
                        formElement.submit();
                    });
                }
                
                // Update credit cost display when changing time amount
                const timeAmountInput = document.getElementById('timeAmount');
                if (timeAmountInput) {
                    timeAmountInput.addEventListener('input', function() {
                        const creditsUsed = 4 * this.value;
                        document.getElementById('creditCostDisplay').textContent = `${creditsUsed} เครดิต`;
                    });
                }
            }
        });
    </script>
</body>
</html>