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

// Get all users from the database with purchase statistics
$users = [];
try {
    $db = new SQLite3($db_file);
    
    // Get users with additional statistics
    $query = "
        SELECT 
            u.id, 
            u.username, 
            u.credits, 
            u.created_at,
            COALESCE(vpn_stats.vpn_count, 0) as vpn_count,
            COALESCE(vpn_stats.packages, '') as packages,
            COALESCE(topup_stats.total_topup, 0) as total_topup,
            COALESCE(topup_stats.topup_count, 0) as topup_count
        FROM users u
        LEFT JOIN (
            SELECT 
                user_id, 
                COUNT(*) as vpn_count,
                GROUP_CONCAT(DISTINCT 
                    CASE 
                        WHEN profile_key = 'true_pro_facebook' THEN 'True Pro FB'
                        WHEN profile_key = 'true_zoom' THEN 'True Zoom'
                        WHEN profile_key = 'ais' THEN 'AIS'
                        WHEN profile_key = 'dtac' THEN 'DTAC'
                        ELSE profile_key
                    END
                ) as packages
            FROM vpn_history 
            GROUP BY user_id
        ) vpn_stats ON u.id = vpn_stats.user_id
        LEFT JOIN (
            SELECT 
                user_id, 
                COUNT(*) as topup_count,
                SUM(amount) as total_topup
            FROM topup_history 
            GROUP BY user_id
        ) topup_stats ON u.id = topup_stats.user_id
        ORDER BY u.id DESC
    ";
    
    $result = $db->query($query);
    
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <img src="https://i.imgur.com/J1bqW0o.png" alt="VIP VPN Logo" class="h-8">
                <h1 class="text-xl font-bold text-gray-800">VIP VPN</h1>
            </div>
            <div class="relative user-menu">
                <button class="flex items-center space-x-2 text-gray-600 hover:text-gray-800">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </button>
                <div class="user-dropdown hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10">
                    <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b">Admin Menu</div>
                    <a href="admin_dashboard.php" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                    </a>
                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800"><i class="fas fa-users-cog mr-2"></i>User Management</h2>
                    <p class="text-sm text-gray-500">Manage all user accounts in the system</p>
                </div>
                <div class="flex space-x-2 mt-4 sm:mt-0">
                    <a href="index.php" class="btn bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        <i class="fas fa-home mr-2"></i>Back to Front
                    </a>
                    <a href="logout.php" class="btn bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-md mb-4 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-md mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    Users <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-full"><?php echo count($users); ?></span>
                </h3>

                <?php if (count($users) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-gray-50 text-gray-700">
                                    <th class="p-3 text-sm font-semibold">ID</th>
                                    <th class="p-3 text-sm font-semibold">Username</th>
                                    <th class="p-3 text-sm font-semibold">Credits</th>
                                    <th class="p-3 text-sm font-semibold">VPN Codes</th>
                                    <th class="p-3 text-sm font-semibold">Packages</th>
                                    <th class="p-3 text-sm font-semibold">Top-ups</th>
                                    <th class="p-3 text-sm font-semibold">Created At</th>
                                    <th class="p-3 text-sm font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 text-sm"><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td class="p-3 text-sm font-medium"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="p-3 text-sm">
                                            <span class="bg-blue-500 text-white px-2 py-1 rounded-full text-xs"><?php echo htmlspecialchars($user['credits']); ?></span>
                                        </td>
                                        <td class="p-3 text-sm">
                                            <div class="flex items-center space-x-2">
                                                <span class="bg-green-500 text-white px-2 py-1 rounded-full text-xs">
                                                    <i class="fas fa-code mr-1"></i><?php echo htmlspecialchars($user['vpn_count']); ?>
                                                </span>
                                                <?php if ($user['vpn_count'] > 0): ?>
                                                    <button onclick="showVPNDetails(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                            class="text-blue-500 hover:text-blue-700 text-xs">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="p-3 text-sm">
                                            <?php if (!empty($user['packages'])): ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php 
                                                    $packages = explode(',', $user['packages']);
                                                    foreach (array_slice($packages, 0, 2) as $package): 
                                                        $package = trim($package);
                                                        $colorClass = '';
                                                        switch($package) {
                                                            case 'True Pro FB': $colorClass = 'bg-blue-600'; break;
                                                            case 'True Zoom': $colorClass = 'bg-purple-600'; break;
                                                            case 'AIS': $colorClass = 'bg-green-600'; break;
                                                            case 'DTAC': $colorClass = 'bg-orange-600'; break;
                                                            default: $colorClass = 'bg-gray-600';
                                                        }
                                                    ?>
                                                        <span class="<?php echo $colorClass; ?> text-white px-2 py-1 rounded text-xs">
                                                            <?php echo htmlspecialchars($package); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($packages) > 2): ?>
                                                        <span class="bg-gray-400 text-white px-2 py-1 rounded text-xs">
                                                            +<?php echo count($packages) - 2; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-sm">
                                            <?php if ($user['topup_count'] > 0): ?>
                                                <div class="flex items-center space-x-2">
                                                    <span class="bg-yellow-500 text-white px-2 py-1 rounded-full text-xs">
                                                        <i class="fas fa-plus-circle mr-1"></i><?php echo htmlspecialchars($user['topup_count']); ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500">
                                                        ฿<?php echo number_format($user['total_topup'], 0); ?>
                                                    </span>
                                                    <button onclick="showTopupDetails(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                            class="text-blue-500 hover:text-blue-700 text-xs">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-sm"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                        <td class="p-3 text-sm flex space-x-2">
                                            <button onclick="openEditModal(<?php echo htmlspecialchars($user['id']); ?>, '<?php echo htmlspecialchars($user['username']); ?>', <?php echo htmlspecialchars($user['credits']); ?>)" class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </button>
                                            <button onclick="confirmDelete(<?php echo htmlspecialchars($user['id']); ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="bg-red-500 text-white px-3 py-1 rounded-md hover:bg-red-600">
                                                <i class="fas fa-trash-alt mr-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No users found in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800"><i class="fas fa-user-edit mr-2"></i>Edit User</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-4">
                <input type="hidden" id="user_id" name="user_id">
                <div>
                    <label for="edit_username" class="block text-sm font-medium text-gray-700"><i class="fas fa-user mr-2"></i>Username</label>
                    <input type="text" id="edit_username" name="username" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="edit_credits" class="block text-sm font-medium text-gray-700"><i class="fas fa-coins mr-2"></i>Credits</label>
                    <input type="number" id="edit_credits" name="credits" min="0" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-key mr-2"></i>New Password (leave empty to keep current)</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-blue-500 focus:border-blue-500">
                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('new_password')">
                            <i id="new_password_toggle_icon" class="fas fa-eye text-gray-500"></i>
                        </button>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal()" class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" name="edit_user" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VPN Details Modal -->
    <div id="vpnModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800"><i class="fas fa-code mr-2"></i>VPN Codes History</h2>
                <button onclick="closeVPNModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div id="vpnModalContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                    <p class="text-gray-500 mt-2">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Topup Details Modal -->
    <div id="topupModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[80vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800"><i class="fas fa-plus-circle mr-2"></i>Topup History</h2>
                <button onclick="closeTopupModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div id="topupModalContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                    <p class="text-gray-500 mt-2">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle user menu dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = this.querySelector('.user-dropdown');
                    dropdown.classList.toggle('hidden');
                });
                
                document.addEventListener('click', function() {
                    const dropdown = userMenu.querySelector('.user-dropdown');
                    dropdown.classList.add('hidden');
                });
            }
        });

        // Edit modal functions
        function openEditModal(id, username, credits) {
            document.getElementById('user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_credits').value = credits;
            document.getElementById('new_password').value = '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Delete confirmation
        function confirmDelete(id, username) {
            if (confirm(`Are you sure you want to delete the user "${username}"? This action cannot be undone.`)) {
                window.location.href = `?action=delete&id=${id}`;
            }
        }

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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        };

        // VPN Details Modal Functions
        function showVPNDetails(userId, username) {
            document.getElementById('vpnModal').classList.remove('hidden');
            loadVPNDetails(userId, username);
        }

        function closeVPNModal() {
            document.getElementById('vpnModal').classList.add('hidden');
        }

        function loadVPNDetails(userId, username) {
            const content = document.getElementById('vpnModalContent');
            
            fetch('admin_ajax.php?action=get_vpn_history&user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<h3 class="text-lg font-medium mb-4">VPN Codes for: ' + username + '</h3>';
                        
                        if (data.vpn_history && data.vpn_history.length > 0) {
                            html += '<div class="overflow-x-auto">';
                            html += '<table class="w-full text-left border-collapse">';
                            html += '<thead><tr class="bg-gray-50">';
                            html += '<th class="border p-3 text-sm font-semibold">Code Name</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Package</th>';
                            html += '<th class="border p-3 text-sm font-semibold">GB Limit</th>';
                            html += '<th class="border p-3 text-sm font-semibold">IP Limit</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Status</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Created</th>';
                            html += '</tr></thead><tbody>';
                            
                            data.vpn_history.forEach(function(vpn) {
                                const packageName = getPackageName(vpn.profile_key);
                                const isExpired = vpn.expiry_time < (Date.now() / 1000);
                                const statusClass = isExpired ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
                                const statusText = isExpired ? 'Expired' : 'Active';
                                
                                html += '<tr class="border-b hover:bg-gray-50">';
                                html += '<td class="border p-3 text-sm">' + vpn.code_name + '</td>';
                                html += '<td class="border p-3 text-sm">' + packageName + '</td>';
                                html += '<td class="border p-3 text-sm">' + (vpn.gb_limit || 'Unlimited') + '</td>';
                                html += '<td class="border p-3 text-sm">' + vpn.ip_limit + '</td>';
                                html += '<td class="border p-3 text-sm"><span class="px-2 py-1 rounded text-xs ' + statusClass + '">' + statusText + '</span></td>';
                                html += '<td class="border p-3 text-sm">' + new Date(vpn.created_at).toLocaleDateString() + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table></div>';
                        } else {
                            html += '<p class="text-gray-500 text-center py-8">No VPN codes found for this user.</p>';
                        }
                        
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p class="text-red-500 text-center py-8">Error loading VPN details.</p>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<p class="text-red-500 text-center py-8">Error loading VPN details.</p>';
                });
        }

        // Topup Details Modal Functions
        function showTopupDetails(userId, username) {
            document.getElementById('topupModal').classList.remove('hidden');
            loadTopupDetails(userId, username);
        }

        function closeTopupModal() {
            document.getElementById('topupModal').classList.add('hidden');
        }

        function loadTopupDetails(userId, username) {
            const content = document.getElementById('topupModalContent');
            
            fetch('admin_ajax.php?action=get_topup_history&user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<h3 class="text-lg font-medium mb-4">Topup History for: ' + username + '</h3>';
                        
                        if (data.topup_history && data.topup_history.length > 0) {
                            html += '<div class="overflow-x-auto">';
                            html += '<table class="w-full text-left border-collapse">';
                            html += '<thead><tr class="bg-gray-50">';
                            html += '<th class="border p-3 text-sm font-semibold">Amount</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Credits</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Method</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Reference</th>';
                            html += '<th class="border p-3 text-sm font-semibold">Date</th>';
                            html += '</tr></thead><tbody>';
                            
                            data.topup_history.forEach(function(topup) {
                                html += '<tr class="border-b hover:bg-gray-50">';
                                html += '<td class="border p-3 text-sm">฿' + parseFloat(topup.amount).toFixed(2) + '</td>';
                                html += '<td class="border p-3 text-sm">' + topup.credits + '</td>';
                                html += '<td class="border p-3 text-sm">' + getMethodName(topup.method) + '</td>';
                                html += '<td class="border p-3 text-sm"><code class="text-xs bg-gray-100 px-1 rounded">' + topup.reference + '</code></td>';
                                html += '<td class="border p-3 text-sm">' + new Date(topup.created_at).toLocaleDateString() + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table></div>';
                        } else {
                            html += '<p class="text-gray-500 text-center py-8">No topup history found for this user.</p>';
                        }
                        
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p class="text-red-500 text-center py-8">Error loading topup details.</p>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<p class="text-red-500 text-center py-8">Error loading topup details.</p>';
                });
        }

        function getPackageName(profileKey) {
            const packages = {
                'true_pro_facebook': 'True Pro FB',
                'true_zoom': 'True Zoom',
                'ais': 'AIS',
                'dtac': 'DTAC'
            };
            return packages[profileKey] || profileKey;
        }

        function getMethodName(method) {
            const methods = {
                'truemoney': 'TrueMoney Voucher',
                'truemoney_angpao': 'TrueMoney Angpao',
                'truewallet_slip': 'TrueWallet Slip',
                'bank_slip_KBANK': 'Bank Transfer (KBANK)',
                'bank_slip_SCB': 'Bank Transfer (SCB)',
                'bank_slip_BBL': 'Bank Transfer (BBL)',
                'bank_slip_KTB': 'Bank Transfer (KTB)',
                'bank_slip_TMB': 'Bank Transfer (TMB)',
                'bank_slip_BAY': 'Bank Transfer (BAY)'
            };
            return methods[method] || method;
        }
    </script>
</body>
</html>