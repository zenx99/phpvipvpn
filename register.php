<?php
session_start();

// SQLite database file path
$db_file = __DIR__ . '/vipvpn.db';

// Initialize variables
$error_message = '';
$success_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif ($password !== $confirm_password) {
        $error_message = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error_message = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } elseif (!isset($_POST['terms'])) {
        $error_message = 'กรุณายอมรับเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว';
    } else {
        try {
            // Connect to database
            $db = new SQLite3($db_file);
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray();
            
            if ($row['count'] > 0) {
                $error_message = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
                $result = $stmt->execute();
                
                if ($result) {
                    $success_message = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ';
                } else {
                    $error_message = 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองอีกครั้ง';
                }
            }
            
            // Close connection
            $db->close();
        } catch (Exception $e) {
            $error_message = 'ขออภัย เกิดข้อผิดพลาดในการเชื่อมต่อกับฐานข้อมูล';
            // For debugging: $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="logo">
        <h1>สมัครสมาชิก</h1>
        
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
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> อีเมล</label>
                <input type="email" id="email" name="email" placeholder="กรอกอีเมล" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> รหัสผ่าน</label>
                <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> ยืนยันรหัสผ่าน</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">ฉันยอมรับ <a href="#">เงื่อนไขการใช้งาน</a> และ <a href="#">นโยบายความเป็นส่วนตัว</a></label>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> สมัครสมาชิก
            </button>
        </form>
        
        <p class="text-center">มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
    </div>
</body>
</html>
