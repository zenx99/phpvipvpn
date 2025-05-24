<?php
// Alternative voucher verification using different methods
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

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
    // Method 1: Try different User-Agents and headers to bypass Cloudflare
    $userAgents = [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Android 11; Mobile; rv:68.0) Gecko/68.0 Firefox/88.0',
        'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36',
        'TrueMoneyWallet/3.0 (iPhone; iOS 14.6; Scale/3.00)',
        'okhttp/4.9.0'
    ];
    
    $proxies = [
        null, // Direct connection
        'socks5://127.0.0.1:9050', // Tor if available
    ];
    
    $lastError = '';
    
    foreach ($userAgents as $userAgent) {
        foreach ($proxies as $proxy) {
            $result = tryVoucherVerification($voucherHash, $userAgent, $proxy);
            if ($result !== false) {
                echo json_encode($result);
                exit;
            }
        }
    }
    
    // Method 2: Try different API endpoints
    $alternativeEndpoints = [
        "https://gift.truemoney.com/api/campaign/vouchers/{$voucherHash}",
        "https://gift.truemoney.com/v1/vouchers/{$voucherHash}",
        "https://api.truemoney.com/gift/vouchers/{$voucherHash}",
    ];
    
    foreach ($alternativeEndpoints as $endpoint) {
        $result = tryAlternativeEndpoint($endpoint, $voucherHash);
        if ($result !== false) {
            echo json_encode($result);
            exit;
        }
    }
    
    // Method 3: Return a warning but allow manual processing
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถตรวจสอบอั่งเปาอัตโนมัติได้ เนื่องจาก API ถูก block กรุณาส่งฟอร์มเพื่อตรวจสอบด้วยตนเอง',
        'allow_manual' => true,
        'voucher_hash' => $voucherHash
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
        'allow_manual' => true
    ]);
}

function tryVoucherVerification($voucherHash, $userAgent, $proxy = null) {
    try {
        $curl = curl_init();
        
        $verifyUrl = "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}";
        
        $curlOptions = [
            CURLOPT_URL => $verifyUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: th-TH,th;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'User-Agent: ' . $userAgent,
                'Referer: https://gift.truemoney.com/',
                'Origin: https://gift.truemoney.com',
                'Cache-Control: no-cache',
                'Pragma: no-cache'
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => '/tmp/cookies_' . md5($userAgent) . '.txt',
            CURLOPT_COOKIEFILE => '/tmp/cookies_' . md5($userAgent) . '.txt'
        ];
        
        if ($proxy) {
            $curlOptions[CURLOPT_PROXY] = $proxy;
        }
        
        curl_setopt_array($curl, $curlOptions);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        
        curl_close($curl);
        
        if ($curlError || $httpCode !== 200) {
            return false;
        }
        
        // Check if response contains Cloudflare block page
        if (strpos($response, 'Cloudflare') !== false && strpos($response, 'blocked') !== false) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            return false;
        }
        
        // Process successful response
        if (isset($data['data']['voucher'])) {
            $voucher = $data['data']['voucher'];
            
            return [
                'success' => true,
                'voucher' => [
                    'amount' => floatval($voucher['amount_baht'] ?? $voucher['amount'] ?? 0),
                    'status' => $voucher['status'] ?? 'ACTIVE',
                    'voucher_id' => $voucher['voucher_id'] ?? $voucherHash
                ],
                'message' => 'ตรวจสอบสำเร็จ'
            ];
        }
        
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}

function tryAlternativeEndpoint($endpoint, $voucherHash) {
    try {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: TrueMoneyWallet/3.0'
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
                    'message' => 'ตรวจสอบผ่าน Alternative API'
                ];
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        return false;
    }
}
?>
