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
$servers = [];

// Handle Server Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_server' && isset($_GET['id'])) {
    $server_id_to_delete = $_GET['id'];
    try {
        $db = new SQLite3($db_file);
        $stmt = $db->prepare("DELETE FROM servers WHERE id = :id");
        $stmt->bindValue(':id', $server_id_to_delete, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Server deleted successfully.'; // Use session for message after redirect
        } else {
            $_SESSION['error_message'] = 'Failed to delete server: ' . $db->lastErrorMsg();
        }
        $db->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Database error during deletion: ' . $e->getMessage();
    }
    // Redirect to clean URL and show message
    header('Location: server_management.php');
    exit;
}

// Display session messages if any
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// Handle Add Server form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_server'])) {
    $name = trim($_POST['server_name']);
    $ip_address = trim($_POST['server_ip_address']);
    $location = trim($_POST['server_location']);
    $status = $_POST['server_status'];

    if (empty($name) || empty($ip_address)) {
        $error_message = 'Server Name and IP Address are required.';
    } else {
        try {
            $db = new SQLite3($db_file);
            $stmt = $db->prepare("INSERT INTO servers (name, ip_address, location, status) VALUES (:name, :ip_address, :location, :status)");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':ip_address', $ip_address, SQLITE3_TEXT);
            $stmt->bindValue(':location', $location, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $success_message = 'Server added successfully.';
                // No need to redirect, server list will be fetched below
            } else {
                $error_message = 'Failed to add server: ' . $db->lastErrorMsg();
            }
            $db->close();
        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle Edit Server form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_server'])) {
    $server_id = $_POST['edit_server_id'];
    $name = trim($_POST['edit_server_name']);
    $ip_address = trim($_POST['edit_server_ip_address']);
    $location = trim($_POST['edit_server_location']);
    $status = $_POST['edit_server_status'];

    if (empty($name) || empty($ip_address) || empty($server_id)) {
        $error_message = 'Server ID, Name, and IP Address are required for editing.';
    } else {
        try {
            $db = new SQLite3($db_file);
            $stmt = $db->prepare("UPDATE servers SET name = :name, ip_address = :ip_address, location = :location, status = :status WHERE id = :id");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':ip_address', $ip_address, SQLITE3_TEXT);
            $stmt->bindValue(':location', $location, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':id', $server_id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $success_message = 'Server updated successfully.';
            } else {
                $error_message = 'Failed to update server: ' . $db->lastErrorMsg();
            }
            $db->close();
        } catch (Exception $e) {
            $error_message = 'Database error during update: ' . $e->getMessage();
        }
    }
}

