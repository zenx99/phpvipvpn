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
        created_at,
        is_enabled
    FROM vpn_history 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
');
$stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $history[] = $row;
}

// Check for any messages from other operations (like delete)
$message = '';
$messageType = '';

if (isset($_SESSION['message']) && isset($_SESSION['messageType'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    
    // Clear the message from session
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ตั้งค่าโค้ดเน็ต - VIP VPN</title>
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

        /* Delete button animation */
        @keyframes deleteButtonIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .delete-btn {
            animation: deleteButtonIn 0.3s ease-out;
        }
        
        .delete-btn:hover {
            background-color: rgba(239, 68, 68, 0.3) !important;
            transform: translateY(-2px);
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
            <h2 class="text-2xl font-bold text-white mb-6"><i class="fas fa-cog mr-2"></i>ตั้งค่าโค้ดเน็ต</h2>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>-message bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-500 bg-opacity-30 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-200 p-4 rounded-lg mb-6 flex items-center border border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-400">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (count($history) > 0): ?>
                <?php foreach ($history as $item): ?>
                    <?php 
                        // Set timezone to Asia/Bangkok (Thailand timezone)
                        date_default_timezone_set('Asia/Bangkok');
                        
                        $isExpired = time() > $item['expiry_time'];
                        $isEnabled = isset($item['is_enabled']) ? (bool)$item['is_enabled'] : true;
                        $expiryDate = date('Y-m-d H:i:s', $item['expiry_time']);
                        $createdDate = isset($item['created_at']) ? $item['created_at'] : date('Y-m-d H:i:s');
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
                            <?php
                                $badgeClass = 'bg-green-500 bg-opacity-20 text-green-300';
                                $iconClass = 'check-circle';
                                $statusText = 'ใช้งานได้';
                                
                                if ($isExpired) {
                                    $badgeClass = 'bg-red-500 bg-opacity-20 text-red-300';
                                    $iconClass = 'times-circle';
                                    $statusText = 'หมดอายุ';
                                } elseif (!$isEnabled) {
                                    $badgeClass = 'bg-yellow-500 bg-opacity-20 text-yellow-300';
                                    $iconClass = 'pause-circle';
                                    $statusText = 'ปิดใช้งาน';
                                }
                            ?>
                            <span class="history-badge px-2 py-1 rounded-full text-sm font-semibold <?php echo $badgeClass; ?>">
                                <i class="fas fa-<?php echo $iconClass; ?> mr-1"></i>
                                <?php echo $statusText; ?>
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
                                <i class="fas fa-clock mr-2 text-cyan-300"></i> สร้างเมื่อ: <?php echo htmlspecialchars($createdDate); ?>
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
                        
                        <div class="mt-4 flex flex-wrap gap-2 justify-end">
                            <!-- Toggle button -->
                            <form method="POST" action="toggle_vless.php" class="toggle-form" id="toggle-form-<?php echo $item['id']; ?>">
                                <input type="hidden" name="vpn_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="enable" value="<?php echo $isEnabled ? 'false' : 'true'; ?>">
                                <button type="submit" class="toggle-btn <?php echo $isEnabled ? 'bg-yellow-500 bg-opacity-20 text-yellow-300' : 'bg-green-500 bg-opacity-20 text-green-300'; ?> px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center">
                                    <i class="fas fa-<?php echo $isEnabled ? 'pause' : 'power-off'; ?> mr-2"></i><?php echo $isEnabled ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>
                                </button>
                            </form>
                            
                            <!-- Renew button -->
                            <button data-id="<?php echo $item['id']; ?>" onclick="openRenewModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['code_name']); ?>', <?php echo $item['gb_limit']; ?>, <?php echo $item['ip_limit']; ?>)" class="renew-btn bg-blue-500 bg-opacity-20 text-blue-300 px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i>ต่ออายุ
                            </button>
                            
                            <!-- Delete button -->
                            <form method="POST" action="delete_vless.php" onsubmit="return confirmDelete(event, <?php echo $item['id']; ?>);" id="delete-form-<?php echo $item['id']; ?>">
                                <input type="hidden" name="vpn_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="delete-btn bg-red-500 bg-opacity-20 text-red-300 px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center">
                                    <i class="fas fa-trash-alt mr-2"></i>ลบโค้ด
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-card p-8 text-center rounded-lg">
                    <i class="fas fa-history text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-300 text-lg mb-4">ไม่มีโค้ดเน็ต กรุณาซื้อก่อน</p>
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

    <!-- Renew Modal -->
    <div id="renewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="glass-card rounded-2xl p-8 max-w-md mx-auto">
            <h3 class="text-xl font-semibold text-white mb-4">ต่ออายุโค้ด VPN</h3>
            <form method="POST" action="update_vless.php">
                <input type="hidden" name="vpn_id" id="renew_vpn_id">
                <div class="space-y-4 mb-6">
                    <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                        <span class="text-gray-300"><i class="fas fa-file-signature mr-2"></i>ชื่อโค้ด:</span>
                        <span id="renew-codeName" class="text-white font-medium"></span>
                    </div>

                    <div>
                        <label for="renew-timeAmount" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-clock mr-2"></i>ระยะเวลา (วัน) <span class="text-red-300">*</span>
                        </label>
                        <input type="number" id="renew-timeAmount" name="timeAmount" min="1" max="30" value="7" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>

                    <div>
                        <label for="renew-gbLimit" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-database mr-2"></i>จำกัดปริมาณข้อมูล (GB)
                        </label>
                        <input type="number" id="renew-gbLimit" name="gbLimit" min="0" value="0" placeholder="0 = ไม่จำกัด" class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>

                    <div>
                        <label for="renew-ipLimit" class="block text-sm font-medium text-gray-200 mb-2">
                            <i class="fas fa-network-wired mr-2"></i>จำนวนอุปกรณ์
                        </label>
                        <input type="number" id="renew-ipLimit" name="ipLimit" min="1" max="10" value="1" class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>

                    <div class="flex justify-between items-center border-t border-gray-700 pt-2">
                        <span class="text-gray-300"><i class="fas fa-coins mr-2"></i>เครดิตที่ใช้:</span>
                        <span id="renew-credits" class="text-white font-medium text-xl">28 เครดิต</span>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeRenewModal()" class="w-1/2 bg-gray-600 text-white py-3 rounded-lg hover:bg-gray-700 transition duration-200 flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i>ยกเลิก
                    </button>
                    <button type="submit" class="w-1/2 bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                        <i class="fas fa-check mr-2"></i>ยืนยัน
                    </button>
                </div>
            </form>
        </div>
    </div>

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

        // Toggle button functionality
        function confirmDelete(event, id) {
            event.preventDefault();
            if (confirm('ต้องการลบโค้ด VPN นี้หรือไม่?')) {
                document.getElementById('delete-form-' + id).submit();
            }
            return false;
        }
        
        // Renew modal functions
        function openRenewModal(id, codeName, gbLimit, ipLimit) {
            document.getElementById('renew_vpn_id').value = id;
            document.getElementById('renew-codeName').textContent = codeName;
            document.getElementById('renew-gbLimit').value = gbLimit;
            document.getElementById('renew-ipLimit').value = ipLimit;
            
            // Update credit cost display
            const timeAmount = document.getElementById('renew-timeAmount').value;
            document.getElementById('renew-credits').textContent = (4 * timeAmount) + ' เครดิต';
            
            // Show modal with fade-in animation
            const modal = document.getElementById('renewModal');
            modal.classList.remove('hidden');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.transition = 'opacity 0.3s ease-out';
                modal.style.opacity = '1';
            }, 10);
        }
        
        function closeRenewModal() {
            const modal = document.getElementById('renewModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
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
            
            // Toggle button form submit confirmation
            const toggleForms = document.querySelectorAll('.toggle-form');
            toggleForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const enableInput = this.querySelector('input[name="enable"]');
                    const isEnabling = enableInput.value === 'true';
                    if (confirm(isEnabling ? 'ต้องการเปิดใช้งานโค้ด VPN นี้หรือไม่?' : 'ต้องการปิดการใช้งานโค้ด VPN นี้หรือไม่?')) {
                        this.submit();
                    }
                });
            });

            // Update the credits display in the renew modal
            const timeAmountInput = document.getElementById('renew-timeAmount');
            if (timeAmountInput) {
                timeAmountInput.addEventListener('input', function() {
                    const creditsUsed = 4 * this.value;
                    document.getElementById('renew-credits').textContent = creditsUsed + ' เครดิต';
                });
            }
            
            // Auto-hide messages after 5 seconds
            const messageDiv = document.querySelector('.message');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.5s';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => messageDiv.remove(), 500);
                }, 5000);
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('renewModal');
                if (event.target === modal) {
                    closeRenewModal();
                }
            });
        });
    </script>
</body>
</html>