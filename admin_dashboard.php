<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit;
}

// SQLite database file path
$db_file = __DIR__ . '/vipvpn.db';

// Initialize messages
$success_message = '';
$error_message = '';

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    try {
        $db = new SQLite3($db_file);
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result) {
            $success_message = 'User has been successfully deleted';
        } else {
            $error_message = 'Failed to delete user';
        }
        
        $db->close();
    } catch (Exception $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Handle user editing
if (isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $credits = $_POST['credits'];
    $new_password = $_POST['new_password'];
    
    try {
        $db = new SQLite3($db_file);
        
        // Check if username already exists for other users
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username AND id != :id");
        $check_stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $check_stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $check_result = $check_stmt->execute();
        $check_row = $check_result->fetchArray();
        
        if ($check_row['count'] > 0) {
            $error_message = 'Username already exists for another user';
        } else {
            // Base SQL for updating user details without password
            $sql = "UPDATE users SET username = :username, credits = :credits WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':credits', $credits, SQLITE3_FLOAT);
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
            
            $result = $stmt->execute();
            
            // If new password is provided, update it
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pwd_stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
                $pwd_stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
                $pwd_stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                $pwd_result = $pwd_stmt->execute();
                
                if (!$pwd_result) {
                    $error_message = 'Failed to update password';
                }
            }
            
            if ($result) {
                $success_message = 'User information has been successfully updated';
            } else {
                $error_message = 'Failed to update user information';
            }
        }
        
        $db->close();
    } catch (Exception $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get all users from the database
$users = [];
try {
    $db = new SQLite3($db_file);
    $result = $db->query('SELECT id, username, credits, created_at FROM users ORDER BY id DESC');
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    $db->close();
} catch (Exception $e) {
    $error_message = 'Failed to fetch users: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .admin-title h1 {
            margin: 0;
            font-size: 24px;
            color: var(--dark-color);
        }
        
        .admin-title p {
            margin: 5px 0 0;
            color: #777;
        }
        
        .admin-actions {
            display: flex;
            align-items: center;
        }
        
        .admin-actions .btn {
            padding: 10px 15px;
            margin-left: 10px;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .user-table th,
        .user-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .user-table th {
            background-color: #f5f7ff;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .user-table tr:hover {
            background-color: #f9faff;
        }
        
        .user-table .actions {
            display: flex;
            gap: 10px;
        }
        
        .user-table .actions a {
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .user-table .actions .edit {
            background: var(--primary-gradient);
        }
        
        .user-table .actions .delete {
            background: linear-gradient(to right, #e74c3c, #c0392b);
        }
        
        .user-table .actions a i {
            margin-right: 5px;
        }
        
        .user-table .user-count {
            background-color: var(--primary-color);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .close {
            font-size: 28px;
            cursor: pointer;
        }
        
        .modal-form .form-row {
            margin-bottom: 15px;
        }
        
        .credits-badge {
            background: var(--primary-gradient);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body style="background: #f9fafc;">
    <div class="header">
        <div class="header-logo">
            <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo">
            <h1>VIP VPN</h1>
        </div>
        <div class="user-menu">
            <span class="user-info">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </span>
            <div class="user-dropdown">
                <div class="dropdown-header">Admin Menu</div>
                <a href="admin_dashboard.php" class="dropdown-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="admin-header">
            <div class="admin-title">
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p>Manage all user accounts in the system</p>
            </div>
            <div class="admin-actions">
                <a href="index.php" class="btn">
                    <i class="fas fa-home"></i> Back to Front
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div>
            <h2>Users <span class="user-count"><?php echo count($users); ?></span></h2>
            
            <?php if (count($users) > 0): ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Credits</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                                <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td data-label="Credits"><span class="credits-badge"><?php echo htmlspecialchars($user['credits']); ?></span></td>
                                <td data-label="Created At"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td data-label="Actions" class="actions">
                                    <a href="#" class="edit" onclick="openEditModal(<?php 
                                        echo htmlspecialchars($user['id']); ?>, 
                                        '<?php echo htmlspecialchars($user['username']); ?>', 
                                        <?php echo htmlspecialchars($user['credits']); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="#" class="delete" onclick="confirmDelete(<?php echo htmlspecialchars($user['id']); ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 50px 0;">
                    <i class="fas fa-users" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                    <p>No users found in the system</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="modal-form">
                <input type="hidden" id="user_id" name="user_id">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="credits"><i class="fas fa-coins"></i> Credits</label>
                    <input type="number" id="edit_credits" name="credits" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password"><i class="fas fa-key"></i> New Password (leave empty to keep current)</label>
                    <input type="password" id="new_password" name="new_password">
                    <span class="password-toggle" onclick="togglePassword('new_password')">
                        <i id="new_password_toggle_icon" class="fas fa-eye"></i>
                    </span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="btn btn-danger" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="edit_user" class="btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Handle user menu dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    this.classList.toggle('active');
                });
                
                document.addEventListener('click', function() {
                    userMenu.classList.remove('active');
                });
            }
        });
        
        // Edit modal functions
        function openEditModal(id, username, credits) {
            document.getElementById('user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_credits').value = credits;
            document.getElementById('new_password').value = '';
            
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Delete confirmation
        function confirmDelete(id, username) {
            if (confirm('Are you sure you want to delete the user "' + username + '"? This action cannot be undone.')) {
                window.location.href = '?action=delete&id=' + id;
            }
        }
        
        // Password toggle
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const iconId = inputId + '_toggle_icon';
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>
