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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สมัครสมาชิก VIP VPN</title>
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

        /* Fade-in animation for messages */
        .error-message, .success-message {
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

        /* Checkbox styling */
        .checkbox-group input[type="checkbox"] {
            accent-color: #22d3ee;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen relative">
    <div id="particles-js"></div>
    <div class="glass-card rounded-2xl p-8 w-full max-w-md mx-4 z-10">
        <div class="flex flex-col items-center mb-6">
            <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="logo h-20 mb-4">
            <h1 class="text-3xl font-bold text-white">VIP VPN</h1>
            <p class="text-sm text-gray-300">สมัครสมาชิกเพื่อเริ่มใช้งาน</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message bg-red-500 bg-opacity-30 text-red-200 p-4 rounded-lg mb-4 flex items-center border border-red-400">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message bg-green-500 bg-opacity-30 text-green-200 p-4 rounded-lg mb-4 flex items-center border border-green-400">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-200">
                    <i class="fas fa-user mr-2"></i>ชื่อผู้ใช้
                </label>
                <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required class="mt-1 block w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-200">
                    <i class="fas fa-envelope mr-2"></i>อีเมล
                </label>
                <input type="email" id="email" name="email" placeholder="กรอกอีเมล" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required class="mt-1 block w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
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
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-200">
                    <i class="fas fa-lock mr-2"></i>ยืนยันรหัสผ่าน
                </label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="กรอกรหัสผ่านอีกครั้ง" required class="mt-1 block w-full bg-white bg-opacity-10 border border-gray-300 rounded-lg p-3 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-200">
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('confirm_password')">
                        <i id="confirm_password_toggle_icon" class="fas fa-eye text-gray-400"></i>
                    </button>
                </div>
            </div>
            
            <div class="checkbox-group flex items-center space-x-2">
                <input type="checkbox" id="terms" name="terms" required class="h-4 w-4">
                <label for="terms" class="text-sm text-gray-200">
                    ฉันยอมรับ <a href="#" class="text-cyan-300 hover:text-cyan-400 hover:underline">เงื่อนไขการใช้งาน</a> และ <a href="#" class="text-cyan-300 hover:text-cyan-400 hover:underline">นโยบายความเป็นส่วนตัว</a>
                </label>
            </div>
            
            <button type="submit" class="btn-primary w-full bg-gradient-to-r from-cyan-500 to-purple-600 text-white py-3 rounded-lg hover:from-cyan-600 hover:to-purple-700 transition duration-200 flex items-center justify-center">
                <i class="fas fa-user-plus mr-2"></i>สมัครสมาชิก
            </button>
        </form>
        
        <div class="text-center mt-6">
            <p class="text-sm text-gray-300">
                มีบัญชีอยู่แล้ว? <a href="login.php" class="text-cyan-300 hover:text-cyan-400 hover:underline transition duration-200">เข้าสู่ระบบ</a>
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