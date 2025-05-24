<?php
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

// Get the voucher hash from POST data
$voucherHash = $_POST['voucher_hash'] ?? '';

if (empty($voucherHash)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุรหัสอั่งเปา']);
    exit;
}

// Validate voucher hash format
if (!preg_match('/^[0-9A-Fa-f]{18,50}$/', $voucherHash)) {
    echo json_encode(['success' => false, 'message' => 'รูปแบบรหัสอั่งเปาไม่ถูกต้อง']);
    exit;
}

try {
    // Initialize cURL for TrueMoney API verification
    $curl = curl_init();
    
    // Try verification endpoint first
    $verifyUrl = "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}";
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $verifyUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: th-TH,th;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Referer: https://gift.truemoney.com/',
            'Origin: https://gift.truemoney.com'
        ],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    
    curl_close($curl);
    
    if ($curlError) {
        throw new Exception('การเชื่อมต่อล้มเหลว: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        // Try alternative verification methods
        $alternativeResult = verifyVoucherAlternative($voucherHash);
        if ($alternativeResult) {
            echo json_encode($alternativeResult);
            exit;
        }
        
        // Handle different HTTP status codes
        switch ($httpCode) {
            case 404:
                echo json_encode(['success' => false, 'message' => 'ไม่พบอั่งเปานี้ หรืออาจถูกใช้งานไปแล้ว']);
                break;
            case 400:
                echo json_encode(['success' => false, 'message' => 'รูปแบบอั่งเปาไม่ถูกต้อง']);
                break;
            case 500:
                echo json_encode(['success' => false, 'message' => 'เซิร์ฟเวอร์ TrueMoney ไม่พร้อมใช้งาน']);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'ไม่สามารถตรวจสอบอั่งเปาได้ (HTTP: ' . $httpCode . ')']);
        }
        exit;
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        throw new Exception('ไม่สามารถแปลงข้อมูลได้');
    }
    
    // Process the response
    if (isset($data['data']['voucher'])) {
        $voucher = $data['data']['voucher'];
        
        $voucherInfo = [
            'amount' => floatval($voucher['amount_baht'] ?? $voucher['amount'] ?? 0),
            'status' => $voucher['status'] ?? 'UNKNOWN',
            'voucher_id' => $voucher['voucher_id'] ?? $voucherHash,
            'expires_at' => $voucher['expires_at'] ?? null,
            'redeemed_at' => $voucher['redeemed_at'] ?? null
        ];
        
        // Check if voucher is valid and has amount
        if ($voucherInfo['amount'] > 0) {
            echo json_encode([
                'success' => true,
                'voucher' => $voucherInfo,
                'message' => 'อั่งเปาถูกต้องและพร้อมใช้งาน'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'อั่งเปานี้ไม่มีจำนวนเงิน หรืออาจถูกใช้งานไปแล้ว'
            ]);
        }
    } else if (isset($data['status'])) {
        // Handle different status responses
        $status = $data['status'];
        $statusCode = $status['code'] ?? 'UNKNOWN';
        
        switch ($statusCode) {
            case 'VOUCHER_NOT_FOUND':
                echo json_encode(['success' => false, 'message' => 'ไม่พบอั่งเปานี้ในระบบ']);
                break;
            case 'VOUCHER_OUT_OF_STOCK':
                echo json_encode(['success' => false, 'message' => 'อั่งเปานี้ถูกใช้งานไปแล้ว']);
                break;
            case 'CAMPAIGN_INACTIVE':
                echo json_encode(['success' => false, 'message' => 'แคมเปญนี้ไม่ได้เปิดใช้งาน']);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'สถานะอั่งเปา: ' . $statusCode]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่สามารถตรวจสอบข้อมูลอั่งเปาได้'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}

/**
 * Alternative verification method using different API endpoints
 */
function verifyVoucherAlternative($voucherHash) {
    try {
        $curl = curl_init();
        
        // Try the redemption preview endpoint
        $previewUrl = "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}/redeem_preview";
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $previewUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['data']['voucher'])) {
                $voucher = $data['data']['voucher'];
                
                return [
                    'success' => true,
                    'voucher' => [
                        'amount' => floatval($voucher['amount_baht'] ?? $voucher['amount'] ?? 0),
                        'status' => $voucher['status'] ?? 'ACTIVE',
                        'voucher_id' => $voucher['voucher_id'] ?? $voucherHash
                    ],
                    'message' => 'ตรวจสอบผ่านวิธีการสำรอง'
                ];
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}
?>
