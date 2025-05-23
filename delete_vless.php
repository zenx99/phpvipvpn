<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info from session
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Database connection
$db = new SQLite3(__DIR__ . '/vipvpn.db');

// Initialize variables
$message = '';
$messageType = '';
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'vpn_history.php';

// Check if ID is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vpn_id'])) {
    $vpn_id = intval($_POST['vpn_id']);
    
    try {
        // First, check if the VPN code belongs to the current user and get its details
        $stmt = $db->prepare('SELECT * FROM vpn_history WHERE id = :id AND user_id = :user_id');
        $stmt->bindValue(':id', $vpn_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $vpn = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($vpn) {
            // Extract the clientId from the vless code if possible
            $clientId = 'xxx-xxx-uuid'; // Default value
            
            // Try to extract clientId from vless_code
            // Format example: vless://client-id@domain:port?...
            if (preg_match('/vless:\/\/([^@]+)@/', $vpn['vless_code'], $matches)) {
                $clientId = $matches[1];
            }
            
            // Clean up the clientId (remove any extra spaces or characters that might cause problems)
            $clientId = trim($clientId);
            
            // VPN code exists and belongs to user, now make API call to delete it
            $apiUrl = 'http://103.245.164.86:4040/client/delete';
            $postData = json_encode([
                'vpsType' => 'IDC',
                'profileKey' => $vpn['profile_key'],
                'clientId' => $clientId
            ]);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $postData
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $apiErrorMessage = '';

            if (curl_errno($ch)) {
                $apiErrorMessage = 'API error: ' . curl_error($ch);
            } elseif ($httpCode !== 200) {
                $apiErrorMessage = 'API returned status code: ' . $httpCode;
            } else {
                $responseData = json_decode($response, true);
                if (!isset($responseData['success']) || $responseData['success'] !== true) {
                    $apiErrorMessage = 'API reported failure: ' . ($responseData['message'] ?? 'Unknown error');
                }
            }

            // Delete the record from the database regardless of API response
            // (This ensures users can clean their history even if API is down)
            $deleteStmt = $db->prepare('DELETE FROM vpn_history WHERE id = :id');
            $deleteStmt->bindValue(':id', $vpn_id, SQLITE3_INTEGER);
            $deleteResult = $deleteStmt->execute();
            
            if ($deleteResult) {
                $_SESSION['message'] = 'ลบโค้ด VPN สำเร็จ';
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = 'เกิดข้อผิดพลาดในการลบโค้ด VPN จากฐานข้อมูล';
                $_SESSION['messageType'] = 'error';
            }
            
            // Log API errors but don't show them to the user if the database deletion was successful
            if (!empty($apiErrorMessage)) {
                error_log("VPN delete API error for ID {$vpn_id}: {$apiErrorMessage}");
            }
            
            curl_close($ch);
        } else {
            $_SESSION['message'] = 'ไม่พบโค้ด VPN หรือคุณไม่มีสิทธิ์ลบโค้ดนี้';
            $_SESSION['messageType'] = 'error';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'เกิดข้อผิดพลาดในการลบโค้ด VPN: ' . $e->getMessage();
        $_SESSION['messageType'] = 'error';
    }
    
    $db->close();
    
    // Redirect back to referrer or history page
    header('Location: ' . $referrer);
    exit;
} else {
    // No VPN ID provided
    $_SESSION['message'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    $_SESSION['messageType'] = 'error';
    
    // Redirect back to history page
    header('Location: vpn_history.php');
    exit;
}
?>
