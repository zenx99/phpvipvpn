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
        
        if ($login_id === $admin_username && $password === $admin_password) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เข้าสู่ระบบ VIP VPN</title>
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
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        /* Fade-in animation for error message */
        .error-message {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive logo size */
        @media (max-width: 640px) {
            .logo {
                height: 3.5rem;
            }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen relative">
    <div id="particles-js"></div>
    <div class="glass-card rounded-2xl p-8 w-full max-w-md mx-4 z-10">
        <div class="flex flex-col items-center mb-6">
            <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="logo h-20 mb-4">
            <h1 class="text-3xl font-bold text-white">VIP VPN</h1>
            <p class="text-sm text-gray-300">เข้าสู่ระบบเพื่อใช้งาน</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message bg-red-500 bg-opacity-30 text-red-200 p-4 rounded-lg mb-4 flex items-center border border-red-400">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
            <div>
                <label for="login_id" class="block text-sm font-medium text-gray-200">
                    <i class="fas fa-user mr-2"></i>ชื่อผู้ใช้หรืออีเมล
                </label>
                <input type="text" id="login_id" name="login_id" placeholder="กรอกชื่อผู้ใช้หรืออีเมล" value="<?php echo isset($_POST['login_id']) ? htmlspecialchars($_POST['login_id']) : ''; ?>" required class="mt-1 block w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-200">
                    <i class="fas fa-lock mr-2"></i>รหัสผ่าน
                </label>
                <div class="relative">
                    <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required class="mt-1 block w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('password')">
                        <i id="password_toggle_icon" class="fas fa-eye text-gray-400"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
            </button>
        </form>

        <div class="text-center mt-6 space-y-2">
            <p class="text-sm text-gray-300">
                ยังไม่มีบัญชี? <a href="register.php" class="text-cyan-300 hover:text-cyan-400 hover:underline transition duration-200">สมัครสมาชิก</a>
            </p>
            <p class="text-sm text-gray-300">
                <a href="reset_password.php" class="text-cyan-300 hover:text-cyan-400 hover:underline transition duration-200">ลืมรหัสผ่าน?</a>
            </p>
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

        // Password toggle
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const icon = document.getElementById(`${inputId}_toggle_icon`);
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
    </script>
</body>
</html>