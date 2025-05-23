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

// Initialize variables
$message = '';
$messageType = '';
$allOnlineClients = [];
$totalOnlineAll = 0;
$processedUsers = [];  // To keep track of users already seen

// Define all profiles we want to display
$validProfiles = ['true_dtac_nopro', 'true_zoom', 'ais', 'true_pro_facebook'];

// Define profile names for display
$profileNames = [
    'true_dtac_nopro' => 'True/Dtac ไม่จำกัด',
    'true_zoom' => 'True Zoom/Work',
    'ais' => 'AIS ไม่จำกัด',
    'true_pro_facebook' => 'True Pro Facebook'
];

// Fetch user credits
$stmt = $db->prepare('SELECT credits FROM users WHERE id = :id');
$stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$credits = $row ? $row['credits'] : 0;

try {
    // Make API call to get online users for all profiles
    $apiUrl = 'http://103.245.164.86:4040/client/onlines';
    
    foreach ($validProfiles as $profileKey) {
        $onlineClients = []; // Reset for each profile
        
        $postData = json_encode([
            'vpsType' => 'IDC',
            'profileKey' => $profileKey
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
            $message = 'การเชื่อมต่อผิดพลาด: ' . curl_error($ch);
            $messageType = 'error';
        } elseif ($httpCode !== 200) {
            $message = 'เซิร์ฟเวอร์ผิดพลาด (HTTP ' . $httpCode . ')';
            $messageType = 'error';
        } else {
            $json = json_decode($response, true);
            if (isset($json['success']) && $json['success'] && isset($json['list'])) {
                // Store the API's response - API already sends us the names directly in list
                $onlineData = $json['list'];
                
                // Directly use the names from API
                if ($profileOnlineCount > 0) {
                    $filteredUsers = [];
                    
                    // In a real implementation, if the API returned package-specific data
                    // we wouldn't need this filtering. But for now, let's assign each user
                    // to only one package to avoid duplication
                    foreach ($onlineData as $clientName) {
                        // Clean up the client name
                        $clientNameClean = trim($clientName);
                        
                        // Skip this user if we've already processed them in another profile
                        if (!empty($clientNameClean) && isset($processedUsers[$clientNameClean])) {
                            continue;
                        }
                        
                        // Add user to appropriate collection
                        if (!empty($clientNameClean)) {
                            // Mark this user as processed
                            $processedUsers[$clientNameClean] = true;
                            
                            $filteredUsers[] = [
                                'uuid' => $clientNameClean, // Not a UUID, but using this field for consistency
                                'name' => $clientNameClean,
                                'profile' => $profileKey,
                                'profileName' => $profileNames[$profileKey]
                            ];
                        } else if (!isset($processedUsers['unknown'])) {
                            $processedUsers['unknown'] = true;
                            $filteredUsers[] = [
                                'uuid' => 'unknown',
                                'name' => 'ไม่ทราบชื่อ',
                                'profile' => $profileKey, 
                                'profileName' => $profileNames[$profileKey]
                            ];
                        }
                    }
                    
                    $onlineClients = $filteredUsers;
                    // Now update count with filtered results
                    $profileOnlineCount = count($filteredUsers);
                    $totalOnlineAll += $profileOnlineCount;
                    
                    // Sort clients by name
                    usort($onlineClients, function($a, $b) {
                        // Get unknown clients to the bottom of the list
                        if ($a['name'] === 'ไม่ทราบชื่อ' && $b['name'] !== 'ไม่ทราบชื่อ') {
                            return 1;
                        }
                        if ($a['name'] !== 'ไม่ทราบชื่อ' && $b['name'] === 'ไม่ทราบชื่อ') {
                            return -1;
                        }
                        return strcmp($a['name'], $b['name']);
                    });
                    
                    // Add the clients to the main array with their profile info
                    $allOnlineClients[$profileKey] = [
                        'clients' => $onlineClients,
                        'count' => $profileOnlineCount,
                        'profileName' => $profileNames[$profileKey]
                    ];
                } else {
                    // Add empty array for this profile
                    $allOnlineClients[$profileKey] = [
                        'clients' => [],
                        'count' => 0,
                        'profileName' => $profileNames[$profileKey]
                    ];
                }
            } else {
                $message = 'เซิร์ฟเวอร์ตอบกลับข้อมูลไม่ถูกต้อง';
                $messageType = 'error';
            }
        }
        
        curl_close($ch);
    }
    
    // Close the last curl handle if it exists
    if (isset($infoCh)) {
        curl_close($infoCh);
    }
} catch (Exception $e) {
    $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $messageType = 'error';
}

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ผู้ใช้งานออนไลน์ - VIP VPN</title>
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
        }
        
        /* Package selector */
        .profile-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .profile-option {
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-option.active {
            border-color: #22d3ee;
            transform: translateY(-2px);
        }
        
        /* Online user counter */
        .online-counter {
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin: 30px 0;
            color: #22d3ee;
            text-shadow: 0 0 10px rgba(34, 211, 238, 0.5);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .capacity-bar-container {
            width: 100%;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 4px;
            margin: 20px 0;
        }
        
        .capacity-bar {
            height: 20px;
            border-radius: 8px;
            transition: width 1s ease-in-out;
        }
        
        /* Responsive adjustments */
        @media (max-width: 640px) {
            .logo {
                height: 2.5rem;
            }
            .profile-selector {
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
        <div class="glass-card rounded-2xl p-8 max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6"><i class="fas fa-users mr-2"></i>ผู้ใช้งานออนไลน์</h2>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>-message bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-500 bg-opacity-30 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-200 p-4 rounded-lg mb-6 flex items-center border border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-400">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-server mr-2"></i>เลือกแพ็กเกจ</h3>
                <div class="profile-selector">
                    <a href="?profile=true_dtac_nopro" class="profile-option glass-card <?php echo $profileKey === 'true_dtac_nopro' ? 'active' : ''; ?>">
                        <strong class="block text-white"><i class="fas fa-signal mr-2"></i>True/Dtac ไม่จำกัด</strong>
                    </a>
                    <a href="?profile=true_zoom" class="profile-option glass-card <?php echo $profileKey === 'true_zoom' ? 'active' : ''; ?>">
                        <strong class="block text-white"><i class="fas fa-video mr-2"></i>True Zoom/Work</strong>
                    </a>
                    <a href="?profile=ais" class="profile-option glass-card <?php echo $profileKey === 'ais' ? 'active' : ''; ?>">
                        <strong class="block text-white"><i class="fas fa-bolt mr-2"></i>AIS ไม่จำกัด</strong>
                    </a>
                    <a href="?profile=true_pro_facebook" class="profile-option glass-card <?php echo $profileKey === 'true_pro_facebook' ? 'active' : ''; ?>">
                        <strong class="block text-white"><i class="fab fa-facebook mr-2"></i>True Pro Facebook</strong>
                    </a>
                </div>
            </div>
            
            <div class="text-center mb-6">
                <h3 class="text-lg font-semibold text-white"><i class="fas fa-chart-bar mr-2"></i>สถิติผู้ใช้งานทั้งหมด</h3>
            </div>
            
            <div class="online-counter">
                <?php echo htmlspecialchars($totalOnlineAll); ?> <span class="text-lg text-gray-300">/ 120</span> <!-- 30 per package × 4 packages -->
            </div>
            
            <div class="capacity-bar-container">
                <?php 
                    // Calculate based on 4 packages with 30 slots each (120 total)
                    $percentage = min(($totalOnlineAll / 120) * 100, 100); 
                    $barColor = $percentage < 60 ? 'bg-green-500' : ($percentage < 80 ? 'bg-yellow-500' : 'bg-red-500');
                ?>
                <div class="capacity-bar <?php echo $barColor; ?>" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            
            <div class="text-center text-gray-300 mb-6">
                <?php if ($percentage < 60): ?>
                    <p><i class="fas fa-check-circle text-green-500 mr-2"></i> มีที่ว่างเพียงพอ สามารถสร้างโค้ดเพิ่มได้</p>
                <?php elseif ($percentage < 80): ?>
                    <p><i class="fas fa-exclamation-circle text-yellow-500 mr-2"></i> มีผู้ใช้งานค่อนข้างมาก อาจมีผลต่อความเร็ว</p>
                <?php else: ?>
                    <p><i class="fas fa-times-circle text-red-500 mr-2"></i> มีผู้ใช้งานใกล้เต็ม ไม่ควรสร้างโค้ดเพิ่ม</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($allOnlineClients)): ?>
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-white mb-4"><i class="fas fa-list mr-2"></i>รายการผู้ใช้งานออนไลน์แยกตามแพ็กเกจ</h3>
                    
                    <?php foreach($allOnlineClients as $profileKey => $profileData): ?>
                        
                    <div class="mb-8">
                        <div class="text-center mb-3">
                            <h3 class="text-lg font-semibold text-white"><i class="fas fa-chart-bar mr-2"></i>แพ็กเกจ: <?php echo htmlspecialchars($profileData['profileName']); ?></h3>
                            <div class="text-xl text-center text-cyan-300 mt-2"><?php echo htmlspecialchars($profileData['count']); ?> <span class="text-sm text-gray-300">/ 30</span></div>
                            
                            <div class="capacity-bar-container w-1/2 mx-auto mt-3">
                                <?php 
                                    $profilePercentage = min(($profileData['count'] / 30) * 100, 100); 
                                    $profileBarColor = $profilePercentage < 60 ? 'bg-green-500' : ($profilePercentage < 80 ? 'bg-yellow-500' : 'bg-red-500');
                                ?>
                                <div class="capacity-bar <?php echo $profileBarColor; ?>" style="width: <?php echo $profilePercentage; ?>%"></div>
                            </div>
                        </div>
                    
                    <?php if (!empty($profileData['clients'])): ?>
                        <?php
                        // Separate known and unknown clients
                        $knownClients = array_filter($profileData['clients'], function($client) {
                            return $client['name'] !== 'ไม่ทราบชื่อ';
                        });
                        
                        $unknownClients = array_filter($profileData['clients'], function($client) {
                            return $client['name'] === 'ไม่ทราบชื่อ';
                        });
                        ?>
                    
                    <?php if (!empty($knownClients)): ?>
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-300 mb-3"><i class="fas fa-user-check mr-2"></i>ผู้ใช้งานที่ทราบชื่อ (<?php echo count($knownClients); ?>)</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                <?php foreach($knownClients as $index => $client): ?>
                                    <div class="glass-card p-3 rounded-lg text-gray-200 flex items-center hover:border-cyan-400 hover:border-opacity-50 border border-transparent transition-all duration-300" title="ID: <?php echo htmlspecialchars($client['uuid']); ?> | ชื่อ: <?php echo htmlspecialchars($client['name']); ?>">
                                        <i class="fas fa-user-circle mr-2 text-cyan-400"></i>
                                        <span class="truncate"><?php echo htmlspecialchars($client['name']); ?></span>
                                        <span class="ml-auto bg-green-500 bg-opacity-20 px-2 py-1 rounded text-xs flex items-center">
                                            <i class="fas fa-circle text-green-500 text-xs animate-pulse mr-1"></i>ใช้งาน
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($unknownClients)): ?>
                        <div>
                            <h4 class="text-md font-semibold text-gray-300 mb-3"><i class="fas fa-user-question mr-2"></i>ผู้ใช้งานที่ไม่ทราบชื่อ (<?php echo count($unknownClients); ?>)</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                <?php foreach($unknownClients as $index => $client): ?>
                                    <div class="glass-card p-3 rounded-lg text-gray-300 flex items-center" title="UUID: <?php echo htmlspecialchars($client['uuid']); ?>">
                                        <i class="fas fa-question-circle text-yellow-400 mr-2"></i>
                                        <span class="truncate text-yellow-200">ไม่ทราบชื่อ</span>
                                        <span class="ml-auto bg-green-500 bg-opacity-20 px-2 py-1 rounded text-xs flex items-center">
                                            <i class="fas fa-circle text-green-500 text-xs animate-pulse mr-1"></i>ใช้งาน
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                        <div class="text-center">
                            <i class="fas fa-users-slash text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-300 mb-3">ไม่มีผู้ใช้งานออนไลน์ในขณะนี้</p>
                        </div>
                    <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mt-8 text-center">
                    <i class="fas fa-users-slash text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-300 text-lg mb-4">ไม่มีข้อมูลผู้ใช้งานออนไลน์จากเซิร์ฟเวอร์</p>
                </div>
            <?php endif; ?>
            
            <div class="flex flex-col sm:flex-row gap-4 mt-8 justify-center">
                <a href="save_vless.php" class="btn-primary w-full sm:w-auto bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 px-6 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                    <i class="fas fa-plus-circle mr-2"></i>สร้างโค้ด VPN ใหม่
                </a>
                <a href="vpn_history.php" class="btn-secondary w-full sm:w-auto bg-gray-600 bg-opacity-20 text-gray-200 py-3 px-6 rounded-lg hover:bg-opacity-30 transition duration-200 flex items-center justify-center">
                    <i class="fas fa-history mr-2"></i>ประวัติโค้ด VPN
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
            
            // Auto-refresh page every 30 seconds
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        });
    </script>
</body>
</html>
