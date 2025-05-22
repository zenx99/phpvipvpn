<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit;
}

// SQLite database file path
$db_file = __DIR__ . '/vipvpn.db';

// Initialize variables
$success_message = '';
$error_message = '';
$username = $_SESSION['username'];
$email = '';
$user_id = $_SESSION['user_id'];

// Get user info from database
try {
    $db = new SQLite3($db_file);
    $stmt = $db->prepare('SELECT username, email, credits FROM users WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $username = $row['username'];
        $email = $row['email'] ?? ''; // Handle NULL email
        $credits = $row['credits'];
    }
    
    $db->close();
} catch (Exception $e) {
    $error_message = 'ไม่สามารถดึงข้อมูลผู้ใช้ได้';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Update profile
        $new_username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $new_email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        // Validate inputs
        if (empty($new_username) || empty($new_email)) {
            $error_message = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'รูปแบบอีเมลไม่ถูกต้อง';
        } else {
            try {
                $db = new SQLite3($db_file);
                
                // Check if new username or email already exists for other users
                $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE (username = :username OR email = :email) AND id != :id");
                $check_stmt->bindValue(':username', $new_username, SQLITE3_TEXT);
                $check_stmt->bindValue(':email', $new_email, SQLITE3_TEXT);
                $check_stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                $check_result = $check_stmt->execute();
                $check_row = $check_result->fetchArray();
                
                if ($check_row['count'] > 0) {
                    $error_message = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
                } else {
                    // Update user profile
                    $update_stmt = $db->prepare("UPDATE users SET username = :username, email = :email WHERE id = :id");
                    $update_stmt->bindValue(':username', $new_username, SQLITE3_TEXT);
                    $update_stmt->bindValue(':email', $new_email, SQLITE3_TEXT);
                    $update_stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                    $update_result = $update_stmt->execute();
                    
                    if ($update_result) {
                        $success_message = 'อัปเดตข้อมูลผู้ใช้สำเร็จ';
                        $_SESSION['username'] = $new_username; // Update session
                        $username = $new_username;
                        $email = $new_email;
                    } else {
                        $error_message = 'ไม่สามารถอัปเดตข้อมูลได้ กรุณาลองอีกครั้ง';
                    }
                }
                
                $db->close();
            } catch (Exception $e) {
                $error_message = 'ขออภัย เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'รหัสผ่านใหม่และยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } else {
            try {
                $db = new SQLite3($db_file);
                
                // Get current hashed password
                $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                
                if ($row && password_verify($current_password, $row['password'])) {
                    // Current password is correct, update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $update_stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
                    $update_stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
                    $update_stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                    $update_result = $update_stmt->execute();
                    
                    if ($update_result) {
                        $success_message = 'เปลี่ยนรหัสผ่านสำเร็จ';
                    } else {
                        $error_message = 'ไม่สามารถเปลี่ยนรหัสผ่านได้ กรุณาลองอีกครั้ง';
                    }
                } else {
                    $error_message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
                
                $db->close();
            } catch (Exception $e) {
                $error_message = 'ขออภัย เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
            }
        }
    } elseif (isset($_POST['delete_account'])) {
        // Delete account
        $delete_password = isset($_POST['delete_password']) ? $_POST['delete_password'] : '';
        $confirm_delete = isset($_POST['confirm_delete']) ? true : false;
        
        // Validate inputs
        if (empty($delete_password) || !$confirm_delete) {
            $error_message = 'กรุณายืนยันการลบบัญชีและป้อนรหัสผ่าน';
        } else {
            try {
                $db = new SQLite3($db_file);
                
                // Verify password first
                $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                
                if ($row && password_verify($delete_password, $row['password'])) {
                    // Password is correct, delete account
                    $delete_stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                    $delete_stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                    $delete_result = $delete_stmt->execute();
                    
                    if ($delete_result) {
                        // Clear session and redirect to login page with message
                        session_unset();
                        session_destroy();
                        
                        // Set a session variable to communicate the deletion message
                        session_start();
                        $_SESSION['account_deleted'] = true;
                        
                        header('Location: login.php?deleted=1');
                        exit;
                    } else {
                        $error_message = 'ไม่สามารถลบบัญชีได้ กรุณาลองอีกครั้ง';
                    }
                } else {
                    $error_message = 'รหัสผ่านไม่ถูกต้อง';
                }
                
                $db->close();
            } catch (Exception $e) {
                $error_message = 'ขออภัย เกิดข้อผิดพลาดในการลบบัญชี';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ตั้งค่าบัญชี - VIP VPN</title>
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

        /* Input focus animation */
        input:focus {
            transform: scale(1.02);
            transition: transform 0.2s ease-in-out;
        }

        /* Button hover animation */
        button:hover, .btn-primary:hover, .btn-danger:hover, .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        /* Fade-in animation for messages and sections */
        .error-message, .success-message, .section {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Modal styling */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            transform: translateY(-20px);
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .logo {
                height: 2.5rem;
            }
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            .modal-footer {
                flex-direction: column;
                gap: 10px;
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
                        <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($username); ?>
                    </div>
                    <div class="px-4 py-2 text-sm text-gray-200">
                        <i class="fas fa-coins mr-2"></i>เครดิต: <span class="font-semibold"><?php echo htmlspecialchars($credits); ?></span>
                    </div>
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
            <a href="index.php" class="inline-flex items-center text-gray-200 hover:text-cyan-300 mb-6 transition duration-200">
                <i class="fas fa-arrow-left mr-2"></i>กลับไปยังแดชบอร์ด
            </a>

            <?php if (!empty($error_message)): ?>
                <div class="error-message bg-red-500 bg-opacity-30 text-red-200 p-4 rounded-lg mb-6 flex items-center border border-red-400">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message bg-green-500 bg-opacity-30 text-green-200 p-4 rounded-lg mb-6 flex items-center border border-green-400">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Edit Profile Section -->
            <div class="section mb-12">
                <div class="flex items-center mb-6">
                    <i class="fas fa-user-edit text-2xl text-white bg-gradient-to-r from-cyan-500 to-purple-600 p-3 rounded-lg mr-3"></i>
                    <h2 class="text-xl font-bold text-white">แก้ไขข้อมูลบัญชี</h2>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
                    <div class="form-row flex flex-wrap gap-4">
                        <div class="form-group flex-1">
                            <label for="username" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-user mr-2"></i>ชื่อผู้ใช้</label>
                            <input type="text" id="username" name="username" placeholder="ชื่อผู้ใช้" value="<?php echo htmlspecialchars($username); ?>" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-envelope mr-2"></i>อีเมล</label>
                        <input type="email" id="email" name="email" placeholder="อีเมล" value="<?php echo htmlspecialchars($email); ?>" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary w-full bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i>บันทึกการเปลี่ยนแปลง
                    </button>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="section mb-12">
                <div class="flex items-center mb-6">
                    <i class="fas fa-key text-2xl text-white bg-gradient-to-r from-cyan-500 to-purple-600 p-3 rounded-lg mr-3"></i>
                    <h2 class="text-xl font-bold text-white">เปลี่ยนรหัสผ่าน</h2>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
                    <div class="form-group relative">
                        <label for="current_password" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-lock mr-2"></i>รหัสผ่านปัจจุบัน</label>
                        <input type="password" id="current_password" name="current_password" placeholder="รหัสผ่านปัจจุบัน" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                        <span class="password-toggle absolute right-3 top-12 cursor-pointer text-gray-400" onclick="togglePassword('current_password')">
                            <i id="current_password-toggle-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="form-group relative">
                        <label for="new_password" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-lock mr-2"></i>รหัสผ่านใหม่</label>
                        <input type="password" id="new_password" name="new_password" placeholder="รหัสผ่านใหม่" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                        <span class="password-toggle absolute right-3 top-12 cursor-pointer text-gray-400" onclick="togglePassword('new_password')">
                            <i id="new_password-toggle-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="form-group relative">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-lock mr-2"></i>ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                        <span class="password-toggle absolute right-3 top-12 cursor-pointer text-gray-400" onclick="togglePassword('confirm_password')">
                            <i id="confirm_password-toggle-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                    <button type="submit" name="change_password" class="btn-primary w-full bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                        <i class="fas fa-key mr-2"></i>เปลี่ยนรหัสผ่าน
                    </button>
                </form>
            </div>

            <!-- Delete Account Section -->
            <div class="section">
                <div class="flex items-center mb-6">
                    <i class="fas fa-user-times text-2xl text-white bg-gradient-to-r from-red-500 to-red-700 p-3 rounded-lg mr-3"></i>
                    <h2 class="text-xl font-bold text-white">ลบบัญชี</h2>
                </div>
                <div class="bg-yellow-500 bg-opacity-20 border-l-4 border-yellow-500 p-4 rounded-lg mb-6 flex items-start">
                    <i class="fas fa-exclamation-triangle text-2xl text-yellow-300 mr-3"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-yellow-300 mb-2">คำเตือน! การดำเนินการนี้ไม่สามารถกู้คืนได้</h3>
                        <p class="text-gray-300">การลบบัญชีของคุณจะเป็นการลบข้อมูลทั้งหมดอย่างถาวร รวมถึงประวัติการใช้งาน เครดิต และข้อมูลส่วนตัว กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ</p>
                    </div>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
                    <div class="form-group relative">
                        <label for="delete_password" class="block text-sm font-medium text-gray-200 mb-2"><i class="fas fa-lock mr-2"></i>รหัสผ่านปัจจุบัน (เพื่อยืนยันตัวตน)</label>
                        <input type="password" id="delete_password" name="delete_password" placeholder="กรุณาป้อนรหัสผ่านปัจจุบันเพื่อยืนยันการลบบัญชี" required class="w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-red-400 focus:border-red-400 transition duration-200">
                        <span class="password-toggle absolute right-3 top-12 cursor-pointer text-gray-400" onclick="togglePassword('delete_password')">
                            <i id="delete_password-toggle-icon" class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="checkbox-group flex items-center text-gray-200">
                        <input type="checkbox" id="confirm_delete" name="confirm_delete" required class="w-5 h-5 text-cyan-400 border-gray-300 rounded focus:ring-cyan-400">
                        <label for="confirm_delete" class="ml-2">ฉันเข้าใจว่าการกระทำนี้จะลบบัญชีของฉันอย่างถาวรและไม่สามารถกู้คืนได้</label>
                    </div>
                    <button type="button" id="delete_account_btn" class="btn-danger w-full bg-gradient-to-r from-red-500 to-red-700 text-white py-3 rounded-lg hover:from-red-600 hover:to-red-800 transition duration-200 flex items-center justify-center" onclick="showDeleteConfirmModal()">
                        <i class="fas fa-user-times mr-2"></i>ลบบัญชีของฉัน
                    </button>
                    <button type="submit" id="confirm_delete_submit" name="delete_account" style="display: none;"></button>
                </form>
            </div>
        </div>
        <div class="text-center text-gray-400 mt-6">© 2025 VIP VPN Thailand. All rights reserved.</div>
    </main>

    <div class="modal-overlay" id="delete-confirm-modal">
        <div class="modal">
            <div class="modal-header flex items-center mb-4">
                <i class="fas fa-exclamation-triangle text-2xl text-red-300 mr-3"></i>
                <h3 class="text-lg font-semibold text-white">ยืนยันการลบบัญชี</h3>
            </div>
            <div class="modal-body mb-6 text-gray-200">
                <p>คุณแน่ใจหรือไม่ว่าต้องการลบบัญชีของคุณ?</p>
                <p><b>คำเตือน:</b> การดำเนินการนี้ไม่สามารถเปลี่ยนแปลงได้ และข้อมูลทั้งหมดของคุณจะถูกลบถาวร</p>
            </div>
            <div class="modal-footer flex justify-end gap-4">
                <button class="btn-cancel bg-gray-600 bg-opacity-20 text-gray-200 px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200" onclick="hideDeleteConfirmModal()">ยกเลิก</button>
                <button class="btn-danger bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-red-800 transition duration-200" onclick="confirmDelete()">ลบบัญชีถาวร</button>
            </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            // User menu functionality
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

            // Auto-hide messages
            const messageDiv = document.querySelector('.message');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.5s';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => messageDiv.remove(), 500);
                }, 5000);
            }
        });
        
        // Password toggle functionality
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const iconId = inputId + '-toggle-icon';
            const icon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Delete account confirmation functionality
        function showDeleteConfirmModal() {
            const confirmCheckbox = document.getElementById('confirm_delete');
            const deletePassword = document.getElementById('delete_password');
            
            if (!confirmCheckbox.checked) {
                alert('กรุณายืนยันว่าคุณเข้าใจผลกระทบจากการลบบัญชี');
                return;
            }
            
            if (!deletePassword.value) {
                alert('กรุณาป้อนรหัสผ่านเพื่อยืนยันการลบบัญชี');
                return;
            }
            
            document.getElementById('delete-confirm-modal').classList.add('active');
        }
        
        function hideDeleteConfirmModal() {
            document.getElementById('delete-confirm-modal').classList.remove('active');
        }
        
        function confirmDelete() {
            document.getElementById('confirm_delete_submit').click();
        }
    </script>
</body>
</html>