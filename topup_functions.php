<?php
/**
 * TrueMoney Angpao Verification Functions
 * Contains all the verification logic for TrueMoney payment links
 */

/**
 * Verify TrueMoney Payment Link angpao using API
 * @param string $angpaoLink The TrueMoney gift link
 * @return array Result with success status, amount, and message
 */
function verifyPaymentLinkangpao($angpaoLink) {
    // Extract voucher hash from the link
    $voucherHash = null;
    
    // Pattern 1: Standard gift link
    $linkRegex1 = '/https:\/\/gift\.truemoney\.com\/campaign\/?\?v=([0-9A-Fa-f]{32})/';
    if (preg_match($linkRegex1, $angpaoLink, $matches)) {
        $voucherHash = $matches[1];
    }
    
    // Pattern 2: Alternative campaign link format
    if (!$voucherHash) {
        $linkRegex2 = '/https:\/\/gift\.truemoney\.com\/campaign\?v=([0-9A-Fa-f]{32})/';
        if (preg_match($linkRegex2, $angpaoLink, $matches)) {
            $voucherHash = $matches[1];
        }
    }
    
    // Pattern 3: More flexible pattern for various hash lengths and formats
    if (!$voucherHash) {
        $linkRegex3 = '/v=([0-9A-Fa-f]{18,50})/';
        if (preg_match($linkRegex3, $angpaoLink, $matches)) {
            $voucherHash = $matches[1];
        }
    }
    
    if (!$voucherHash) {
        return [
            'success' => false,
            'message' => 'ลิงก์ซองอั่งเปาไม่ถูกต้อง กรุณาตรวจสอบลิงก์อีกครั้ง',
            'amount' => 0,
            'voucher_hash' => null
        ];
    }
    
    // Try multiple verification methods
    $verificationResult = tryMultipleVerificationMethods($voucherHash);
    
    if ($verificationResult && $verificationResult['success']) {
        return [
            'success' => true,
            'message' => 'ตรวจสอบอั่งเปาสำเร็จ',
            'amount' => $verificationResult['amount'],
            'voucher_hash' => $voucherHash,
            'voucher_data' => $verificationResult['voucher_data'] ?? null
        ];
    }
    
    return [
        'success' => false,
        'message' => 'ไม่สามารถตรวจสอบอั่งเปาได้ อาจถูกใช้งานแล้วหรือหมดอายุ',
        'amount' => 0,
        'voucher_hash' => $voucherHash
    ];
}

/**
 * Try multiple verification methods for TrueMoney vouchers
 * @param string $voucherHash The voucher hash to verify
 * @return array|false Verification result or false on failure
 */
function tryMultipleVerificationMethods($voucherHash) {
    // Method 1: Standard verification endpoint
    $result = tryVerificationMethod1($voucherHash);
    if ($result && $result['success']) {
        return $result;
    }
    
    // Method 2: Alternative endpoint
    $result = tryVerificationMethod2($voucherHash);
    if ($result && $result['success']) {
        return $result;
    }
    
    // Method 3: Redemption preview endpoint
    $result = tryVerificationMethod3($voucherHash);
    if ($result && $result['success']) {
        return $result;
    }
    
    return false;
}

/**
 * Verification Method 1: Standard API endpoint
 */
function tryVerificationMethod1($voucherHash) {
    try {
        $curl = curl_init();
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
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => ''
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['data']['voucher'])) {
                $voucher = $data['data']['voucher'];
                $amount = floatval($voucher['amount_baht'] ?? $voucher['amount'] ?? 0);
                
                if ($amount > 0) {
                    return [
                        'success' => true,
                        'amount' => $amount,
                        'voucher_data' => $voucher
                    ];
                }
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verification Method 2: Alternative endpoint with different headers
 */
function tryVerificationMethod2($voucherHash) {
    try {
        $curl = curl_init();
        $verifyUrl = "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}";
        
        $userAgents = [
            'TrueMoneyWallet/3.0 (iPhone; iOS 14.0; Scale/3.00)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 10; SM-G975F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Mobile Safari/537.36'
        ];
        
        $userAgent = $userAgents[array_rand($userAgents)];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $verifyUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: th-TH,th;q=0.9',
                'User-Agent: ' . $userAgent,
                'Referer: https://gift.truemoney.com/',
                'Cache-Control: no-cache'
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['data']['voucher'])) {
                $voucher = $data['data']['voucher'];
                $amount = floatval($voucher['amount_baht'] ?? $voucher['amount'] ?? 0);
                
                if ($amount > 0) {
                    return [
                        'success' => true,
                        'amount' => $amount,
                        'voucher_data' => $voucher
                    ];
                }
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verification Method 3: Redemption preview endpoint
 */
function tryVerificationMethod3($voucherHash) {
    try {
        $curl = curl_init();
        $verifyUrl = "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}/redeem_preview";
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $verifyUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer: https://gift.truemoney.com/'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['data']['voucher'])) {
                $voucher = $data['data']['voucher'];
                $amount = floatval($voucher['amount_baht'] ?? $voucher['amount'] ?? 0);
                
                if ($amount > 0) {
                    return [
                        'success' => true,
                        'amount' => $amount,
                        'voucher_data' => $voucher
                    ];
                }
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}
?>