// Fetch all servers from the database
try {
    $db = new SQLite3($db_file);
    $result = $db->query('SELECT id, name, ip_address, location, status FROM servers ORDER BY id DESC');
    
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $servers[] = $row;
        }
    } else {
        // If query fails, $db->lastErrorMsg() might not be set if the error is not from SQLite itself
        // For example, if the table doesn't exist, it's an SQLite error.
        // But if $db is false (connection failed), then lastErrorMsg() won't work.
        $error_message = 'Failed to fetch servers. Error: ' . $db->lastErrorMsg();
        if (!$db->lastErrorMsg()) { // More generic error if SQLite doesn't give one
            $error_message = 'Failed to fetch servers. The table might be missing or there was a connection issue.';
        }
    }
    
    $db->close();
} catch (Exception $e) {
    // This will catch errors like SQLite3 connection failure
    $error_message = 'Database connection error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Server Management - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Styles from admin_dashboard.php for consistency */
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

        /* Table styles (mimicking .user-table from admin_dashboard.php) */
        .data-table { /* Renamed from .user-table for semantic clarity if needed, but styled identically */
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f5f7ff;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background-color: #f9faff;
        }
        
        .data-table .actions {
            display: flex;
            gap: 10px;
        }
        
        .data-table .actions a {
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .data-table .actions .edit {
            background: var(--primary-gradient);
        }
        
        .data-table .actions .delete {
            background: linear-gradient(to right, #e74c3c, #c0392b);
        }
        
        .data-table .actions a i {
            margin-right: 5px;
        }
        
        .item-count { /* Equivalent to .user-count */
            background-color: var(--primary-color);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
        }

        /* Modal Styles (from admin_dashboard.php) */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
            z-index: 1000;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
        }
        
        .modal-content {
            background-color: white;
            padding: 30px; /* Matched admin_dashboard.php */
            border-radius: 12px; /* Matched admin_dashboard.php */
            width: 500px; /* Matched admin_dashboard.php */
            max-width: 90%; /* Responsive */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); /* Matched admin_dashboard.php */
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px; /* Matched admin_dashboard.php */
            padding-bottom: 15px; /* Matched admin_dashboard.php */
            border-bottom: 1px solid #eee; /* Matched admin_dashboard.php */
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px; /* Matched admin_dashboard.php */
        }
        
        .close { /* Matched admin_dashboard.php */
            font-size: 28px;
            cursor: pointer;
            color: #aaa; /* Added for consistency */
            font-weight: bold; /* Added for consistency */
        }
        .close:hover,
        .close:focus {
            color: black; /* Matched admin_dashboard.php */
            text-decoration: none; /* Matched admin_dashboard.php */
        }
        
        .modal-form .form-group { /* .form-group is used in server_management, .form-row in admin_dashboard but contains .form-group */
            margin-bottom: 15px;
        }
        
        .modal-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600; /* Often good for labels */
            color: #555; /* Typical label color */
        }
        
        .modal-form input[type="text"],
        .modal-form input[type="number"],
        .modal-form input[type="password"],
        .modal-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px; /* Ensure consistent font size */
        }
        
        /* Message Styles (from admin_dashboard.php) */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px; /* Matched from admin_dashboard.php (if it was 8px) or ensure consistency */
            display: flex;
            align-items: center;
            font-size: 16px;
            /* border: 1px solid transparent; Should be set by specific message types */
        }
        .message i {
            margin-right: 10px;
            font-size: 20px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb; /* Matched admin_dashboard.php */
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb; /* Matched admin_dashboard.php */
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
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
            </span>
            <div class="user-dropdown">
                <div class="dropdown-header">Admin Menu</div>
                <a href="admin_dashboard.php" class="dropdown-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="server_management.php" class="dropdown-item">
                    <i class="fas fa-server"></i> Server Management
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
                <h1><i class="fas fa-server"></i> Server Management</h1>
                <p>Manage all VPN servers in the system</p>
            </div>
            <div class="admin-actions">
                 <a href="admin_dashboard.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-primary" onclick="openAddServerModal()">
                    <i class="fas fa-plus"></i> Add New Server
                </button>
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
            <h2>Servers <span class="item-count"><?php echo count($servers); ?></span></h2>
            
            <?php if (count($servers) > 0): ?>
                <table class="data-table"> <!-- Changed class to data-table -->
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>IP Address</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $server): ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($server['id']); ?></td>
                                <td data-label="Name"><?php echo htmlspecialchars($server['name']); ?></td>
                                <td data-label="IP Address"><?php echo htmlspecialchars($server['ip_address']); ?></td>
                                <td data-label="Location"><?php echo htmlspecialchars($server['location'] ?? 'N/A'); ?></td>
                                <td data-label="Status"><?php echo htmlspecialchars($server['status'] ?? 'Unknown'); ?></td>
                                <td data-label="Actions" class="actions">
                                    <a href="javascript:void(0);" class="edit" onclick="openEditServerModal(
                                        '<?php echo htmlspecialchars($server['id']); ?>',
                                        '<?php echo htmlspecialchars(addslashes($server['name'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($server['ip_address'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($server['location'] ?? '')); ?>',
                                        '<?php echo htmlspecialchars(addslashes($server['status'] ?? 'active')); ?>'
                                    )">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="javascript:void(0);" class="delete" onclick="confirmDeleteServer('<?php echo htmlspecialchars($server['id']); ?>', '<?php echo htmlspecialchars(addslashes($server['name'])); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 50px 0;">
                    <i class="fas fa-server" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                    <p>No servers found.</p>
                    <?php if(empty($error_message)): // Only show "add server" message if there wasn't a db error ?>
                    <p>You can add a new server to get started (functionality to be added).</p> 
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    // Prevent dropdown from closing if an item inside is clicked
                    if (!e.target.closest('.user-dropdown')) {
                         e.stopPropagation();
                    }
                    this.classList.toggle('active');
                });
                
                document.addEventListener('click', function(e) {
                    // Close dropdown if clicked outside of user-menu
                    if (!userMenu.contains(e.target) && userMenu.classList.contains('active')) {
                        userMenu.classList.remove('active');
                    }
                });
            }

            // Add Server Modal functions
            const addServerModal = document.getElementById('addServerModal');

            function openAddServerModal() {
                // Clear previous form values if any
                document.getElementById('server_name').value = '';
                document.getElementById('server_ip_address').value = '';
                document.getElementById('server_location').value = '';
                document.getElementById('server_status').value = 'active'; // Default status
                addServerModal.style.display = 'flex';
            }

            function closeAddServerModal() {
                addServerModal.style.display = 'none';
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === addServerModal) {
                    closeAddServerModal();
                }
                if (event.target === editServerModal) { // Added for edit modal
                    closeEditServerModal();
                }
            });

            // Edit Server Modal functions
            const editServerModal = document.getElementById('editServerModal');

            function openEditServerModal(id, name, ip_address, location, status) {
                document.getElementById('edit_server_id').value = id;
                document.getElementById('edit_server_name').value = name;
                document.getElementById('edit_server_ip_address').value = ip_address;
                document.getElementById('edit_server_location').value = location;
                document.getElementById('edit_server_status').value = status;
                editServerModal.style.display = 'flex';
            }

            function closeEditServerModal() {
                editServerModal.style.display = 'none';
            }

            // Confirm Server Deletion
            function confirmDeleteServer(id, name) {
                if (confirm('Are you sure you want to delete the server "' + name + '"? This action cannot be undone.')) {
                    window.location.href = 'server_management.php?action=delete_server&id=' + id;
                }
            }
        });
    </script>

    <!-- Add Server Modal -->
    <div id="addServerModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Server</h2>
                <span class="close" onclick="closeAddServerModal()">&times;</span>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="modal-form">
                <div class="form-group">
                    <label for="server_name"><i class="fas fa-server"></i> Server Name</label>
                    <input type="text" id="server_name" name="server_name" required>
                </div>
                
                <div class="form-group">
                    <label for="server_ip_address"><i class="fas fa-network-wired"></i> IP Address</label>
                    <input type="text" id="server_ip_address" name="server_ip_address" required>
                </div>
                
                <div class="form-group">
                    <label for="server_location"><i class="fas fa-map-marker-alt"></i> Location (Optional)</label>
                    <input type="text" id="server_location" name="server_location">
                </div>
                
                <div class="form-group">
                    <label for="server_status"><i class="fas fa-power-off"></i> Status</label>
                    <select id="server_status" name="server_status">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="btn btn-danger" onclick="closeAddServerModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="add_server" class="btn">
                        <i class="fas fa-save"></i> Save Server
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Server Modal -->
    <div id="editServerModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Server</h2>
                <span class="close" onclick="closeEditServerModal()">&times;</span>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="modal-form">
                <input type="hidden" id="edit_server_id" name="edit_server_id">
                
                <div class="form-group">
                    <label for="edit_server_name"><i class="fas fa-server"></i> Server Name</label>
                    <input type="text" id="edit_server_name" name="edit_server_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_server_ip_address"><i class="fas fa-network-wired"></i> IP Address</label>
                    <input type="text" id="edit_server_ip_address" name="edit_server_ip_address" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_server_location"><i class="fas fa-map-marker-alt"></i> Location (Optional)</label>
                    <input type="text" id="edit_server_location" name="edit_server_location">
                </div>
                
                <div class="form-group">
                    <label for="edit_server_status"><i class="fas fa-power-off"></i> Status</label>
                    <select id="edit_server_status" name="edit_server_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="btn btn-danger" onclick="closeEditServerModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="edit_server" class="btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
