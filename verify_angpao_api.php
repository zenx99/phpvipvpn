<?php
/**
 * API endpoint for verifying TrueMoney angpao links
 * This file provides real-time verification for the angpao form
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the angpao link from POST data
$angpaoLink = $_POST['angpao_link'] ?? '';

if (empty($angpaoLink)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุลิงก์อั่งเปา']);
    exit;
}

// Include the verification functions
require_once('topup_functions.php');

try {
    // Use the verification function
    $result = verifyPaymentLinkangpao($angpaoLink);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'amount' => $result['amount'],
            'credits' => $result['amount'], // 1 baht = 1 credit
            'voucher_hash' => $result['voucher_hash'],
            'message' => 'ตรวจสอบอั่งเปาสำเร็จ'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
