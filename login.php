<?php
session_start();

// SQLite database file path
$db_file = __DIR__ . '/vipvpn.db';

// Initialize error message variable
$error_message = '';

// Create SQLite database and tables if they don't exist
try {
    $db = new SQLite3($db_file);
    
    // Create users table if it doesn't exist
    $db->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE,
            password TEXT NOT NULL,
            credits DECIMAL(10,2) DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $db->close();
} catch (Exception $e) {
    $error_message = 'ไม่สามารถสร้างฐานข้อมูลได้';
    // For debugging: $error_message = $e->getMessage();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get credentials from form
    $login_id = isset($_POST['login_id']) ? trim($_POST['login_id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Basic validation
    if (empty($login_id) || empty($password)) {
        $error_message = 'กรุณากรอกชื่อผู้ใช้หรืออีเมล และรหัสผ่าน';
    } else {
        // Check if admin login attempt
        $admin_username = 'admin';
        $admin_password = 'admin123';
        
        if ($username === $admin_username && $password === $admin_password) {
            // Admin login successful
            $_SESSION['admin'] = true;
            $_SESSION['admin_username'] = $admin_username;
            
            // Redirect to admin dashboard
            header('Location: admin_dashboard.php');
            exit;
        }
        
        // If not admin, try regular user login
        try {
            $db = new SQLite3($db_file);
            
            // Get user from database using username or email
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :login_id OR email = :login_id LIMIT 1");
            $stmt->bindValue(':login_id', $login_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            // Check if user exists
            if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                // Verify password (using password_hash in your user creation)
                if (password_verify($password, $row['password'])) {
                    // Success - store user data in session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['credits'] = $row['credits'];
                    
                    // Redirect to dashboard or home page
                    header('Location: index.php');
                    exit;
                } else {
                    $error_message = 'รหัสผ่านไม่ถูกต้อง';
                }
            } else {
                $error_message = 'ไม่พบชื่อผู้ใช้หรืออีเมลนี้ในระบบ';
            }
        } catch(Exception $e) {
            $error_message = 'ขออภัย เกิดข้อผิดพลาดในการเชื่อมต่อกับฐานข้อมูล';
            // For debugging: $error_message = $e->getMessage();
        }
        
        // Close connection
        $db->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="logo">
        <h1>เข้าสู่ระบบ</h1>

        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="login_id"><i class="fas fa-user"></i> ชื่อผู้ใช้หรืออีเมล</label>
                <input type="text" id="login_id" name="login_id" placeholder="กรอกชื่อผู้ใช้หรืออีเมล" value="<?php echo isset($_POST['login_id']) ? htmlspecialchars($_POST['login_id']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> รหัสผ่าน</label>
                <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </button>
        </form>

        <p class="text-center">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
        <p class="text-center"><a href="reset_password.php">ลืมรหัสผ่าน?</a></p>
    </div>
</body>
</html>
