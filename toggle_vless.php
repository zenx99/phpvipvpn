<?php
session_start();

// Set timezone to Asia/Bangkok (Thailand timezone)
date_default_timezone_set('Asia/Bangkok');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user info from session
$user_id = $_SESSION['user_id'];

// Database connection
$db = new SQLite3(__DIR__ . '/vipvpn.db');

// Initialize variables
$message = '';
$messageType = '';
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'vpn_history.php';

// Check if required parameters are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vpn_id']) && isset($_POST['enable'])) {
    $vpn_id = intval($_POST['vpn_id']);
    $enable = $_POST['enable'] === 'true' ? true : false;
    
    try {
        // First, check if the VPN code belongs to the current user and get its details
        $stmt = $db->prepare('SELECT * FROM vpn_history WHERE id = :id AND user_id = :user_id');
        $stmt->bindValue(':id', $vpn_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $vpn = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($vpn) {
            // Extract the clientId from the vless code
            $clientId = '';
            
            // Try to extract clientId from vless_code
            // Format example: vless://client-id@domain:port?...
            if (preg_match('/vless:\/\/([^@]+)@/', $vpn['vless_code'], $matches)) {
                $clientId = $matches[1];
            }
            
            // Clean up the clientId
            $clientId = trim($clientId);
            
            if (!empty($clientId)) {
                // Make API call to toggle the VPN code
                $apiUrl = 'http://103.245.164.86:4040/client/toggle';
                $postData = json_encode([
                    'vpsType' => 'IDC',
                    'profileKey' => $vpn['profile_key'],
                    'clientId' => $clientId,
                    'enable' => $enable
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
                
                if (curl_errno($ch)) {
                    $_SESSION['message'] = 'การเชื่อมต่อ API ผิดพลาด: ' . curl_error($ch);
                    $_SESSION['messageType'] = 'error';
                } elseif ($httpCode !== 200) {
                    $_SESSION['message'] = 'API ส่งรหัสสถานะ: ' . $httpCode;
                    $_SESSION['messageType'] = 'error';
                } else {
                    $json = json_decode($response, true);
                    if (isset($json['success']) && $json['success']) {
                        $_SESSION['message'] = $enable ? 'เปิดใช้งานโค้ด VPN สำเร็จ' : 'ปิดการใช้งานโค้ด VPN สำเร็จ';
                        $_SESSION['messageType'] = 'success';
                        
                        // Update status in database
                        $updateStmt = $db->prepare('UPDATE vpn_history SET is_enabled = :is_enabled WHERE id = :id');
                        $updateStmt->bindValue(':is_enabled', $enable ? 1 : 0, SQLITE3_INTEGER);
                        $updateStmt->bindValue(':id', $vpn_id, SQLITE3_INTEGER);
                        $updateStmt->execute();
                    } else {
                        $_SESSION['message'] = 'การ' . ($enable ? 'เปิด' : 'ปิด') . 'ใช้งานโค้ด VPN ล้มเหลว: ' . 
                                              (isset($json['message']) ? $json['message'] : 'ข้อผิดพลาดที่ไม่ทราบสาเหตุ');
                        $_SESSION['messageType'] = 'error';
                    }
                }
                
                curl_close($ch);
            } else {
                $_SESSION['message'] = 'ไม่สามารถอ่านรหัส Client ID จากโค้ด VPN ได้';
                $_SESSION['messageType'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'ไม่พบโค้ด VPN หรือคุณไม่มีสิทธิ์จัดการโค้ดนี้';
            $_SESSION['messageType'] = 'error';
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $_SESSION['messageType'] = 'error';
    }
    
    $db->close();
} else {
    $_SESSION['message'] = 'ข้อมูลไม่ครบถ้วน';
    $_SESSION['messageType'] = 'error';
}

// Redirect back to the history page
header('Location: ' . $referrer);
exit;
?>
