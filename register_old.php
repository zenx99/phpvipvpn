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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        /* Custom glassmorphism effect with enhanced blur */
        .glass-card {
            background: rgba(15, 23, 42, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.3);
            box-shadow: 
                0 20px 50px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Enhanced cyberpunk background */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: 
                radial-gradient(circle at 20% 80%, rgba(20, 184, 166, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                linear-gradient(135deg, #0f172a, #1e293b, #334155, #0f172a);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }

        #particles-js::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(1px 1px at 20px 30px, rgba(59, 130, 246, 0.3), transparent),
                radial-gradient(1px 1px at 40px 70px, rgba(20, 184, 166, 0.3), transparent),
                radial-gradient(1px 1px at 90px 40px, rgba(139, 92, 246, 0.3), transparent);
            background-size: 100px 80px;
            animation: matrix 20s linear infinite;
        }

        @keyframes matrix {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-100px, -80px); }
        }

        /* Enhanced gradient animation */
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 100% 100%; }
            75% { background-position: 50% 100%; }
            100% { background-position: 0% 50%; }
        }

        /* Enhanced input styling */
        .input-group {
            position: relative;
        }

        .input-field {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(148, 163, 184, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .input-field:focus {
            transform: translateY(-2px);
            border-color: rgba(20, 184, 166, 0.6);
            box-shadow: 
                0 10px 25px rgba(0, 0, 0, 0.3),
                0 0 30px rgba(20, 184, 166, 0.2);
            background: rgba(15, 23, 42, 0.5);
        }

        .input-field:focus + .input-glow {
            opacity: 1;
            transform: scale(1);
        }

        .input-glow {
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, #14b8a6, #3b82f6, #8b5cf6);
            border-radius: 0.75rem;
            opacity: 0;
            transform: scale(0.95);
            transition: all 0.3s ease;
            z-index: -1;
            filter: blur(8px);
        }

        /* Enhanced button styling */
        .btn-primary {
            background: linear-gradient(135deg, #14b8a6, #3b82f6, #8b5cf6);
            background-size: 200% 200%;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.4),
                0 0 50px rgba(20, 184, 166, 0.3);
            background-position: 100% 100%;
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        /* Enhanced logo animation */
        .logo {
            filter: drop-shadow(0 0 20px rgba(20, 184, 166, 0.5));
            animation: logoFloat 6s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        /* Enhanced fade-in animation */
        .error-message, .success-message {
            animation: slideInFromTop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes slideInFromTop {
            0% { 
                opacity: 0; 
                transform: translateY(-30px) scale(0.9); 
            }
            100% { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        /* Enhanced responsive design */
        @media (max-width: 640px) {
            .logo {
                height: 3.5rem;
            }
            .glass-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }

        /* Checkbox styling */
        .checkbox-group input[type="checkbox"] {
            accent-color: #14b8a6;
            transform: scale(1.2);
        }

        .checkbox-group label {
            cursor: pointer;
            user-select: none;
        }

        /* Glow effect for links */
        .glow-link:hover {
            text-shadow: 0 0 10px rgba(20, 184, 166, 0.6);
        }

        /* Loading animation for form submission */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading span {
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 20px;
            width: 16px;
            height: 16px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            <p class="text-sm text-gray-300 text-center">🌟 สมัครสมาชิกเพื่อเริ่มใช้งาน</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message bg-gradient-to-r from-red-500/20 to-pink-500/20 text-red-200 p-4 rounded-xl mb-6 flex items-center border border-red-400/30 backdrop-blur-sm">
                <i class="fas fa-exclamation-triangle mr-3 text-red-400"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message bg-gradient-to-r from-green-500/20 to-emerald-500/20 text-green-200 p-4 rounded-xl mb-6 flex items-center border border-green-400/30 backdrop-blur-sm">
                <i class="fas fa-check-circle mr-3 text-green-400"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-6" id="registerForm">
            <div class="input-group">
                <label for="username" class="block text-sm font-medium text-gray-200 mb-2">
                    <i class="fas fa-user-astronaut mr-2 text-cyan-400"></i>ชื่อผู้ใช้
                </label>
                <div class="relative">
                    <input type="text" id="username" name="username" 
                           placeholder="✨ กรอกชื่อผู้ใช้" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           required 
                           class="input-field mt-1 block w-full rounded-xl p-4 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-300">
                    <div class="input-glow"></div>
                </div>
            </div>
            
            <div class="input-group">
                <label for="email" class="block text-sm font-medium text-gray-200 mb-2">
                    <i class="fas fa-envelope mr-2 text-purple-400"></i>อีเมล
                </label>
                <div class="relative">
                    <input type="email" id="email" name="email" 
                           placeholder="📧 กรอกอีเมล" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required 
                           class="input-field mt-1 block w-full rounded-xl p-4 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-300">
                    <div class="input-glow"></div>
                </div>
            </div>
            
            <div class="input-group">
                <label for="password" class="block text-sm font-medium text-gray-200 mb-2">
                    <i class="fas fa-shield-halved mr-2 text-cyan-400"></i>รหัสผ่าน
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
                <!-- Password Strength Indicator -->
                <div class="mt-2">
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div id="password-strength" class="h-2 rounded-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <p id="strength-text" class="text-xs mt-1 text-gray-400"></p>
                </div>
            </div>
            
            <div class="input-group">
                <label for="confirm_password" class="block text-sm font-medium text-gray-200 mb-2">
                    <i class="fas fa-lock mr-2 text-purple-400"></i>ยืนยันรหัสผ่าน
                </label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="🔒 กรอกรหัสผ่านอีกครั้ง" 
                           required 
                           class="input-field mt-1 block w-full rounded-xl p-4 pr-12 text-white placeholder-gray-400 focus:ring-cyan-400 focus:border-cyan-400 transition duration-300">
                    <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center hover:scale-110 transition-transform duration-200" onclick="togglePassword('confirm_password')">
                        <i id="confirm_password_toggle_icon" class="fas fa-eye text-gray-400 hover:text-cyan-400"></i>
                    </button>
                    <div class="input-glow"></div>
                </div>
            </div>
            
            <div class="checkbox-group flex items-start space-x-3 p-4 bg-gradient-to-r from-cyan-500/10 to-purple-500/10 rounded-xl border border-cyan-400/20">
                <input type="checkbox" id="terms" name="terms" required class="mt-1 h-4 w-4">
                <label for="terms" class="text-sm text-gray-200 leading-relaxed">
                    ฉันยอมรับ 
                    <a href="#" class="glow-link text-cyan-300 hover:text-cyan-400 hover:underline font-medium">เงื่อนไขการใช้งาน</a> 
                    และ 
                    <a href="#" class="glow-link text-purple-300 hover:text-purple-400 hover:underline font-medium">นโยบายความเป็นส่วนตัว</a>
                </label>
            </div>
            
            <button type="submit" class="btn-primary w-full py-4 rounded-xl hover:from-cyan-600 hover:to-purple-700 transition duration-300 flex items-center justify-center text-lg font-semibold relative z-10">
                <i class="fas fa-rocket mr-3"></i>
                <span>สมัครสมาชิก</span>
            </button>
        </form>
        
        <div class="text-center mt-8">
            <div class="h-px bg-gradient-to-r from-transparent via-gray-600 to-transparent my-6"></div>
            <p class="text-sm text-gray-300">
                มีบัญชีอยู่แล้ว? 
                <a href="login.php" class="glow-link text-cyan-300 hover:text-cyan-400 hover:underline transition duration-200 font-medium">
                    <i class="fas fa-sign-in-alt mr-1"></i>เข้าสู่ระบบ
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
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.classList.add('loading');
            button.innerHTML = '<i class="fas fa-rocket mr-3"></i><span>กำลังสมัครสมาชิก...</span>';
        });

        // Add typing effect to placeholders
        function typeWriter(element, text, speed = 80) {
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
                typeWriter(document.getElementById('username'), '✨ กรอกชื่อผู้ใช้', 60);
            }, 300);
            setTimeout(() => {
                typeWriter(document.getElementById('email'), '📧 กรอกอีเมล', 60);
            }, 800);
            setTimeout(() => {
                typeWriter(document.getElementById('password'), '🔐 กรอกรหัสผ่าน', 60);
            }, 1300);
            setTimeout(() => {
                typeWriter(document.getElementById('confirm_password'), '🔒 กรอกรหัสผ่านอีกครั้ง', 60);
            }, 1800);
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthMeter = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e'];
            const texts = ['อ่อน', 'ปานกลาง', 'ดี', 'แข็งแกร่ง'];
            
            if (password.length > 0) {
                strengthMeter.style.width = (strength * 25) + '%';
                strengthMeter.style.backgroundColor = colors[strength - 1] || '#ef4444';
                strengthText.textContent = 'ความแข็งแกร่งของรหัสผ่าน: ' + (texts[strength - 1] || 'อ่อนมาก');
                strengthText.style.color = colors[strength - 1] || '#ef4444';
            } else {
                strengthMeter.style.width = '0%';
                strengthText.textContent = '';
            }
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
            canvas.style.opacity = '0.08';
            document.body.appendChild(canvas);

            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const matrix = "VIPVPNABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()";
            const matrixArray = matrix.split("");
            const fontSize = 12;
            const columns = canvas.width / fontSize;
            const drops = [];

            for (let x = 0; x < columns; x++) {
                drops[x] = 1;
            }

            function draw() {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.fillStyle = '#14b8a6';
                ctx.font = fontSize + 'px monospace';
                
                for (let i = 0; i < drops.length; i++) {
                    const text = matrixArray[Math.floor(Math.random() * matrixArray.length)];
                    ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                    
                    if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }
            }

            setInterval(draw, 40);
        }

        // Initialize matrix rain
        createMatrixRain();
    </script>
</body>
</html>