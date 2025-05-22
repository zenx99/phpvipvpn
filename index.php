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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>VIP VPN Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
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

        /* Button hover animation */
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        /* Fade-in animation for messages */
        .announcement-text {
            animation: slideText 15s linear infinite;
        }

        @keyframes slideText {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .logo {
                height: 2.5rem;
            }
            .header {
                padding: 1rem;
            }
        }

        /* Blinking status light */
        @keyframes blink {
            0%, 50% { opacity: 1; }
            50%, 100% { opacity: 0.3; }
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
                        <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($username); ?>
                    </div>
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
        <!-- Announcements -->
        <div class="glass-card rounded-2xl p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-bell mr-2"></i>ประกาศ</h2>
            <div class="text-gray-300 mb-2">สมาชิกใหม่ในรอบ 24 ชั่วโมง:</div>
            <div class="bg-white bg-opacity-10 rounded-lg p-4 max-h-36 overflow-y-auto">
                <?php if (count($newUsers) > 0): ?>
                    <div class="relative overflow-hidden">
                        <div class="announcement-text flex space-x-8">
                            <?php foreach ($newUsers as $newUser): ?>
                                <span class="inline-block whitespace-nowrap text-gray-200">
                                    <?php echo $newUser['timeAgo']; ?> ยินดีต้อนรับ
                                    <span class="text-cyan-300 font-semibold"><?php echo htmlspecialchars($newUser['username']); ?></span>
                                    สมาชิกใหม่ของเรา
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-300 py-4">ยังไม่มีสมาชิกใหม่ในขณะนี้</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thai Server -->
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-server mr-2"></i>เซิร์ฟเวอร์ไทย</h2>
            <div class="flex flex-col items-center">
                <i class="fas fa-server text-4xl text-cyan-300 mb-4"></i>
                <div class="text-lg font-semibold text-white mb-2">เซิร์ฟเวอร์ไทย</div>
                <div class="flex items-center mb-4">
                    <span id="status-light" class="w-3 h-3 rounded-full mr-2 bg-green-500 animate-[blink_1s_infinite]"></span>
                    <span id="status-text" class="text-green-300 font-medium">ออนไลน์</span>
                </div>
                <div id="server-stats" class="text-gray-300 text-sm mb-4">Ping: 15ms | โหลด: 45%</div>
                <a href="save_vless.php" class="btn-primary bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-2 px-6 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i>สร้างโค้ด VPN
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-6 text-gray-300">
        © 2025 VIP VPN Thailand. All rights reserved.
    </footer>

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

            // Connect button effect
            const connectBtn = document.querySelector('.btn-primary');
            if (connectBtn) {
                connectBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>กำลังเชื่อมต่อ...';
                    this.disabled = true;
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-check mr-2"></i>เชื่อมต่อแล้ว';
                        this.classList.remove('bg-gradient-to-r', 'from-cyan-500', 'to-purple-600', 'hover:from-cyan-600', 'hover:to-purple-700');
                        this.classList.add('bg-gradient-to-r', 'from-green-500', 'to-green-600');
                        setTimeout(() => {
                            window.location.href = this.href;
                        }, 1000);
                    }, 2000);
                });
            }

            // Dynamic ping/load update and status light
            const statsEl = document.getElementById('server-stats');
            const statusLight = document.getElementById('status-light');
            setInterval(() => {
                const ping = Math.floor(Math.random() * 291) + 10; // 10-300ms
                const load = Math.floor(Math.random() * 101); // 0-100%
                if (statsEl) {
                    statsEl.textContent = `Ping: ${ping}ms | โหลด: ${load}%`;
                }
                if (statusLight) {
                    statusLight.classList.remove('bg-green-500', 'bg-red-500');
                    statusLight.classList.add(ping <= 100 ? 'bg-green-500' : 'bg-red-500');
                }
            }, 3000);
        });
    </script>
</body>
</html>