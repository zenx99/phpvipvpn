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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
            min-height: 100vh;
        }
        
        /* Modern glass card effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        /* Modern input styling */
        .input-field {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .input-field:focus {
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
            background: rgba(255, 255, 255, 0.15);
        }

        /* Modern button styling */
        .btn-primary {
            background: linear-gradient(135deg, #06b6d4, #3b82f6);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(6, 182, 212, 0.3);
        }

        /* Logo styling */
        .logo {
            filter: drop-shadow(0 0 10px rgba(6, 182, 212, 0.5));
        }

        /* Error message animation */
        .error-message {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Loading state */
        .loading {
            opacity: 0.8;
            pointer-events: none;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile responsive */
        @media (max-width: 640px) {
            .glass-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen relative">
    <div id="particles-js"></div>
    <div class="glass-card rounded-2xl p-8 w-full max-w-md mx-4 z-10">
        <div class="flex flex-col items-center mb-8">
            <div class="relative">
                <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="logo h-20 mb-4">
                <div class="absolute -inset-1 bg-gradient-to-r from-cyan-500 to-purple-600 rounded-full blur opacity-25"></div>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">
                VIP <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-purple-400">VPN</span>
            </h1>
            <p class="text-sm text-gray-300 text-center">🚀 เข้าสู่ระบบเพื่อใช้งาน</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message bg-gradient-to-r from-red-500/20 to-pink-500/20 text-red-200 p-4 rounded-xl mb-6 flex items-center border border-red-400/30 backdrop-blur-sm">
                <i class="fas fa-exclamation-triangle mr-3 text-red-400"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6" id="loginForm">
            <div class="input-group">
                <label for="login_id" class="block text-sm font-medium text-gray-200 mb-2">
                    <i class="fas fa-user-astronaut mr-2 text-cyan-400"></i>ชื่อผู้ใช้หรืออีเมล
                </label>
                <div class="relative">
                    <input type="text" id="login_id" name="login_id" 
                           placeholder="🌟 กรอกชื่อผู้ใช้หรืออีเมล" 
                           value="<?php echo isset($_POST['login_id']) ? htmlspecialchars($_POST['login_id']) : ''; ?>" 
                           required 
                           class="input-field mt-1 block w-full rounded-xl p-4 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-300">
                    <div class="input-glow"></div>
                </div>
            </div>
            
            <div class="input-group">
                <label for="password" class="block text-sm font-medium text-gray-200 mb-2">
                    <i class="fas fa-shield-halved mr-2 text-purple-400"></i>รหัสผ่าน
                </label>
                <div class="relative">
                    <input type="password" id="password" name="password" 
                           placeholder="🔐 กรอกรหัสผ่าน" 
                           required 
                           class="input-field mt-1 block w-full rounded-xl p-4 pr-12 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-300">
                    <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center hover:scale-110 transition-transform duration-200" onclick="togglePassword('password')">
                        <i id="password_toggle_icon" class="fas fa-eye text-gray-400 hover:text-cyan-400"></i>
                    </button>
                    <div class="input-glow"></div>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full py-4 rounded-xl hover:from-cyan-600 hover:to-purple-700 transition duration-300 flex items-center justify-center text-lg font-semibold relative z-10">
                <i class="fas fa-rocket mr-3"></i>
                <span>เข้าสู่ระบบ</span>
            </button>
        </form>

        <div class="text-center mt-8 space-y-3">
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent my-6"></div>
            <p class="text-sm text-gray-300">
                ยังไม่มีบัญชี? 
                <a href="register.php" class="glow-link text-cyan-300 hover:text-cyan-400 hover:underline transition duration-200 font-medium">
                    <i class="fas fa-user-plus mr-1"></i>สมัครสมาชิก
                </a>
            </p>
            <p class="text-sm text-gray-300">
                <a href="reset_password.php" class="glow-link text-purple-300 hover:text-purple-400 hover:underline transition duration-200">
                    <i class="fas fa-key mr-1"></i>ลืมรหัสผ่าน?
                </a>
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

        // Form animation on submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.classList.add('loading');
            button.innerHTML = '<i class="fas fa-rocket mr-3"></i><span>กำลังเข้าสู่ระบบ...</span>';
        });

        // Add typing effect to placeholders
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.placeholder = '';
            function type() {
                if (i < text.length) {
                    element.placeholder += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        // Initialize typing effects
        window.addEventListener('load', function() {
            setTimeout(() => {
                typeWriter(document.getElementById('login_id'), '🌟 กรอกชื่อผู้ใช้หรืออีเมล', 50);
            }, 500);
            setTimeout(() => {
                typeWriter(document.getElementById('password'), '🔐 กรอกรหัสผ่าน', 50);
            }, 1500);
        });

        // Add matrix rain effect
        function createMatrixRain() {
            const canvas = document.createElement('canvas');
            canvas.style.position = 'fixed';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.zIndex = '-2';
            canvas.style.opacity = '0.1';
            document.body.appendChild(canvas);

            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()*&^%+-/~{[|`]}";
            const matrixArray = matrix.split("");
            const fontSize = 10;
            const columns = canvas.width / fontSize;
            const drops = [];

            for (let x = 0; x < columns; x++) {
                drops[x] = 1;
            }

            function draw() {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.04)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.fillStyle = '#0F4';
                ctx.font = fontSize + 'px arial';
                
                for (let i = 0; i < drops.length; i++) {
                    const text = matrixArray[Math.floor(Math.random() * matrixArray.length)];
                    ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                    
                    if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }
            }

            setInterval(draw, 35);
        }

        // Initialize matrix rain
        createMatrixRain();
    </script>
</body>
</html>