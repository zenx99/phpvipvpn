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

// Check if Voucher class file exists before including it
$voucherClassExists = file_exists('./src/Voucher.php');

if ($voucherClassExists) {
    require('./src/Voucher.php');
    // This class will only be used if the file exists
    class_exists('M4h45amu7x\Voucher');
}

// Handle form submission for truemoney voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_url']) && $voucherClassExists) {
    $voucherUrl = $_POST['voucher_url'] ?? '';

    // Validate input
    if (empty($voucherUrl)) {
        $messageType = 'error';
        $message = 'กรุณาใส่ลิงก์อั่งเปา';
    } else {
        try {
            // Use admin's number or configurable number for redemption
            $mobileNumber = '0825658423'; // You might want to put this in a config file
            
            $voucher = new Voucher($mobileNumber, $voucherUrl);
            
            // First verify the voucher
            $verification = $voucher->verify();
            
            if (isset($verification['status']['code']) && $verification['status']['code'] === "VOUCHER_NOT_FOUND") {
                $messageType = 'error';
                $message = 'ลิงก์อั่งเปาไม่ถูกต้อง หรือถูกใช้ไปแล้ว';
            } else if (isset($verification['data']['voucher'])) {
                // Voucher is valid, proceed to redeem
                $redemption = $voucher->redeem();
                
                if (isset($redemption['status']['code']) && $redemption['status']['code'] === "SUCCESS") {
                    // Successful redemption
                    $amount = $redemption['data']['voucher']['amount_baht'];
                    $creditRate = 1; // 1 credit per 1 baht
                    $creditsToAdd = $amount * $creditRate;
                    
                    // Update user credits in database
                    $updateStmt = $db->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');
                    $updateStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                    $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                    $updateStmt->execute();
                    
                    // Record the transaction
                    $transactionStmt = $db->prepare('INSERT INTO topup_history (user_id, amount, credits, method, reference) VALUES (:user_id, :amount, :credits, :method, :reference)');
                    $transactionStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $transactionStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                    $transactionStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                    $transactionStmt->bindValue(':method', 'truemoney', SQLITE3_TEXT);
                    $transactionStmt->bindValue(':reference', $redemption['data']['voucher']['voucher_id'], SQLITE3_TEXT);
                    $transactionStmt->execute();
                    
                    $messageType = 'success';
                    $message = "เติมเงินสำเร็จ คุณได้รับ {$creditsToAdd} เครดิต";
                    
                    // Update credits for display
                    $credits += $creditsToAdd;
                } else {
                    // Failed redemption
                    $messageType = 'error';
                    $message = 'ไม่สามารถเติมเงินได้ อั่งเปาอาจถูกใช้ไปแล้วหรือหมดอายุ';
                }
            } else {
                $messageType = 'error';
                $message = 'ไม่สามารถตรวจสอบอั่งเปาได้ โปรดลองอีกครั้งในภายหลัง';
            }
        } catch (Exception $e) {
            $messageType = 'error';
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// Handle TrueWallet slip verification
if (isset($_POST['verify_tw_slip']) && isset($_FILES['tw_slip'])) {
    $target_dir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_file = $target_dir . basename($_FILES["tw_slip"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["tw_slip"]["tmp_name"]);
    if($check === false) {
        $messageType = 'error';
        $message = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ';
    } 
    // Check file size (limit to 2MB)
    elseif ($_FILES["tw_slip"]["size"] > 2000000) {
        $messageType = 'error';
        $message = 'ขนาดไฟล์เกิน 2MB';
    }
    // Allow only JPG, JPEG, PNG
    elseif($imageFileType != "jpg" && $imageFileType != "jpeg" && $imageFileType != "png") {
        $messageType = 'error';
        $message = 'อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG เท่านั้น';
    } 
    else {
        // Generate unique filename
        $temp_filename = uniqid('slip_', true) . '.' . $imageFileType;
        $target_file = $target_dir . $temp_filename;
        
        if (move_uploaded_file($_FILES["tw_slip"]["tmp_name"], $target_file)) {
            // Initialize cURL
            $curl = curl_init();
            $data = [
                'ClientID-Secret' => '12345668ac5d834ae6dadfb97890:59c3fe615570b9a0f643c112a302e45090a4a7470c725326', // Replace with your actual API key
                'image' => new CURLFile($target_file)
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://thaislip.xncly.xyz/api/v1/slipverify-tw",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            // Delete the temporary file
            unlink($target_file);
            
            if ($err) {
                $messageType = 'error';
                $message = 'เกิดข้อผิดพลาดในการตรวจสอบสลิป: ' . $err;
            } else {
                $result = json_decode($response, true);
                
                if ($result && isset($result['status']) && $result['status'] === true) {
                    // Successful verification
                    $amountStr = $result['result']['amount'];
                    $amount = floatval($amountStr);
                    $transRef = $result['result']['transRef'];
                    
                    // Check if this transaction has been used before
                    $checkStmt = $db->prepare('SELECT id FROM topup_history WHERE reference = :reference');
                    $checkStmt->bindValue(':reference', $transRef, SQLITE3_TEXT);
                    $checkResult = $checkStmt->execute();
                    
                    if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
                        // This transaction has been used
                        $messageType = 'error';
                        $message = 'รายการโอนเงินนี้ถูกใช้งานไปแล้ว';
                    } else {
                        // Calculate credits (1 baht = 1 credit)
                        $creditRate = 1;
                        $creditsToAdd = $amount * $creditRate;
                        
                        // Update user credits
                        $updateStmt = $db->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');
                        $updateStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                        $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                        $updateStmt->execute();
                        
                        // Record transaction
                        $transactionStmt = $db->prepare('INSERT INTO topup_history (user_id, amount, credits, method, reference) VALUES (:user_id, :amount, :credits, :method, :reference)');
                        $transactionStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                        $transactionStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                        $transactionStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                        $transactionStmt->bindValue(':method', 'truewallet_slip', SQLITE3_TEXT);
                        $transactionStmt->bindValue(':reference', $transRef, SQLITE3_TEXT);
                        $transactionStmt->execute();
                        
                        $messageType = 'success';
                        $message = "เติมเงินสำเร็จ คุณได้รับ {$creditsToAdd} เครดิต";
                        
                        // Update credits for display
                        $credits += $creditsToAdd;
                    }
                } else {
                    // Failed verification
                    $messageType = 'error';
                    $message = 'ไม่สามารถตรวจสอบสลิปได้ โปรดตรวจสอบว่าเป็นสลิป TrueWallet ที่ถูกต้อง';
                }
            }
        } else {
            $messageType = 'error';
            $message = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
        }
    }
}

// Create topup_history table if it doesn't exist
$db->exec('
    CREATE TABLE IF NOT EXISTS topup_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        amount REAL,
        credits INTEGER,
        method TEXT,
        reference TEXT,
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
    <title>เติมเงิน - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
            --gradient-success: linear-gradient(135deg, #10b981, #059669);
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .topup-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .topup-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }
        
        .topup-container h2 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .topup-container h2 i {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.2em;
        }
        
        .topup-methods {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .topup-method {
            flex: 1;
            min-width: 200px;
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            position: relative;
        }
        
        .topup-method:hover {
            border-color: #a5b4fc;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .topup-method.active {
            border-color: #8b5cf6;
            background: #f5f3ff;
        }
        
        .topup-method.active::before {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #8b5cf6;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .topup-method img {
            height: 40px;
            margin-bottom: 10px;
        }
        
        .topup-method h3 {
            margin: 0;
            font-size: 16px;
            color: #1f2937;
        }
        
        .topup-method p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #6b7280;
        }
        
        .topup-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
        }
        
        .topup-form.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4b5563;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            color: #1f2937;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.25);
            background: white;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            gap: 10px;
            box-shadow: var(--shadow-md);
            border: none;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            color: #1f2937;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }
        
        .angpao-info {
            margin: 20px 0;
            padding: 20px;
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
        }
        
        .angpao-info h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #92400e;
            font-size: 16px;
            margin-top: 0;
        }
        
        .angpao-steps {
            list-style-type: none;
            padding: 0;
            margin: 15px 0 0;
        }
        
        .angpao-steps li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
            color: #92400e;
        }
        
        .angpao-steps li i {
            margin-right: 10px;
            margin-top: 3px;
        }
        
        .angpao-example {
            margin-top: 15px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #92400e;
            word-break: break-all;
        }
        
        .credit-rates {
            margin-top: 30px;
            padding: 20px;
            background: #f3f4f6;
            border-radius: 12px;
        }
        
        .credit-rates h3 {
            color: #1f2937;
            font-size: 18px;
            margin-top: 0;
        }
        
        .rate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .rate-table th, .rate-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .rate-table th {
            font-weight: 600;
            color: #4b5563;
            background: #f9fafb;
        }
        
        .rate-table td {
            color: #6b7280;
        }
        
        .topup-instructions {
            margin-top: 30px;
        }
        
        /* Custom file upload styling */
        input[type="file"] {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }
        
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 150px;
            border: 2px dashed #a5b4fc;
            border-radius: 8px;
            background-color: rgba(165, 180, 252, 0.1);
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .file-upload-label:hover {
            background-color: rgba(165, 180, 252, 0.2);
            border-color: #6366f1;
        }
        
        .file-upload-label i {
            font-size: 36px;
            color: #6366f1;
            margin-bottom: 10px;
        }
        
        .file-upload-label span {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 5px;
        }
        
        .file-upload-label small {
            font-size: 12px;
            color: #6b7280;
        }
        
        .file-preview {
            display: none;
            margin-top: 15px;
            text-align: center;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
        }
        
        .topup-instructions h3 {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .topup-methods {
                flex-direction: column;
            }
            
            .topup-method {
                width: 100%;
            }
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

    <div class="topup-container">
        <h2><i class="fas fa-plus-circle"></i> เติมเงิน</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>-message">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="topup-methods">
            <?php if ($voucherClassExists): ?>
            <div class="topup-method <?php echo $voucherClassExists ? '' : 'active'; ?>" data-method="truemoney">
                <img src="https://www.pngall.com/wp-content/uploads/5/TrueMoney-Wallet-Logo-PNG-Image.png" alt="TrueMoney Wallet">
                <h3>TrueMoney Wallet</h3>
                <p>เติมเงินด้วยอั่งเปาทรูมันนี่</p>
            </div>
            <?php endif; ?>
            <div class="topup-method <?php echo !$voucherClassExists ? 'active' : ''; ?>" data-method="tw_slip">
                <img src="https://www.pngall.com/wp-content/uploads/5/TrueMoney-Wallet-Logo-PNG-Image.png" alt="TrueMoney Slip">
                <h3>TrueWallet Slip</h3>
                <p>เติมเงินผ่านเช็คสลิปวอเล็ท</p>
            </div>
            <div class="topup-method" data-method="bank">
                <img src="https://cdn-icons-png.flaticon.com/512/2830/2830284.png" alt="Bank Transfer">
                <h3>โอนเงินผ่านธนาคาร</h3>
                <p>เติมเงินด้วยการโอนเงินผ่านธนาคาร</p>
            </div>
        </div>

        <!-- TrueMoney Form -->
        <?php if ($voucherClassExists): ?>
        <div class="topup-form <?php echo $voucherClassExists ? 'active' : ''; ?>" id="truemoney-form">
            <div class="angpao-info">
                <h3><i class="fas fa-gift"></i> วิธีการส่งอั่งเปาทรูมันนี่</h3>
                <ul class="angpao-steps">
                    <li><i class="fas fa-1"></i> เปิดแอป TrueMoney Wallet</li>
                    <li><i class="fas fa-2"></i> เลือก "ส่งของขวัญ" หรือ "อั่งเปา"</li>
                    <li><i class="fas fa-3"></i> เลือก "ส่งอั่งเปาให้เพื่อน (ลิงก์)"</li>
                    <li><i class="fas fa-4"></i> ใส่จำนวนเงินที่ต้องการเติม</li>
                    <li><i class="fas fa-5"></i> คัดลอกลิงก์อั่งเปาที่ได้มาวางในฟอร์มด้านล่าง</li>
                </ul>
                <div class="angpao-example">
                    ตัวอย่างลิงก์อั่งเปา: https://gift.truemoney.com/campaign?v=abcdefgh
                </div>
            </div>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label for="voucher_url"><i class="fas fa-link"></i> ลิงก์อั่งเปาทรูมันนี่ <span class="required-mark">*</span></label>
                    <input type="text" id="voucher_url" name="voucher_url" placeholder="https://gift.truemoney.com/campaign/?v=..." required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-check-circle"></i> ยืนยันการเติมเงิน
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- TrueWallet Slip Form -->
        <div class="topup-form <?php echo !$voucherClassExists ? 'active' : ''; ?>" id="tw_slip-form">
            <div class="topup-instructions">
                <h3><i class="fas fa-receipt"></i> วิธีการเติมเงินผ่านสลิป TrueWallet</h3>
                <ul class="angpao-steps">
                    <li><i class="fas fa-1"></i> โอนเงินผ่าน TrueWallet ไปยังเบอร์ 082-565-8423</li>
                    <li><i class="fas fa-2"></i> บันทึกภาพสลิปการโอนเงิน</li>
                    <li><i class="fas fa-3"></i> อัปโหลดสลิปในฟอร์มด้านล่าง</li>
                    <li><i class="fas fa-4"></i> รอระบบตรวจสอบและเติมเครดิตให้โดยอัตโนมัติ</li>
                </ul>
                <div class="angpao-example" style="color: #FF6F00; font-weight: bold;">
                    หมายเหตุ: แต่ละสลิปสามารถใช้ได้เพียงครั้งเดียวเท่านั้น
                </div>
            </div>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="tw_slip" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-upload"></i> อัปโหลดสลิป TrueWallet <span class="required-mark">*</span></label>
                    <label for="tw_slip" class="file-upload-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>คลิกเพื่อเลือกไฟล์หรือลากไฟล์มาวางที่นี่</span>
                        <small>รองรับไฟล์ JPG, JPEG, PNG ขนาดไม่เกิน 2MB</small>
                    </label>
                    <input type="file" id="tw_slip" name="tw_slip" accept="image/jpeg,image/png" required>
                    <div class="file-preview">
                        <img id="slip-preview" src="#" alt="ตัวอย่างสลิป">
                    </div>
                </div>
                <button type="submit" name="verify_tw_slip" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-check-circle"></i> ตรวจสอบและเติมเงิน
                </button>
            </form>
        </div>

        <!-- Bank Transfer Form -->
        <div class="topup-form" id="bank-form">
            <div class="topup-instructions">
                <h3>วิธีการโอนเงินผ่านธนาคาร</h3>
                <p>ทำการโอนเงินมาที่บัญชีด้านล่าง จากนั้นแจ้งสลิปโอนเงินกับแอดมินผ่านทางไลน์</p>
                
                <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin-top: 15px;">
                    <div style="margin-bottom: 15px;">
                        <p style="margin: 5px 0; color: #1f2937; font-weight: 600;">ธนาคารกสิกรไทย (KBANK)</p>
                        <p style="margin: 5px 0;">ชื่อบัญชี: บริษัท วีไอพี วีพีเอ็น จำกัด</p>
                        <p style="margin: 5px 0;">เลขที่บัญชี: 123-4-56789-0</p>
                    </div>
                    
                    <div>
                        <p style="margin: 5px 0; color: #1f2937; font-weight: 600;">แจ้งการโอนเงินได้ที่</p>
                        <p style="margin: 5px 0;">Line ID: @vipvpn</p>
                        <p style="margin: 5px 0;">พร้อมแนบสลิปโอนเงินและ Username ของคุณ</p>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> กลับสู่หน้าหลัก
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method selection
            const methodOptions = document.querySelectorAll('.topup-method');
            const methodForms = document.querySelectorAll('.topup-form');
            
            methodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const selectedMethod = this.dataset.method;
                    
                    // Update active method styling
                    methodOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding form
                    methodForms.forEach(form => {
                        form.classList.remove('active');
                        if (form.id === selectedMethod + '-form') {
                            form.classList.add('active');
                        }
                    });
                });
            });
            
            // TrueWallet Slip file upload preview
            const slipInput = document.getElementById('tw_slip');
            if (slipInput) {
                slipInput.addEventListener('change', function(e) {
                    const filePreview = document.querySelector('.file-preview');
                    const previewImg = document.getElementById('slip-preview');
                    const fileUploadLabel = document.querySelector('.file-upload-label');
                    
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        // Check file type
                        const fileType = file.type;
                        if (fileType !== 'image/jpeg' && fileType !== 'image/png') {
                            alert('รองรับเฉพาะไฟล์ JPG, JPEG และ PNG เท่านั้น');
                            this.value = '';
                            return;
                        }
                        
                        // Check file size (max 2MB)
                        if (file.size > 2 * 1024 * 1024) {
                            alert('ขนาดไฟล์ต้องไม่เกิน 2MB');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.setAttribute('src', e.target.result);
                            filePreview.style.display = 'block';
                            fileUploadLabel.style.borderColor = '#10b981';
                            fileUploadLabel.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                            fileUploadLabel.querySelector('i').style.color = '#10b981';
                            fileUploadLabel.querySelector('span').textContent = file.name;
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                // Drag and drop functionality
                const dropArea = document.querySelector('.file-upload-label');
                
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropArea.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, unhighlight, false);
                });

                function highlight() {
                    dropArea.style.backgroundColor = 'rgba(165, 180, 252, 0.3)';
                    dropArea.style.borderColor = '#6366f1';
                }

                function unhighlight() {
                    dropArea.style.backgroundColor = 'rgba(165, 180, 252, 0.1)';
                    dropArea.style.borderColor = '#a5b4fc';
                }
                
                dropArea.addEventListener('drop', handleDrop, false);
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    slipInput.files = files;
                    
                    // Trigger change event
                    const event = new Event('change', { bubbles: true });
                    slipInput.dispatchEvent(event);
                }
            }

            // User menu dropdown with animation
            const menuToggle = document.querySelector('.user-info');
            const menuDropdown = document.querySelector('.user-dropdown');
            
            if (menuToggle && menuDropdown) {
                menuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    if (menuDropdown.classList.contains('show')) {
                        // Hide dropdown with animation
                        menuDropdown.style.opacity = '0';
                        menuDropdown.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            menuDropdown.classList.remove('show');
                        }, 200);
                    } else {
                        // Show dropdown with animation
                        menuDropdown.classList.add('show');
                        setTimeout(() => {
                            menuDropdown.style.opacity = '1';
                            menuDropdown.style.transform = 'translateY(0)';
                        }, 10);
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!menuDropdown.contains(e.target) && !menuToggle.contains(e.target)) {
                        menuDropdown.style.opacity = '0';
                        menuDropdown.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            menuDropdown.classList.remove('show');
                        }, 200);
                    }
                });
            }
            
            // Form validation and enhanced UX for TrueMoney Gift
            const truemoneyForm = document.querySelector('#truemoney-form form');
            if (truemoneyForm) {
                truemoneyForm.addEventListener('submit', function(e) {
                    const voucherUrl = document.getElementById('voucher_url').value;
                    // Support both URL formats: with or without slash before query parameter
                    const validUrlPattern = /https:\/\/gift\.truemoney\.com\/campaign\/?(\?|&)v=[a-zA-Z0-9]+/i;
                    
                    if (!validUrlPattern.test(voucherUrl)) {
                        e.preventDefault();
                        alert('กรุณาใส่ลิงก์อั่งเปาที่ถูกต้อง');
                    }
                });
            }
            
            // Set initial active tab based on available methods
            if (!document.querySelector('#truemoney-form')) {
                const twSlipTab = document.querySelector('[data-method="tw_slip"]');
                if (twSlipTab) {
                    // Simulate click to activate the TW slip tab if it's not already active
                    if (!twSlipTab.classList.contains('active')) {
                        twSlipTab.click();
                    }
                }
            }
            
            // Animated form fields
            const formInputs = document.querySelectorAll('input, select');
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.position = 'relative';
                    this.parentElement.style.zIndex = '1';
                    this.style.boxShadow = '0 0 0 3px rgba(139, 92, 246, 0.15)';
                    this.style.borderColor = '#a5b4fc';
                    this.style.background = 'white';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.zIndex = '';
                    this.style.boxShadow = '';
                    if (!this.value) {
                        this.style.borderColor = '#e5e7eb';
                        this.style.background = '#f9fafb';
                    }
                });
            });
            
            // Hide alert message after 5 seconds
            const messageDiv = document.querySelector('.message');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.5s';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => {
                        messageDiv.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
