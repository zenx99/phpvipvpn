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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ประวัติโค้ด VPN - VIP VPN</title>
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
        .btn-primary:hover, .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        /* History item hover animation */
        .history-item:hover {
            transform: translateY(-4px);
            border-color: #22d3ee;
        }

        /* Fade-in animation for history items */
        .history-item {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .logo {
                height: 2.5rem;
            }
            .history-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-row {
                grid-template-columns: 1fr;
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
        <div class="glass-card rounded-2xl p-8 max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6"><i class="fas fa-history mr-2"></i>ประวัติการสร้างโค้ด VPN</h2>

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
                    <div class="history-item glass-card p-6 mb-4 rounded-lg border border-transparent transition duration-200" id="item-<?php echo $item['id']; ?>">
                        <div class="history-header flex flex-wrap justify-between items-center mb-4 gap-4">
                            <div class="flex items-center flex-wrap gap-2">
                                <span class="package-badge bg-cyan-500 bg-opacity-20 text-cyan-300 px-2 py-1 rounded-full text-sm font-semibold"><?php echo htmlspecialchars($packageName); ?></span>
                                <strong class="text-lg text-white"><?php echo htmlspecialchars($item['code_name']); ?></strong>
                            </div>
                            <span class="history-badge px-2 py-1 rounded-full text-sm font-semibold <?php echo $isExpired ? 'bg-red-500 bg-opacity-20 text-red-300' : 'bg-green-500 bg-opacity-20 text-green-300'; ?>">
                                <i class="fas fa-<?php echo $isExpired ? 'times-circle' : 'check-circle'; ?> mr-1"></i>
                                <?php echo $isExpired ? 'หมดอายุ' : 'ใช้งานได้'; ?>
                            </span>
                        </div>
                        <div class="relative bg-white bg-opacity-10 p-4 rounded-lg mb-4">
                            <code class="vless-code text-gray-200 text-sm break-all"><?php echo htmlspecialchars($item['vless_code']); ?></code>
                            <button class="copy-btn absolute top-2 right-2 bg-white bg-opacity-20 text-gray-200 px-3 py-1 rounded-lg hover:bg-opacity-30 transition duration-200" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($item['vless_code']); ?>')">
                                <i class="fas fa-copy mr-1"></i>คัดลอก
                            </button>
                        </div>
                        <div class="stats-row grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-gray-300 text-sm">
                            <div class="flex items-center bg-white bg-opacity-10 p-2 rounded-lg">
                                <i class="fas fa-clock mr-2 text-cyan-300"></i> สร้างเมื่อ: <?php echo htmlspecialchars($item['created_at']); ?>
                            </div>
                            <div class="flex items-center bg-white bg-opacity-10 p-2 rounded-lg">
                                <i class="fas fa-calendar mr-2 text-cyan-300"></i> หมดอายุ: <?php echo htmlspecialchars($expiryDate); ?>
                            </div>
                            <div class="flex items-center bg-white bg-opacity-10 p-2 rounded-lg">
                                <i class="fas fa-database mr-2 text-cyan-300"></i> 
                                <?php echo $item['gb_limit'] > 0 ? htmlspecialchars($item['gb_limit']) . ' GB' : 'ไม่จำกัด'; ?>
                            </div>
                            <div class="flex items-center bg-white bg-opacity-10 p-2 rounded-lg">
                                <i class="fas fa-network-wired mr-2 text-cyan-300"></i> 
                                <?php echo htmlspecialchars($item['ip_limit']); ?> เครื่อง
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-card p-8 text-center rounded-lg">
                    <i class="fas fa-history text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-300 text-lg mb-4">ยังไม่มีประวัติการสร้างโค้ด VPN</p>
                </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row gap-4 mt-6 justify-center">
                <a href="save_vless.php" class="btn-primary w-full sm:w-auto bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 px-6 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                    <i class="fas fa-plus-circle mr-2"></i>สร้างโค้ด VPN ใหม่
                </a>
                <a href="index.php" class="btn-secondary w-full sm:w-auto bg-gray-600 bg-opacity-20 text-gray-200 py-3 px-6 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center justify-center">
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

        // Copy to clipboard function
        function copyToClipboard(button, text) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check mr-1"></i>คัดลอกแล้ว';
                button.classList.add('bg-green-500', 'bg-opacity-30');
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('bg-green-500', 'bg-opacity-30');
                }, 2000);
            });
        }

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
        });
    </script>
</body>
</html>