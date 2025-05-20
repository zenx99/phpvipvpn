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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าบัญชี - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .tab {
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            background: #f5f7ff;
            color: var(--dark-color);
        }
        
        .tab.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 5px 15px rgba(71, 118, 230, 0.2);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--dark-color);
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: var(--primary-color);
        }
        
        .back-link i {
            margin-right: 8px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            cursor: pointer;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .card-header i {
            font-size: 24px;
            background: var(--primary-gradient);
            color: white;
            padding: 12px;
            border-radius: 12px;
            margin-right: 15px;
        }
        
        .card-header h2 {
            margin: 0;
            text-align: left;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .warning-box {
            background-color: rgba(255, 187, 0, 0.1);
            border-left: 4px solid #ffbb00;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
        }
        
        .warning-box i {
            font-size: 24px;
            color: #ffbb00;
            margin-right: 15px;
            margin-top: 3px;
        }
        
        .warning-box h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #e65100;
        }
        
        .warning-box p {
            margin: 0;
            color: #555;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff5252, #d32f2f);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
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
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            transform: translateY(-20px);
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header i {
            font-size: 24px;
            color: #d32f2f;
            margin-right: 15px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-cancel {
            background: #f5f5f5;
            color: #333;
        }
        
        .btn-cancel:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body style="display: block; padding-top: 80px; background: #f9fafc;">
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
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> ตั้งค่าบัญชี
                </a>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
    
    <div class="settings-container">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> กลับไปยังแดชบอร์ด
        </a>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="profile">
                <i class="fas fa-user"></i> ข้อมูลบัญชี
            </div>
            <div class="tab" data-tab="password">
                <i class="fas fa-lock"></i> เปลี่ยนรหัสผ่าน
            </div>
            <div class="tab" data-tab="delete">
                <i class="fas fa-user-times"></i> ลบบัญชี
            </div>
        </div>
        
        <div class="tab-content active" id="profile-tab">
            <div class="card-header">
                <i class="fas fa-user-edit"></i>
                <h2>แก้ไขข้อมูลบัญชี</h2>
            </div>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> ชื่อผู้ใช้</label>
                        <input type="text" id="username" name="username" placeholder="ชื่อผู้ใช้" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> อีเมล</label>
                    <input type="email" id="email" name="email" placeholder="อีเมล" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <button type="submit" name="update_profile" class="btn">
                    <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                </button>
            </form>
        </div>
        
        <div class="tab-content" id="password-tab">
            <div class="card-header">
                <i class="fas fa-key"></i>
                <h2>เปลี่ยนรหัสผ่าน</h2>
            </div>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label for="current_password"><i class="fas fa-lock"></i> รหัสผ่านปัจจุบัน</label>
                    <input type="password" id="current_password" name="current_password" placeholder="รหัสผ่านปัจจุบัน" required>
                    <span class="password-toggle" onclick="togglePassword('current_password')">
                        <i id="current_password-toggle-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                
                <div class="form-group">
                    <label for="new_password"><i class="fas fa-lock"></i> รหัสผ่านใหม่</label>
                    <input type="password" id="new_password" name="new_password" placeholder="รหัสผ่านใหม่" required>
                    <span class="password-toggle" onclick="togglePassword('new_password')">
                        <i id="new_password-toggle-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required>
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i id="confirm_password-toggle-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                
                <button type="submit" name="change_password" class="btn">
                    <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </div>
        
        <div class="tab-content" id="delete-tab">
            <div class="card-header">
                <i class="fas fa-user-times"></i>
                <h2>ลบบัญชี</h2>
            </div>
            
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h3>คำเตือน! การดำเนินการนี้ไม่สามารถกู้คืนได้</h3>
                    <p>การลบบัญชีของคุณจะเป็นการลบข้อมูลทั้งหมดอย่างถาวร รวมถึงประวัติการใช้งาน เครดิต และข้อมูลส่วนตัว กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ</p>
                </div>
            </div>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label for="delete_password"><i class="fas fa-lock"></i> รหัสผ่านปัจจุบัน (เพื่อยืนยันตัวตน)</label>
                    <input type="password" id="delete_password" name="delete_password" placeholder="กรุณาป้อนรหัสผ่านปัจจุบันเพื่อยืนยันการลบบัญชี" required>
                    <span class="password-toggle" onclick="togglePassword('delete_password')">
                        <i id="delete_password-toggle-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="confirm_delete" name="confirm_delete" required>
                    <label for="confirm_delete">ฉันเข้าใจว่าการกระทำนี้จะลบบัญชีของฉันอย่างถาวรและไม่สามารถกู้คืนได้</label>
                </div>
                
                <button type="button" id="delete_account_btn" class="btn btn-danger" onclick="showDeleteConfirmModal()">
                    <i class="fas fa-user-times"></i> ลบบัญชีของฉัน
                </button>
                
                <!-- Hidden submit button that will be triggered by the confirmation modal -->
                <button type="submit" id="confirm_delete_submit" name="delete_account" style="display: none;"></button>
            </form>
        </div>
    </div>
    
    <div style="text-align: center; padding: 20px; color: #777; margin-top: 20px;">
        &copy; 2025 VIP VPN Thailand. All rights reserved.
    </div>
    
    <!-- Delete Account Confirmation Modal -->
    <div class="modal-overlay" id="delete-confirm-modal">
        <div class="modal">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>ยืนยันการลบบัญชี</h3>
            </div>
            <div class="modal-body">
                <p>คุณแน่ใจหรือไม่ว่าต้องการลบบัญชีของคุณ?</p>
                <p><b>คำเตือน:</b> การดำเนินการนี้ไม่สามารถเปลี่ยนแปลงได้ และข้อมูลทั้งหมดของคุณจะถูกลบถาวร</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="hideDeleteConfirmModal()">ยกเลิก</button>
                <button class="btn btn-danger" onclick="confirmDelete()">ลบบัญชีถาวร</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding tab content
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === tabName + '-tab') {
                            content.classList.add('active');
                        }
                    });
                });
            });
            
            // User menu functionality
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    this.classList.toggle('active');
                });
                
                // Close menu when clicking elsewhere
                document.addEventListener('click', function() {
                    userMenu.classList.remove('active');
                });
            }
            
            // Check if there's a url parameter for tab and activate that tab
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            
            if (activeTab) {
                const tabToActivate = document.querySelector(`.tab[data-tab="${activeTab}"]`);
                if (tabToActivate) {
                    tabToActivate.click();
                }
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