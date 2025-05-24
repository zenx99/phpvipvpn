<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// SQLite database file path
$db_file = __DIR__ . '/vipvpn.db';

// Get action and user_id from request
$action = $_GET['action'] ?? '';
$user_id = intval($_GET['user_id'] ?? 0);

header('Content-Type: application/json');

try {
    $db = new SQLite3($db_file);
    
    if ($action === 'get_vpn_history' && $user_id > 0) {
        // Get VPN history for specific user
        $stmt = $db->prepare('
            SELECT 
                code_name, 
                profile_key, 
                expiry_time, 
                gb_limit, 
                ip_limit, 
                created_at,
                is_enabled
            FROM vpn_history 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC
        ');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $vpn_history = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $vpn_history[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'vpn_history' => $vpn_history
        ]);
        
    } elseif ($action === 'get_topup_history' && $user_id > 0) {
        // Get topup history for specific user
        $stmt = $db->prepare('
            SELECT 
                amount, 
                credits, 
                method, 
                reference, 
                created_at
            FROM topup_history 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC
        ');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $topup_history = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $topup_history[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'topup_history' => $topup_history
        ]);
        
    } elseif ($action === 'get_user_stats' && $user_id > 0) {
        // Get comprehensive user statistics
        $stmt = $db->prepare('
            SELECT 
                u.username,
                u.credits,
                u.created_at,
                COUNT(vh.id) as total_vpn_codes,
                COUNT(DISTINCT vh.profile_key) as unique_packages,
                GROUP_CONCAT(DISTINCT vh.profile_key) as packages_used,
                COALESCE(SUM(th.amount), 0) as total_topup_amount,
                COUNT(th.id) as total_topups
            FROM users u
            LEFT JOIN vpn_history vh ON u.id = vh.user_id
            LEFT JOIN topup_history th ON u.id = th.user_id
            WHERE u.id = :user_id
            GROUP BY u.id
        ');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $user_stats = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user_stats) {
            echo json_encode([
                'success' => true,
                'user_stats' => $user_stats
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'User not found'
            ]);
        }
        
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action or missing parameters'
        ]);
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
