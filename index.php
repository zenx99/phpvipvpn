<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit;
}

// Get user info from session
$username = $_SESSION['username'];

// Database connection
$db = new SQLite3(__DIR__ . '/vipvpn.db');

// Fetch user credits
$stmt = $db->prepare('SELECT credits FROM users WHERE id = :id');
$stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$credits = $row ? $row['credits'] : 0;

// Get new users from the last 24 hours with their creation time
try {
    $stmt = $db->prepare('SELECT username, created_at, (julianday("now") - julianday(created_at)) * 24 as hours_ago FROM users WHERE created_at >= datetime("now", "-1 day") ORDER BY created_at DESC');
    if ($stmt) {
        $result = $stmt->execute();
        $newUsers = [];
        while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
            $hoursAgo = floor($user['hours_ago']);
            $minutesAgo = floor(($user['hours_ago'] - $hoursAgo) * 60);
            
            if ($hoursAgo > 0) {
                $timeAgo = "{$hoursAgo} ชั่วโมง";
            } else if ($minutesAgo > 0) {
                $timeAgo = "{$minutesAgo} นาที";
            } else {
                $timeAgo = "เมื่อสักครู่";
            }
            
            $newUsers[] = [
                'username' => $user['username'],
                'timeAgo' => $timeAgo
            ];
        }
    } else {
        $newUsers = [];
    }
} catch (Exception $e) {
    $newUsers = [];
}

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIP VPN Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
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
                <div class="dropdown-item user-profile">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                </div>
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
    </div> <!-- end of header -->
    
    <!-- Dashboard content -->        <div class="dashboard">
        <!-- New users announcements -->
        <div class="dashboard-container" style="margin-bottom: 20px;">
            <h2 class="dashboard-title"><i class="fas fa-bell"></i> ประกาศ</h2>
            <div style="padding: 10px;">
                <div style="font-weight: 500; color: #666; margin-bottom: 10px;">สมาชิกใหม่ในรอบ 24 ชั่วโมง:</div>
                <div class="new-users-scroll" style="max-height: 150px; overflow-y: auto; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                    <style>
                        @keyframes slideText {
                            0% { transform: translateX(100%); }
                            100% { transform: translateX(-100%); }
                        }
                        .announcement-text {
                            white-space: nowrap;
                            animation: slideText 15s linear infinite;
                            color: #2ecc71;
                            font-weight: 500;
                            font-size: 16px;
                        }
                        .user-join {
                            display: inline-block;
                            margin: 0 30px;
                            color: #666;
                        }
                    </style>
                    <div style="position: relative; overflow: hidden;">
                        <?php if (count($newUsers) > 0): ?>
                            <div class="announcement-text">
                                <?php foreach ($newUsers as $newUser): ?>
                                    <span class="user-join">
                                        <?php echo $newUser['timeAgo']; ?> ยินดีต้อนรับ
                                        <span style="color: #4a90e2; font-weight: 600;"><?php echo htmlspecialchars($newUser['username']); ?></span>
                                        สมาชิกใหม่ของเรา
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; color: #666; padding: 20px;">
                                ยังไม่มีสมาชิกใหม่ในขณะนี้
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Thai server card -->
        <div class="dashboard-container">
            <h2 class="dashboard-title"><i class="fas fa-server"></i> เซิร์ฟเวอร์ไทย</h2>
            <div style="display: flex; flex-direction: column; align-items: center; margin-top: 20px; padding: 10px;">
                <i class="fas fa-server" style="font-size: 24px; color: var(--success-color); margin-bottom: 10px;"></i>
                <div style="font-weight: 600; font-size: 18px; margin-bottom: 5px; text-align: center;">เซิร์ฟเวอร์ไทย</div>
                <div style="color: var(--success-color); font-weight: 500; margin-bottom: 10px;">ออนไลน์</div>
                <div style="color: #777; font-size: 14px; margin-bottom: 15px; text-align: center;">Ping: 15ms | โหลด: 45%</div>
                <a href="save_vless.php" class="btn" style="width: 200px; max-width: 100%; display: inline-block; text-decoration: none;">
                    <i class="fas fa-plus-circle"></i> สร้างโค้ด VPN
                </a>
            </div>
        </div>
    </div>

    <div class="footer-copyright" style="text-align: center; padding: 20px; color: #777; margin-top: 20px;">
        &copy; 2025 VIP VPN Thailand. All rights reserved.
    </div>
    
    <script>
        // เพิ่ม JavaScript เพื่อเพิ่มความสามารถให้กับแดชบอร์ด
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่มเอฟเฟกต์เมื่อคลิกที่ปุ่มเชื่อมต่อ
            const connectBtn = document.querySelector('.btn');
            if (connectBtn) {
                connectBtn.addEventListener('click', function() {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังเชื่อมต่อ...';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-check"></i> เชื่อมต่อแล้ว';
                        this.style.background = 'linear-gradient(to right, #2ecc71, #27ae60)';
                    }, 2000);
                });
            }
            
            // เพิ่มเอฟเฟกต์เมนูผู้ใช้
            const menuToggle = document.querySelector('.user-info');
            const menuDropdown = document.querySelector('.user-dropdown');
            
            if (menuToggle && menuDropdown) {
                // Toggle menu on click
                menuToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    menuDropdown.classList.toggle('show');
                });

                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!menuDropdown.contains(e.target) && !menuToggle.contains(e.target)) {
                        menuDropdown.classList.remove('show');
                    }
                });

                // Close menu when clicking menu items
                const menuItems = menuDropdown.querySelectorAll('.dropdown-item');
                menuItems.forEach(item => {
                    item.addEventListener('click', () => {
                        menuDropdown.classList.remove('show');
                    });
                });
                
                // ปิดเมนูเมื่อคลิกที่รายการในเมนู
                document.querySelectorAll('.dropdown-item').forEach(item => {
                    item.addEventListener('click', function() {
                        if (!this.getAttribute('href')) {
                            userMenu.classList.remove('active');
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
