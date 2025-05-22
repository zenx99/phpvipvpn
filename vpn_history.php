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

// Fetch VPN history
$history = [];
$stmt = $db->prepare('
    SELECT 
        id,
        code_name,
        profile_key,
        vless_code,
        expiry_time,
        gb_limit,
        ip_limit,
        created_at
    FROM vpn_history 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
');
$stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $history[] = $row;
}

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติโค้ด VPN - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .history-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .history-item {
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .history-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .history-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-expired {
            background: #ffebee;
            color: #c62828;
        }
        .code-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
            position: relative;
        }
        .copy-btn {
            position: absolute;
            right: 10px;
            top: 10px;
            background: white;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .copy-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .package-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 10px;
        }
        .stats-row {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }
        .no-history {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        .no-history i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
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

    <div class="history-container">
        <h2><i class="fas fa-history"></i> ประวัติการสร้างโค้ด VPN</h2>
        
        <?php if (count($history) > 0): ?>
            <?php foreach ($history as $item): ?>
                <?php 
                    $isExpired = time() > $item['expiry_time'];
                    $expiryDate = date('Y-m-d H:i:s', $item['expiry_time']);
                    $packageName = match($item['profile_key']) {
                        'true_dtac_nopro' => 'True/Dtac ไม่จำกัด',
                        'true_zoom' => 'True Zoom/Work',
                        'ais' => 'AIS ไม่จำกัด',
                        'true_pro_facebook' => 'True Pro Facebook',
                        default => $item['profile_key']
                    };
                ?>
                <div class="history-item">
                    <div class="history-header">
                        <div>
                            <span class="package-badge">
                                <?php echo htmlspecialchars($packageName); ?>
                            </span>
                            <strong><?php echo htmlspecialchars($item['code_name']); ?></strong>
                        </div>
                        <span class="history-badge <?php echo $isExpired ? 'badge-expired' : 'badge-active'; ?>">
                            <?php echo $isExpired ? 'หมดอายุ' : 'ใช้งานได้'; ?>
                        </span>
                    </div>
                    
                    <div class="code-box">
                        <?php echo htmlspecialchars($item['vless_code']); ?>
                        <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($item['vless_code']); ?>')">
                            <i class="fas fa-copy"></i> คัดลอก
                        </button>
                    </div>
                    
                    <div class="stats-row">
                        <div>
                            <i class="fas fa-clock"></i> สร้างเมื่อ: <?php echo htmlspecialchars($item['created_at']); ?>
                        </div>
                        <div>
                            <i class="fas fa-calendar"></i> หมดอายุ: <?php echo htmlspecialchars($expiryDate); ?>
                        </div>
                        <div>
                            <i class="fas fa-database"></i> 
                            ข้อมูล: <?php echo $item['gb_limit'] > 0 ? htmlspecialchars($item['gb_limit']) . ' GB' : 'ไม่จำกัด'; ?>
                        </div>
                        <div>
                            <i class="fas fa-network-wired"></i> 
                            อุปกรณ์: <?php echo htmlspecialchars($item['ip_limit']); ?> เครื่อง
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-history">
                <i class="fas fa-history"></i>
                <p>ยังไม่มีประวัติการสร้างโค้ด VPN</p>
                <a href="save_vless.php" class="btn">
                    <i class="fas fa-plus-circle"></i> สร้างโค้ด VPN ใหม่
                </a>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px; text-align: center;">
            <a href="save_vless.php" class="btn">
                <i class="fas fa-plus-circle"></i> สร้างโค้ด VPN ใหม่
            </a>
            <a href="index.php" class="btn" style="background: #6c757d;">
                <i class="fas fa-arrow-left"></i> กลับไปหน้าหลัก
            </a>
        </div>
    </div>

    <script>
        function copyToClipboard(button, text) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> คัดลอกแล้ว';
                button.style.background = '#4caf50';
                button.style.color = 'white';
                button.style.borderColor = '#4caf50';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = 'white';
                    button.style.color = 'inherit';
                    button.style.borderColor = '#ddd';
                }, 2000);
            });
        }

        // User menu dropdown
        document.addEventListener('DOMContentLoaded', function() {
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