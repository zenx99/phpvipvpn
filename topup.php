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

// Fetch user credits
$stmt = $db->prepare('SELECT credits FROM users WHERE id = :id');
$stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$credits = $row ? $row['credits'] : 0;

// Initialize variables
$message = '';
$messageType = '';

// Check if Voucher class file exists before including it
$voucherClassExists = file_exists('./src/Voucher.php');

if ($voucherClassExists) {
    require('./src/Voucher.php');
    // This class will only be used if the file exists
    class_exists('M4h45amu7x\Voucher');
}

// Handle form submission for truemoney voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_url']) && $voucherClassExists) {
    $voucherUrl = $_POST['voucher_url'] ?? '';

    // Validate input
    if (empty($voucherUrl)) {
        $messageType = 'error';
        $message = 'กรุณาใส่ลิงก์อั่งเปา';
    } else {
        try {
            // Use admin's number or configurable number for redemption
            $mobileNumber = '0825658423'; // You might want to put this in a config file
            
            $voucher = new Voucher($mobileNumber, $voucherUrl);
            
            // First verify the voucher
            $verification = $voucher->verify();
            
            if (isset($verification['status']['code']) && $verification['status']['code'] === "VOUCHER_NOT_FOUND") {
                $messageType = 'error';
                $message = 'ลิงก์อั่งเปาไม่ถูกต้อง หรือถูกใช้ไปแล้ว';
            } else if (isset($verification['data']['voucher'])) {
                // Voucher is valid, proceed to redeem
                $redemption = $voucher->redeem();
                
                if (isset($redemption['status']['code']) && $redemption['status']['code'] === "SUCCESS") {
                    // Successful redemption
                    $amount = $redemption['data']['voucher']['amount_baht'];
                    $creditRate = 1; // 1 credit per 1 baht
                    $creditsToAdd = $amount * $creditRate;
                    
                    // Update user credits in database
                    $updateStmt = $db->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');
                    $updateStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                    $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                    $updateStmt->execute();
                    
                    // Record the transaction
                    $transactionStmt = $db->prepare('INSERT INTO topup_history (user_id, amount, credits, method, reference) VALUES (:user_id, :amount, :credits, :method, :reference)');
                    $transactionStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $transactionStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                    $transactionStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                    $transactionStmt->bindValue(':method', 'truemoney', SQLITE3_TEXT);
                    $transactionStmt->bindValue(':reference', $redemption['data']['voucher']['voucher_id'], SQLITE3_TEXT);
                    $transactionStmt->execute();
                    
                    $messageType = 'success';
                    $message = "เติมเงินสำเร็จ คุณได้รับ {$creditsToAdd} เครดิต";
                    
                    // Update credits for display
                    $credits += $creditsToAdd;
                } else {
                    // Failed redemption
                    $messageType = 'error';
                    $message = 'ไม่สามารถเติมเงินได้ อั่งเปาอาจถูกใช้ไปแล้วหรือหมดอายุ';
                }
            } else {
                $messageType = 'error';
                $message = 'ไม่สามารถตรวจสอบอั่งเปาได้ โปรดลองอีกครั้งในภายหลัง';
            }
        } catch (Exception $e) {
            $messageType = 'error';
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// Handle direct TrueMoney Wallet ang pao link verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_angpao_link']) && isset($_POST['angpao_link'])) {
    $angpaoLink = $_POST['angpao_link'] ?? '';
    
    // Validate input
    if (empty($angpaoLink)) {
        $messageType = 'error';
        $message = 'กรุณาใส่ลิงก์ซองอั่งเปา';
    } else {
        // Check if the link is valid - support multiple TrueMoney gift link formats
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
            $messageType = 'error';
            $message = 'ลิงก์ซองอั่งเปาไม่ถูกต้อง กรุณาตรวจสอบลิงก์อีกครั้ง';
        } else {
            // Initialize cURL with headers that mimic a real browser request
            $curl = curl_init();
            
            // Try to verify voucher first before attempting redemption
            $verifyUrl = "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}";
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $verifyUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, text/plain, */*',
                    'Accept-Language: th-TH,th;q=0.9,en;q=0.8',
                    'Accept-Encoding: gzip, deflate, br',
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Referer: https://gift.truemoney.com/',
                    'Origin: https://gift.truemoney.com',
                    'DNT: 1',
                    'Connection: keep-alive',
                    'Sec-Fetch-Dest: empty',
                    'Sec-Fetch-Mode: cors',
                    'Sec-Fetch-Site: same-origin'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $verifyResponse = curl_exec($curl);
            $verifyHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            // If verification fails, try the redemption endpoint
            if ($verifyHttpCode !== 200 || !$verifyResponse) {
                // Setup redemption request
                $mobileNumber = '0825658423';
                $postData = json_encode([
                    'mobile' => $mobileNumber,
                    'voucher_hash' => $voucherHash
                ]);
                
                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://gift.truemoney.com/campaign/vouchers/{$voucherHash}/redeem",
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postData,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json, text/plain, */*',
                        'Accept-Language: th-TH,th;q=0.9,en;q=0.8',
                        'Accept-Encoding: gzip, deflate, br',
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Referer: https://gift.truemoney.com/',
                        'Origin: https://gift.truemoney.com',
                        'DNT: 1',
                        'Connection: keep-alive',
                        'Sec-Fetch-Dest: empty',
                        'Sec-Fetch-Mode: cors',
                        'Sec-Fetch-Site: same-origin'
                    ]
                ]);
                
                $response = $verifyResponse = curl_exec($curl);
            } else {
                $response = $verifyResponse;
            }
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($err) {
                $messageType = 'error';
                $message = 'เกิดข้อผิดพลาดในการตรวจสอบอั่งเปา: ' . $err;
            } else {
                $result = json_decode($response, true);
                
                // Log debug information
                error_log("TrueMoney API - HTTP Code: " . $httpCode);
                error_log("TrueMoney API - Response: " . $response);
                error_log("TrueMoney API - Voucher Hash: " . $voucherHash);
                
                // Check for successful response
                if ($result && isset($result['status']) && isset($result['status']['code'])) {
                    $statusCode = $result['status']['code'];
                    
                    if ($statusCode === 'SUCCESS') {
                        // Successful verification and redemption
                        $amount = 0;
                        
                        // Try different response structures
                        if (isset($result['data']['my_ticket']['amount_baht'])) {
                            $amount = floatval($result['data']['my_ticket']['amount_baht']);
                        } elseif (isset($result['data']['voucher']['amount_baht'])) {
                            $amount = floatval($result['data']['voucher']['amount_baht']);
                        } elseif (isset($result['data']['amount'])) {
                            $amount = floatval($result['data']['amount']);
                        }
                        
                        // Check if this transaction has been used before
                        $checkStmt = $db->prepare('SELECT id FROM topup_history WHERE reference = :reference');
                        $checkStmt->bindValue(':reference', $voucherHash, SQLITE3_TEXT);
                        $checkResult = $checkStmt->execute();
                        
                        if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
                            // This transaction has been used
                            $messageType = 'error';
                            $message = 'ซองอั่งเปานี้ถูกใช้งานไปแล้ว';
                        } else if ($amount > 0) {
                            // Calculate credits (1 baht = 1 credit)
                            $creditRate = 1;
                            $creditsToAdd = $amount * $creditRate;
                            
                            // Update user credits
                            $updateStmt = $db->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');
                            $updateStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                            $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                            $updateStmt->execute();
                            
                            // Record transaction
                            $transactionStmt = $db->prepare('INSERT INTO topup_history (user_id, amount, credits, method, reference) VALUES (:user_id, :amount, :credits, :method, :reference)');
                            $transactionStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                            $transactionStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                            $transactionStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                            $transactionStmt->bindValue(':method', 'truemoney_angpao', SQLITE3_TEXT);
                            $transactionStmt->bindValue(':reference', $voucherHash, SQLITE3_TEXT);
                            $transactionStmt->execute();
                            
                            $messageType = 'success';
                            $message = "เติมเงินสำเร็จ คุณได้รับ {$creditsToAdd} เครดิต";
                            
                            // Update credits for display
                            $credits += $creditsToAdd;
                        } else {
                            $messageType = 'error';
                            $message = 'จำนวนเงินในซองไม่ถูกต้อง';
                        }
                    } else {
                        // Check for specific error codes
                        $errorMessage = $result['status']['message'] ?? 'ไม่ทราบสาเหตุ';
                        
                        switch ($statusCode) {
                            case 'VOUCHER_NOT_FOUND':
                                $message = 'ลิงก์อั่งเปาไม่ถูกต้อง หรือถูกใช้ไปแล้ว';
                                break;
                            case 'VOUCHER_EXPIRED':
                                $message = 'อั่งเปาหมดอายุแล้ว';
                                break;
                            case 'VOUCHER_ALREADY_USED':
                            case 'VOUCHER_OUT_OF_STOCK':
                                $message = 'อั่งเปานี้ถูกใช้งานไปแล้ว';
                                break;
                            case 'INVALID_MOBILE':
                                $message = 'เบอร์โทรศัพท์ไม่ถูกต้อง';
                                break;
                            case 'MOBILE_NOT_FOUND':
                                $message = 'ไม่พบเบอร์โทรศัพท์ในระบบ TrueMoney';
                                break;
                            case 'CAMPAIGN_INACTIVE':
                                $message = 'แคมเปญไม่ได้เปิดใช้งาน';
                                break;
                            default:
                                $message = 'ไม่สามารถตรวจสอบอั่งเปาได้: ' . $errorMessage;
                        }
                        $messageType = 'error';
                    }
                } else if ($httpCode == 404) {
                    $messageType = 'error';
                    $message = 'ลิงก์อั่งเปาไม่ถูกต้อง หรือถูกใช้ไปแล้ว';
                } else if ($httpCode >= 500) {
                    $messageType = 'error';
                    $message = 'เซิร์ฟเวอร์ TrueMoney ไม่พร้อมใช้งานขณะนี้ กรุณาลองอีกครั้งในภายหลัง';
                } else {
                    // Failed verification - no valid response
                    $messageType = 'error';
                    $message = 'ไม่สามารถยืนยันการชำระเงินได้ กรุณาตรวจสอบลิงก์และลองอีกครั้ง';
                }
            }
        }
    }
}

// Handle TrueWallet slip verification
if (isset($_POST['verify_tw_slip']) && isset($_FILES['tw_slip'])) {
    $target_dir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_file = $target_dir . basename($_FILES["tw_slip"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["tw_slip"]["tmp_name"]);
    if($check === false) {
        $messageType = 'error';
        $message = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ';
    } 
    // Check file size (limit to 2MB)
    elseif ($_FILES["tw_slip"]["size"] > 2000000) {
        $messageType = 'error';
        $message = 'ขนาดไฟล์เกิน 2MB';
    }
    // Allow only JPG, JPEG, PNG
    elseif($imageFileType != "jpg" && $imageFileType != "jpeg" && $imageFileType != "png") {
        $messageType = 'error';
        $message = 'อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG เท่านั้น';
    } 
    else {
        // Generate unique filename
        $temp_filename = uniqid('slip_', true) . '.' . $imageFileType;
        $target_file = $target_dir . $temp_filename;
        
        if (move_uploaded_file($_FILES["tw_slip"]["tmp_name"], $target_file)) {
            // Initialize cURL
            $curl = curl_init();
            $data = [
                'ClientID-Secret' => '12345668ac5d834ae6dadfb97890:59c3fe615570b9a0f643c112a302e45090a4a7470c725326', // Replace with your actual API key
                'image' => new CURLFile($target_file)
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://thaislip.xncly.xyz/api/v1/slipverify-tw",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            // Delete the temporary file
            unlink($target_file);
            
            if ($err) {
                $messageType = 'error';
                $message = 'เกิดข้อผิดพลาดในการตรวจสอบสลิป: ' . $err;
            } else {
                $result = json_decode($response, true);
                
                if ($result && isset($result['status']) && $result['status'] === true) {
                    // Successful verification
                    $amountStr = $result['result']['amount'];
                    $amount = floatval($amountStr);
                    $transRef = $result['result']['transRef'];
                    
                    // Check if this transaction has been used before
                    $checkStmt = $db->prepare('SELECT id FROM topup_history WHERE reference = :reference');
                    $checkStmt->bindValue(':reference', $transRef, SQLITE3_TEXT);
                    $checkResult = $checkStmt->execute();
                    
                    if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
                        // This transaction has been used
                        $messageType = 'error';
                        $message = 'รายการโอนเงินนี้ถูกใช้งานไปแล้ว';
                    } else {
                        // Calculate credits (1 baht = 1 credit)
                        $creditRate = 1;
                        $creditsToAdd = $amount * $creditRate;
                        
                        // Update user credits
                        $updateStmt = $db->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');
                        $updateStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                        $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                        $updateStmt->execute();
                        
                        // Record transaction
                        $transactionStmt = $db->prepare('INSERT INTO topup_history (user_id, amount, credits, method, reference) VALUES (:user_id, :amount, :credits, :method, :reference)');
                        $transactionStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                        $transactionStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                        $transactionStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                        $transactionStmt->bindValue(':method', 'truewallet_slip', SQLITE3_TEXT);
                        $transactionStmt->bindValue(':reference', $transRef, SQLITE3_TEXT);
                        $transactionStmt->execute();
                        
                        $messageType = 'success';
                        $message = "เติมเงินสำเร็จ คุณได้รับ {$creditsToAdd} เครดิต";
                        
                        // Update credits for display
                        $credits += $creditsToAdd;
                    }
                } else {
                    // Failed verification
                    $messageType = 'error';
                    $message = 'ไม่สามารถตรวจสอบสลิปได้ โปรดตรวจสอบว่าเป็นสลิป TrueWallet ที่ถูกต้อง';
                }
            }
        } else {
            $messageType = 'error';
            $message = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
        }
    }
}

// Handle Bank Slip verification
if (isset($_POST['verify_bank_slip']) && isset($_FILES['bank_slip'])) {
    $target_dir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_file = $target_dir . basename($_FILES["bank_slip"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["bank_slip"]["tmp_name"]);
    if($check === false) {
        $messageType = 'error';
        $message = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ';
    } 
    // Check file size (limit to 2MB)
    elseif ($_FILES["bank_slip"]["size"] > 2000000) {
        $messageType = 'error';
        $message = 'ขนาดไฟล์เกิน 2MB';
    }
    // Allow only JPG, JPEG, PNG
    elseif($imageFileType != "jpg" && $imageFileType != "jpeg" && $imageFileType != "png") {
        $messageType = 'error';
        $message = 'อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG เท่านั้น';
    } 
    else {
        // Generate unique filename
        $temp_filename = uniqid('slip_', true) . '.' . $imageFileType;
        $target_file = $target_dir . $temp_filename;
        
        if (move_uploaded_file($_FILES["bank_slip"]["tmp_name"], $target_file)) {
            // Initialize cURL
            $curl = curl_init();
            $data = [
                'ClientID-Secret' => '1234567890:abcdefg', // Replace with your actual API key
                'image' => new CURLFile($target_file)
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://thaislip.xncly.xyz/api/v1/slipverify-bank",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            // Delete the temporary file
            unlink($target_file);
            
            if ($err) {
                $messageType = 'error';
                $message = 'เกิดข้อผิดพลาดในการตรวจสอบสลิป: ' . $err;
            } else {
                $result = json_decode($response, true);
                
                if ($result && isset($result['status']) && $result['status'] === true) {
                    // Successful verification
                    $amountStr = $result['result']['amount'];
                    $amount = floatval($amountStr);
                    $bankRef = $result['result']['transRef'] ?? $result['result']['accountNumber'] ?? uniqid('bank_', true);
                    $bankName = $result['result']['bankName'] ?? 'ไม่ระบุ';
                    
                    // Check if this transaction has been used before
                    $checkStmt = $db->prepare('SELECT id FROM topup_history WHERE reference = :reference');
                    $checkStmt->bindValue(':reference', $bankRef, SQLITE3_TEXT);
                    $checkResult = $checkStmt->execute();
                    
                    if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
                        // This transaction has been used
                        $messageType = 'error';
                        $message = 'รายการโอนเงินนี้ถูกใช้งานไปแล้ว';
                    } else {
                        // Calculate credits (1 baht = 1 credit)
                        $creditRate = 1;
                        $creditsToAdd = $amount * $creditRate;
                        
                        // Update user credits
                        $updateStmt = $db->prepare('UPDATE users SET credits = credits + :credits WHERE id = :id');
                        $updateStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                        $updateStmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                        $updateStmt->execute();
                        
                        // Record transaction
                        $transactionStmt = $db->prepare('INSERT INTO topup_history (user_id, amount, credits, method, reference) VALUES (:user_id, :amount, :credits, :method, :reference)');
                        $transactionStmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                        $transactionStmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                        $transactionStmt->bindValue(':credits', $creditsToAdd, SQLITE3_INTEGER);
                        $transactionStmt->bindValue(':method', 'bank_slip_' . $bankName, SQLITE3_TEXT);
                        $transactionStmt->bindValue(':reference', $bankRef, SQLITE3_TEXT);
                        $transactionStmt->execute();
                        
                        $messageType = 'success';
                        $message = "เติมเงินสำเร็จ คุณได้รับ {$creditsToAdd} เครดิต";
                        
                        // Update credits for display
                        $credits += $creditsToAdd;
                    }
                } else {
                    // Failed verification
                    $messageType = 'error';
                    $message = 'ไม่สามารถตรวจสอบสลิปได้ โปรดตรวจสอบว่าเป็นสลิปธนาคารที่ถูกต้อง';
                }
            }
        } else {
            $messageType = 'error';
            $message = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
        }
    }
}

// Create topup_history table if it doesn't exist
$db->exec('
    CREATE TABLE IF NOT EXISTS topup_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        amount REAL,
        credits INTEGER,
        method TEXT,
        reference TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
');

$db->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เติมเงิน - VIP VPN</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --border-radius: 20px;
            --border-radius-sm: 12px;
            --border-radius-lg: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px 0;
        }
        
        .topup-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .topup-header {
            background: var(--gradient-primary);
            padding: 30px 25px;
            text-align: center;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            position: relative;
            overflow: hidden;
        }
        
        .topup-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            50% { transform: translate(-50%, -50%) rotate(180deg); }
        }
        
        .topup-header h2 {
            color: white;
            font-size: 28px;
            margin: 0;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }
        
        .topup-header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin-top: 8px;
            font-weight: 400;
            position: relative;
            z-index: 2;
        }
        
        .credit-display {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-top: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
        }
        
        .credit-display .label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .credit-display .amount {
            color: white;
            font-size: 24px;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .topup-content {
            padding: 30px 25px;
        }
        
        .topup-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .topup-method {
            padding: 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .topup-method::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: var(--transition);
        }
        
        .topup-method:hover::before {
            left: 100%;
        }
        
        .topup-method:hover {
            border-color: rgba(102, 126, 234, 0.5);
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .topup-method.active {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .topup-method.active::after {
            content: '✓';
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gradient-primary);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            box-shadow: var(--shadow-sm);
        }
        
        .topup-method .icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .topup-method img {
            height: 45px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        
        .topup-method h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .topup-method p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.4;
        }
        
        .topup-form {
            display: none;
            margin-top: 25px;
            padding: 25px;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .topup-form.active {
            display: block;
            animation: slideInUp 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        @keyframes slideInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #374151;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group label i {
            color: #667eea;
        }
        
        .required-mark {
            color: #ef4444;
            font-weight: 700;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--border-radius-sm);
            background: rgba(255, 255, 255, 0.9);
            color: #1f2937;
            font-size: 16px;
            font-weight: 500;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            background: white;
            transform: translateY(-2px);
        }
        
        .form-group input::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 18px 28px;
            border-radius: var(--border-radius-sm);
            font-weight: 700;
            font-size: 16px;
            transition: var(--transition);
            gap: 12px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: var(--transition);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #4b5563;
            border: 2px solid rgba(102, 126, 234, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary:hover {
            background: white;
            color: #1f2937;
            border-color: rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }
        
        .message {
            padding: 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid;
        }
        
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .angpao-info {
            margin: 25px 0;
            padding: 25px;
            background: rgba(251, 191, 36, 0.1);
            border-left: 4px solid #f59e0b;
            border-radius: var(--border-radius-sm);
            backdrop-filter: blur(10px);
        }
        
        .angpao-info h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #92400e;
            font-size: 18px;
            margin-top: 0;
            font-weight: 700;
        }
        
        .angpao-steps {
            list-style-type: none;
            padding: 0;
            margin: 20px 0 0;
        }
        
        .angpao-steps li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            color: #92400e;
            font-weight: 500;
            line-height: 1.5;
        }
        
        .angpao-steps li i {
            margin-right: 12px;
            margin-top: 3px;
            width: 24px;
            height: 24px;
            background: var(--gradient-warning);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .angpao-example {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: var(--border-radius-sm);
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 14px;
            color: #92400e;
            word-break: break-all;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }
        
        .credit-rates {
            margin-top: 30px;
            padding: 20px;
            background: #f3f4f6;
            border-radius: 12px;
        }
        
        .credit-rates h3 {
            color: #1f2937;
            font-size: 18px;
            margin-top: 0;
        }
        
        .rate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .rate-table th, .rate-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .rate-table th {
            font-weight: 600;
            color: #4b5563;
            background: #f9fafb;
        }
        
        .rate-table td {
            color: #6b7280;
        }
        
        .topup-instructions {
            margin-top: 30px;
        }
        
        
        /* Custom file upload styling */
        input[type="file"] {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }
        
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 180px;
            border: 3px dashed rgba(102, 126, 234, 0.3);
            border-radius: var(--border-radius);
            background: rgba(102, 126, 234, 0.05);
            padding: 25px;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .file-upload-label::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: var(--transition);
        }
        
        .file-upload-label:hover::before {
            opacity: 1;
        }
        
        .file-upload-label:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.6);
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .file-upload-label i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
            transition: var(--transition);
        }
        
        .file-upload-label:hover i {
            transform: scale(1.1);
        }
        
        .file-upload-label span {
            font-size: 18px;
            color: #374151;
            margin-bottom: 8px;
            font-weight: 600;
            text-align: center;
        }
        
        .file-upload-label small {
            font-size: 14px;
            color: #6b7280;
            text-align: center;
            line-height: 1.4;
        }
        
        .file-preview {
            display: none;
            margin-top: 20px;
            text-align: center;
            animation: slideInUp 0.3s ease;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-md);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }
        
        .topup-instructions h3 {
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .topup-instructions h3 i {
            color: #667eea;
        }
        
        .bank-info {
            background: rgba(102, 126, 234, 0.1);
            padding: 25px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            border: 1px solid rgba(102, 126, 234, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .bank-info h4 {
            color: #374151;
            font-size: 18px;
            margin: 0 0 15px 0;
            font-weight: 700;
        }
        
        .bank-info p {
            margin: 8px 0;
            color: #4b5563;
            font-weight: 500;
        }
        
        .bank-info .account-number {
            font-family: 'Fira Code', monospace;
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
            background: rgba(255, 255, 255, 0.7);
            padding: 10px 15px;
            border-radius: var(--border-radius-sm);
            display: inline-block;
            margin-top: 5px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px 0;
            }
            
            .topup-container {
                margin: 0 15px;
                max-width: none;
            }
            
            .topup-header {
                padding: 25px 20px;
            }
            
            .topup-header h2 {
                font-size: 24px;
            }
            
            .topup-content {
                padding: 25px 20px;
            }
            
            .topup-methods {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .topup-method {
                padding: 18px;
            }
            
            .topup-method h3 {
                font-size: 16px;
            }
            
            .topup-form {
                padding: 20px;
            }
            
            .form-group input {
                padding: 14px 18px;
                font-size: 16px;
            }
            
            .btn {
                padding: 16px 24px;
                font-size: 16px;
            }
            
            .file-upload-label {
                height: 150px;
                padding: 20px;
            }
            
            .file-upload-label i {
                font-size: 36px;
            }
            
            .file-upload-label span {
                font-size: 16px;
            }
            
            .angpao-steps li {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .angpao-steps li i {
                margin-right: 0;
                margin-bottom: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .topup-container {
                margin: 0 10px;
            }
            
            .topup-header {
                padding: 20px 15px;
            }
            
            .topup-content {
                padding: 20px 15px;
            }
            
            .topup-header h2 {
                font-size: 22px;
            }
            
            .credit-display .amount {
                font-size: 20px;
            }
            
            /* Navigation responsive */
            .nav-container {
                flex-direction: column !important;
                gap: 10px !important;
                padding: 12px 15px !important;
            }
            
            .nav-container > div:last-child {
                flex-direction: column;
                gap: 8px;
                align-items: center;
            }
            
            .nav-container > div:last-child > div {
                font-size: 14px;
            }
        }
        
        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #f3f4f6;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Voucher verification states */
        #voucher-info {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn:disabled {
            background: #9ca3af !important;
            cursor: not-allowed !important;
            transform: none !important;
        }
        
        .btn:disabled::before {
            display: none !important;
        }
    </style>
</head>
<body>
    <!-- Simple Navigation -->
    <div style="position: fixed; top: 0; left: 0; right: 0; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 15px 20px; z-index: 1000; border-bottom: 1px solid rgba(102, 126, 234, 0.2);">
        <div class="nav-container" style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; background: var(--gradient-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">V</div>
                <h1 style="margin: 0; color: #1f2937; font-size: 20px; font-weight: 700;">VIP VPN</h1>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="background: rgba(102, 126, 234, 0.1); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: #667eea;">
                    <i class="fas fa-coins"></i> <?php echo number_format($credits); ?> เครดิต
                </div>
                <div style="color: #4b5563; font-weight: 500;">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
                </div>
            </div>
        </div>
    </div>

    <div style="padding-top: 100px;">

    <div class="topup-container">
        <div class="topup-header">
            <h2>💰 เติมเงิน</h2>
            <div class="subtitle">เลือกวิธีการเติมเงินที่สะดวกสำหรับคุณ</div>
            <div class="credit-display">
                <div class="label">เครดิตปัจจุบัน</div>
                <div class="amount"><?php echo number_format($credits); ?> บาท</div>
            </div>
        </div>
        
        <div class="topup-content">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>-message">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="topup-methods">
                <?php if ($voucherClassExists): ?>
                <div class="topup-method <?php echo $voucherClassExists ? 'active' : ''; ?>" data-method="truemoney">
                    <div class="icon"><i class="fas fa-gift"></i></div>
                    <h3>TrueMoney Wallet</h3>
                    <p>เติมเงินด้วยอั่งเปาทรูมันนี่</p>
                </div>
                <?php endif; ?>
                <div class="topup-method" data-method="angpao_link">
                    <div class="icon"><i class="fas fa-link"></i></div>
                    <h3>ลิงก์อั่งเปาวอเล็ท</h3>
                    <p>เติมเงินด้วยลิงก์อั่งเปาวอเล็ทโดยตรง</p>
                </div>
                <div class="topup-method <?php echo !$voucherClassExists ? 'active' : ''; ?>" data-method="tw_slip">
                    <div class="icon"><i class="fas fa-receipt"></i></div>
                    <h3>TrueWallet Slip</h3>
                    <p>เติมเงินผ่านเช็คสลิปวอเล็ท</p>
                </div>
                <div class="topup-method" data-method="bank">
                    <div class="icon"><i class="fas fa-university"></i></div>
                    <h3>โอนเงินผ่านธนาคาร</h3>
                    <p>เติมเงินด้วยการโอนเงินผ่านธนาคาร</p>
                </div>
            </div>

        <!-- TrueMoney Form -->
        <?php if ($voucherClassExists): ?>
        <div class="topup-form <?php echo $voucherClassExists ? 'active' : ''; ?>" id="truemoney-form">
            <div class="angpao-info">
                <h3><i class="fas fa-gift"></i> วิธีการส่งอั่งเปาทรูมันนี่</h3>
                <ul class="angpao-steps">
                    <li><i class="fas fa-1"></i> เปิดแอป TrueMoney Wallet</li>
                    <li><i class="fas fa-2"></i> เลือก "ส่งของขวัญ" หรือ "อั่งเปา"</li>
                    <li><i class="fas fa-3"></i> เลือก "ส่งอั่งเปาให้เพื่อน (ลิงก์)"</li>
                    <li><i class="fas fa-4"></i> ใส่จำนวนเงินที่ต้องการเติม</li>
                    <li><i class="fas fa-5"></i> คัดลอกลิงก์อั่งเปาที่ได้มาวางในฟอร์มด้านล่าง</li>
                </ul>
                <div class="angpao-example">
                    ตัวอย่างลิงก์อั่งเปา: https://gift.truemoney.com/campaign?v=abcdefgh
                </div>
            </div>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="truemoney-voucher-form">
                <div class="form-group">
                    <label for="voucher_url"><i class="fas fa-link"></i> ลิงก์อั่งเปาทรูมันนี่ <span class="required-mark">*</span></label>
                    <div style="position: relative;">
                        <input type="text" id="voucher_url" name="voucher_url" placeholder="https://gift.truemoney.com/campaign/?v=..." required>
                        <div id="voucher-validation" style="margin-top: 8px; font-size: 14px; display: none;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                            <span style="color: #10b981;">ลิงก์ถูกต้อง</span>
                        </div>
                        <div id="voucher-error" style="margin-top: 8px; font-size: 14px; display: none;">
                            <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                            <span style="color: #ef4444;" id="voucher-error-text">ลิงก์ไม่ถูกต้อง</span>
                        </div>
                    </div>
                </div>
                
                <!-- Voucher Info Display -->
                <div id="voucher-info-display" style="display: none; margin: 20px 0; padding: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <h4 style="color: #059669; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-gift"></i> ข้อมูลอั่งเปา
                    </h4>
                    <div style="display: grid; gap: 8px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #6b7280;">จำนวนเงิน:</span>
                            <span id="voucher-amount-display" style="font-weight: 600; color: #059669;"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #6b7280;">เครดิตที่จะได้รับ:</span>
                            <span id="voucher-credits-display" style="font-weight: 600; color: #059669;"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #6b7280;">สถานะ:</span>
                            <span id="voucher-status-display" style="font-weight: 600; color: #059669;">พร้อมใช้งาน</span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="voucher-submit-btn" style="width: 100%;" disabled>
                    <i class="fas fa-check-circle"></i> ยืนยันการเติมเงิน
                </button>
                
                <!-- Loading State -->
                <div id="voucher-verification-loading" style="display: none; text-align: center; margin-top: 15px;">
                    <div style="display: inline-flex; align-items: center; gap: 8px; color: #667eea; font-weight: 500;">
                        <div class="spinner"></div>
                        <span>กำลังตรวจสอบอั่งเปา...</span>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
            <!-- TrueWallet Angpao Link Form -->
            <div class="topup-form" id="angpao_link-form">
                <div class="angpao-info">
                    <h3><i class="fas fa-gift"></i> วิธีการเติมเงินด้วยลิงก์อั่งเปาวอเล็ท</h3>
                    <ul class="angpao-steps">
                        <li><i>1</i> เปิดแอป TrueMoney Wallet</li>
                        <li><i>2</i> เลือก "ส่งของขวัญ" หรือ "อั่งเปา"</li>
                        <li><i>3</i> เลือก "ส่งอั่งเปาให้เพื่อน (ลิงก์)"</li>
                        <li><i>4</i> ใส่จำนวนเงินที่ต้องการเติม</li>
                        <li><i>5</i> คัดลอกลิงก์อั่งเปาที่ได้มาวางในฟอร์มด้านล่าง</li>
                    </ul>
                    <div class="angpao-example">
                        ตัวอย่างลิงก์อั่งเปา: https://gift.truemoney.com/campaign?v=0196fe0966d57bc8ae5789f50f9747889a5
                    </div>
                    <div class="angpao-example" style="color: #FF6F00; font-weight: bold;">
                        หมายเหตุ: 1 บาท = 1 เครดิต, ตรวจสอบและยืนยันการเติมเงินอัตโนมัติ
                    </div>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="angpao-form">
                    <div class="form-group">
                        <label for="angpao_link"><i class="fas fa-link"></i> ลิงก์อั่งเปาวอเล็ท <span class="required-mark">*</span></label>
                        <div style="position: relative;">
                            <input type="text" id="angpao_link" name="angpao_link" placeholder="https://gift.truemoney.com/campaign/?v=..." required>
                            <div id="link-validation" style="margin-top: 8px; font-size: 14px; display: none;">
                                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                <span style="color: #10b981;">ลิงก์ถูกต้อง</span>
                            </div>
                            <div id="link-error" style="margin-top: 8px; font-size: 14px; display: none;">
                                <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                                <span style="color: #ef4444;" id="link-error-text">ลิงก์ไม่ถูกต้อง</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Voucher Info Display -->
                    <div id="voucher-info" style="display: none; margin: 20px 0; padding: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.3);">
                        <h4 style="color: #059669; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-gift"></i> ข้อมูลอั่งเปา
                        </h4>
                        <div style="display: grid; gap: 8px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280;">จำนวนเงิน:</span>
                                <span id="voucher-amount" style="font-weight: 600; color: #059669;"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280;">เครดิตที่จะได้รับ:</span>
                                <span id="voucher-credits" style="font-weight: 600; color: #059669;"></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280;">สถานะ:</span>
                                <span id="voucher-status" style="font-weight: 600; color: #059669;">พร้อมใช้งาน</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_angpao_link" class="btn btn-primary" id="verify-btn" style="width: 100%;" disabled>
                        <i class="fas fa-check-circle"></i> ยืนยันการเติมเงิน
                    </button>
                    
                    <!-- Loading State -->
                    <div id="verification-loading" style="display: none; text-align: center; margin-top: 15px;">
                        <div style="display: inline-flex; align-items: center; gap: 8px; color: #667eea; font-weight: 500;">
                            <div class="spinner"></div>
                            <span>กำลังตรวจสอบอั่งเปา...</span>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- TrueWallet Slip Form -->
            <div class="topup-form <?php echo !$voucherClassExists ? 'active' : ''; ?>" id="tw_slip-form">
                <div class="topup-instructions">
                    <h3><i class="fas fa-receipt"></i> วิธีการเติมเงินผ่านสลิป TrueWallet</h3>
                    <ul class="angpao-steps">
                        <li><i>1</i> โอนเงินผ่าน TrueWallet ไปยังเบอร์ 082-565-8423</li>
                        <li><i>2</i> บันทึกภาพสลิปการโอนเงิน</li>
                        <li><i>3</i> อัปโหลดสลิปในฟอร์มด้านล่าง</li>
                        <li><i>4</i> รอระบบตรวจสอบและเติมเครดิตให้โดยอัตโนมัติ</li>
                    </ul>
                    <div class="angpao-example" style="color: #FF6F00; font-weight: bold;">
                        หมายเหตุ: แต่ละสลิปสามารถใช้ได้เพียงครั้งเดียวเท่านั้น
                    </div>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-upload"></i> อัปโหลดสลิป TrueWallet <span class="required-mark">*</span></label>
                        <label for="tw_slip" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>คลิกเพื่อเลือกไฟล์หรือลากไฟล์มาวางที่นี่</span>
                            <small>รองรับไฟล์ JPG, JPEG, PNG ขนาดไม่เกิน 2MB</small>
                        </label>
                        <input type="file" id="tw_slip" name="tw_slip" accept="image/jpeg,image/png" required>
                        <div class="file-preview">
                            <img id="slip-preview" src="#" alt="ตัวอย่างสลิป">
                        </div>
                    </div>
                    <button type="submit" name="verify_tw_slip" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-check-circle"></i> ตรวจสอบและเติมเงิน
                    </button>
                </form>
            </div>

            <!-- Bank Transfer Form -->
            <div class="topup-form" id="bank-form">
                <div class="topup-instructions">
                    <h3><i class="fas fa-university"></i> วิธีการโอนเงินผ่านธนาคาร</h3>
                    <ul class="angpao-steps">
                        <li><i>1</i> โอนเงินไปยังบัญชีด้านล่าง</li>
                        <li><i>2</i> บันทึกภาพสลิปการโอนเงิน</li>
                        <li><i>3</i> อัปโหลดสลิปในฟอร์มด้านล่าง</li>
                        <li><i>4</i> รอระบบตรวจสอบและเติมเครดิตให้โดยอัตโนมัติ</li>
                    </ul>
                    
                    <div class="bank-info">
                        <h4>📋 ข้อมูลบัญชีสำหรับโอนเงิน</h4>
                        <p><strong>ธนาคาร:</strong> กสิกรไทย (KBANK)</p>
                        <p><strong>ชื่อบัญชี:</strong> บริษัท วีไอพี วีพีเอ็น จำกัด</p>
                        <p><strong>เลขที่บัญชี:</strong> <span class="account-number">123-4-56789-0</span></p>
                        
                        <div class="angpao-example" style="color: #FF6F00; font-weight: bold; margin-top: 15px;">
                            หมายเหตุ: แต่ละสลิปสามารถใช้ได้เพียงครั้งเดียวเท่านั้น
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-upload"></i> อัปโหลดสลิปธนาคาร <span class="required-mark">*</span></label>
                        <label for="bank_slip" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>คลิกเพื่อเลือกไฟล์หรือลากไฟล์มาวางที่นี่</span>
                            <small>รองรับไฟล์ JPG, JPEG, PNG ขนาดไม่เกิน 2MB</small>
                        </label>
                        <input type="file" id="bank_slip" name="bank_slip" accept="image/jpeg,image/png" required>
                        <div class="file-preview">
                            <img id="bank-slip-preview" src="#" alt="ตัวอย่างสลิป">
                        </div>
                    </div>
                    <button type="submit" name="verify_bank_slip" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-check-circle"></i> ตรวจสอบและเติมเงิน
                    </button>
                </form>
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> กลับสู่หน้าหลัก
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method selection
            const methodOptions = document.querySelectorAll('.topup-method');
            const methodForms = document.querySelectorAll('.topup-form');
            
            methodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const selectedMethod = this.dataset.method;
                    
                    // Update active method styling
                    methodOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding form
                    methodForms.forEach(form => {
                        form.classList.remove('active');
                        if (form.id === selectedMethod + '-form') {
                            form.classList.add('active');
                        }
                    });
                });
            });
            
            // Function to setup file upload preview and drag-drop functionality
            function setupFileUpload(inputId, previewImgId) {
                const inputElement = document.getElementById(inputId);
                if (!inputElement) return;
                
                const filePreview = inputElement.parentElement.querySelector('.file-preview');
                const previewImg = document.getElementById(previewImgId);
                const fileUploadLabel = inputElement.parentElement.querySelector('.file-upload-label');
                
                inputElement.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        // Check file type
                        const fileType = file.type;
                        if (fileType !== 'image/jpeg' && fileType !== 'image/png') {
                            alert('รองรับเฉพาะไฟล์ JPG, JPEG และ PNG เท่านั้น');
                            this.value = '';
                            return;
                        }
                        
                        // Check file size (max 2MB)
                        if (file.size > 2 * 1024 * 1024) {
                            alert('ขนาดไฟล์ต้องไม่เกิน 2MB');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.setAttribute('src', e.target.result);
                            filePreview.style.display = 'block';
                            fileUploadLabel.style.borderColor = '#10b981';
                            fileUploadLabel.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                            fileUploadLabel.querySelector('i').style.color = '#10b981';
                            fileUploadLabel.querySelector('span').textContent = file.name;
                        };
                        reader.readAsDataURL(file);
                    }
                });
                
                // Drag and drop functionality
                const dropArea = fileUploadLabel;
                
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, preventDefaults, false);
                });
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropArea.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, unhighlight, false);
                });
                
                dropArea.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    inputElement.files = files;
                    
                    // Trigger change event
                    const event = new Event('change', { bubbles: true });
                    inputElement.dispatchEvent(event);
                }, false);
            }
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            function highlight() {
                this.style.backgroundColor = 'rgba(165, 180, 252, 0.3)';
                this.style.borderColor = '#6366f1';
            }

            function unhighlight() {
                this.style.backgroundColor = 'rgba(165, 180, 252, 0.1)';
                this.style.borderColor = '#a5b4fc';
            }
            
            // Setup file upload for TrueWallet slip
            setupFileUpload('tw_slip', 'slip-preview');
            
            // Setup file upload for Bank slip
            setupFileUpload('bank_slip', 'bank-slip-preview');

            // User menu dropdown with animation
            // (Removed as we simplified the header)
            
            // Form validation and enhanced UX for TrueMoney Gift
            const truemoneyForm = document.querySelector('#truemoney-voucher-form');
            const voucherUrlInput = document.getElementById('voucher_url');
            const voucherSubmitBtn = document.getElementById('voucher-submit-btn');
            const voucherValidation = document.getElementById('voucher-validation');
            const voucherError = document.getElementById('voucher-error');
            const voucherErrorText = document.getElementById('voucher-error-text');
            const voucherInfoDisplay = document.getElementById('voucher-info-display');
            const voucherVerificationLoading = document.getElementById('voucher-verification-loading');
            
            let voucherVerificationTimeout;
            let currentVoucherDataTrueMoney = null;
            
            if (voucherUrlInput) {
                // Real-time link validation
                voucherUrlInput.addEventListener('input', function(e) {
                    const link = e.target.value.trim();
                    
                    // Clear previous timeout
                    if (voucherVerificationTimeout) {
                        clearTimeout(voucherVerificationTimeout);
                    }
                    
                    // Hide all states initially
                    hideAllVoucherStates();
                    
                    if (link.length === 0) {
                        voucherSubmitBtn.disabled = true;
                        return;
                    }
                    
                    // Validate link format
                    const isValidFormat = validateVoucherLinkFormat(link);
                    
                    if (!isValidFormat) {
                        showVoucherError('รูปแบบลิงก์ไม่ถูกต้อง กรุณาใช้ลิงก์จาก TrueMoney Wallet');
                        voucherSubmitBtn.disabled = true;
                        return;
                    }
                    
                    // Show format validation success
                    voucherValidation.style.display = 'block';
                    
                    // Extract voucher hash for display
                    const voucherHash = extractVoucherHashFromLink(link);
                    if (voucherHash) {
                        // Show that link is valid but API verification is disabled
                        showVoucherManualMode();
                    }
                });
                
                // Form submission handling
                truemoneyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const link = voucherUrlInput.value.trim();
                    
                    if (!validateVoucherLinkFormat(link)) {
                        showVoucherError('รูปแบบลิงก์ไม่ถูกต้อง');
                        return;
                    }
                    
                    // Show loading state
                    voucherSubmitBtn.innerHTML = '<div class="spinner" style="width: 16px; height: 16px; border-width: 2px; margin-right: 8px;"></div> กำลังประมวลผล...';
                    voucherSubmitBtn.disabled = true;
                    
                    // Submit the form
                    setTimeout(() => {
                        this.submit();
                    }, 500);
                });
            }
            
            function hideAllVoucherStates() {
                voucherValidation.style.display = 'none';
                voucherError.style.display = 'none';
                voucherInfoDisplay.style.display = 'none';
                voucherVerificationLoading.style.display = 'none';
                currentVoucherDataTrueMoney = null;
            }
            
            function showVoucherError(message) {
                voucherError.style.display = 'block';
                voucherErrorText.textContent = message;
                voucherSubmitBtn.disabled = true;
            }
            
            function validateVoucherLinkFormat(link) {
                // Multiple patterns to support different TrueMoney gift link formats
                const patterns = [
                    /^https:\/\/gift\.truemoney\.com\/campaign\/?\?v=([0-9A-Fa-f]{18,50})$/,
                    /^https:\/\/gift\.truemoney\.com\/campaign\?v=([0-9A-Fa-f]{18,50})$/,
                    /^https:\/\/gift\.truemoney\.com\/campaign\/\?v=([0-9A-Fa-f]{18,50})$/
                ];
                
                return patterns.some(pattern => pattern.test(link));
            }
            
            function extractVoucherHashFromLink(link) {
                const hashMatch = link.match(/v=([0-9A-Fa-f]{18,50})/);
                return hashMatch ? hashMatch[1] : null;
            }
            
            function showVoucherManualMode() {
                // Show info that verification will be done manually
                voucherInfoDisplay.style.display = 'block';
                document.getElementById('voucher-amount-display').textContent = 'จะตรวจสอบเมื่อส่งฟอร์ม';
                document.getElementById('voucher-credits-display').textContent = 'จะตรวจสอบเมื่อส่งฟอร์ม';
                document.getElementById('voucher-status-display').textContent = 'รอการตรวจสอบ';
                document.getElementById('voucher-status-display').style.color = '#f59e0b';
                
                // Enable submit button
                voucherSubmitBtn.disabled = false;
                voucherSubmitBtn.innerHTML = '<i class="fas fa-check-circle"></i> ยืนยันการเติมเงิน';
            }
            
            // Form validation and enhanced UX for TrueMoney Angpao Link
            const angpaoLinkForm = document.querySelector('#angpao-form');
            const angpaoLinkInput = document.getElementById('angpao_link');
            const verifyBtn = document.getElementById('verify-btn');
            const linkValidation = document.getElementById('link-validation');
            const linkError = document.getElementById('link-error');
            const linkErrorText = document.getElementById('link-error-text');
            const voucherInfo = document.getElementById('voucher-info');
            const verificationLoading = document.getElementById('verification-loading');
            
            let verificationTimeout;
            let currentVoucherData = null;
            
            if (angpaoLinkInput) {
                // Real-time link validation and verification
                angpaoLinkInput.addEventListener('input', function(e) {
                    const link = e.target.value.trim();
                    
                    // Clear previous timeout
                    if (verificationTimeout) {
                        clearTimeout(verificationTimeout);
                    }
                    
                    // Hide all states initially
                    hideAllStates();
                    
                    if (link.length === 0) {
                        verifyBtn.disabled = true;
                        return;
                    }
                    
                    // Validate link format
                    const isValidFormat = validateLinkFormat(link);
                    
                    if (!isValidFormat) {
                        showError('รูปแบบลิงก์ไม่ถูกต้อง กรุณาใช้ลิงก์จาก TrueMoney Wallet');
                        verifyBtn.disabled = true;
                        return;
                    }
                    
                    // Show format validation success
                    linkValidation.style.display = 'block';
                    
                    // Debounce API call
                    verificationTimeout = setTimeout(() => {
                        verifyVoucherRealTime(link);
                    }, 800);
                });
                
                // Form submission handling
                angpaoLinkForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const link = angpaoLinkInput.value.trim();
                    
                    if (!validateLinkFormat(link)) {
                        showError('รูปแบบลิงก์ไม่ถูกต้อง');
                        return;
                    }
                    
                    if (!currentVoucherData) {
                        showError('กรุณารอให้ระบบตรวจสอบอั่งเปาเสร็จสิ้น');
                        return;
                    }
                    
                    // Show loading state
                    verifyBtn.innerHTML = '<div class="spinner" style="width: 16px; height: 16px; border-width: 2px; margin-right: 8px;"></div> กำลังประมวลผล...';
                    verifyBtn.disabled = true;
                    
                    // Submit the form with verified data
                    setTimeout(() => {
                        this.submit();
                    }, 500);
                });
            }
            
            function hideAllStates() {
                linkValidation.style.display = 'none';
                linkError.style.display = 'none';
                voucherInfo.style.display = 'none';
                verificationLoading.style.display = 'none';
                currentVoucherData = null;
            }
            
            function showError(message) {
                linkError.style.display = 'block';
                linkErrorText.textContent = message;
                verifyBtn.disabled = true;
            }
            
            function validateLinkFormat(link) {
                // Multiple patterns to support different TrueMoney gift link formats
                const patterns = [
                    /^https:\/\/gift\.truemoney\.com\/campaign\/?\?v=([0-9A-Fa-f]{18,50})$/,
                    /^https:\/\/gift\.truemoney\.com\/campaign\?v=([0-9A-Fa-f]{18,50})$/,
                    /^https:\/\/gift\.truemoney\.com\/campaign\/\?v=([0-9A-Fa-f]{18,50})$/
                ];
                
                return patterns.some(pattern => pattern.test(link));
            }
            
            function extractVoucherHash(link) {
                const hashMatch = link.match(/v=([0-9A-Fa-f]{18,50})/);
                return hashMatch ? hashMatch[1] : null;
            }
            
            async function verifyVoucherRealTime(link) {
                try {
                    // Show loading
                    verificationLoading.style.display = 'block';
                    linkValidation.style.display = 'none';
                    
                    const voucherHash = extractVoucherHash(link);
                    if (!voucherHash) {
                        throw new Error('ไม่สามารถดึงรหัสอั่งเปาได้');
                    }
                    
                    // Try alternative verification endpoint first
                    try {
                        const formData = new FormData();
                        formData.append('voucher_hash', voucherHash);
                        
                        const response = await fetch('verify_voucher_alt.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            
                            if (data.success && data.voucher) {
                                showVoucherInfo(data.voucher);
                                return;
                            } else if (data.allow_manual) {
                                showWarningAndAllowSubmission();
                                return;
                            }
                        }
                    } catch (error) {
                        console.log('Alternative verification failed:', error.message);
                    }
                    
                    // Fallback: Show warning but allow manual processing
                    showWarningAndAllowSubmission();
                    
                } catch (error) {
                    console.error('Verification failed:', error);
                    showWarningAndAllowSubmission();
                } finally {
                    verificationLoading.style.display = 'none';
                }
            }
            
            async function verifyVoucherClientSide(voucherHash) {
                try {
                    // Client-side verification using CORS proxy or direct API call
                    const verifyUrl = `https://gift.truemoney.com/campaign/vouchers/${voucherHash}`;
                    
                    // Try to use a CORS proxy for client-side verification
                    const proxyUrls = [
                        `https://api.allorigins.win/get?url=${encodeURIComponent(verifyUrl)}`,
                        `https://cors-anywhere.herokuapp.com/${verifyUrl}`,
                        verifyUrl // Direct call (might fail due to CORS)
                    ];
                    
                    let lastError;
                    
                    for (const url of proxyUrls) {
                        try {
                            const response = await fetch(url, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json, text/plain, */*',
                                    'Accept-Language': 'th-TH,th;q=0.9,en;q=0.8',
                                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                                }
                            });
                            
                            if (!response.ok) continue;
                            
                            let data;
                            if (url.includes('allorigins.win')) {
                                const proxyData = await response.json();
                                data = JSON.parse(proxyData.contents);
                            } else {
                                data = await response.json();
                            }
                            
                            if (data && data.data && data.data.voucher) {
                                const voucher = data.data.voucher;
                                const voucherInfo = {
                                    amount: voucher.amount_baht || voucher.amount || 0,
                                    status: voucher.status || 'ACTIVE',
                                    voucher_id: voucher.voucher_id || voucherHash
                                };
                                
                                if (voucherInfo.amount > 0) {
                                    showVoucherInfo(voucherInfo);
                                    return;
                                }
                            }
                            
                        } catch (err) {
                            lastError = err;
                            console.log(`Failed with ${url}:`, err.message);
                            continue;
                        }
                    }
                    
                    // If all methods fail, show a warning but allow submission
                    showWarningAndAllowSubmission();
                    
                } catch (error) {
                    console.error('Client-side verification failed:', error);
                    showWarningAndAllowSubmission();
                }
            }
            
            function showVoucherInfo(voucher) {
                currentVoucherData = voucher;
                
                // Update voucher info display
                document.getElementById('voucher-amount').textContent = `${voucher.amount} บาท`;
                document.getElementById('voucher-credits').textContent = `${voucher.amount} เครดิต`;
                document.getElementById('voucher-status').textContent = voucher.status === 'ACTIVE' ? 'พร้อมใช้งาน' : voucher.status;
                
                // Show voucher info
                voucherInfo.style.display = 'block';
                linkValidation.style.display = 'block';
                
                // Enable submit button
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> ยืนยันการเติมเงิน';
            }
            
            function showWarningAndAllowSubmission() {
                // Show a warning but still allow submission for manual processing
                linkError.style.display = 'block';
                linkErrorText.textContent = 'ไม่สามารถตรวจสอบอั่งเปาอัตโนมัติได้ ระบบจะตรวจสอบด้วยตนเองหลังจากส่งฟอร์ม';
                linkErrorText.style.color = '#f59e0b'; // Warning color instead of error
                
                // Still allow submission
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ส่งเพื่อตรวจสอบด้วยตนเอง';
            }
            
            // Set initial active tab based on available methods
            if (!document.querySelector('#truemoney-form')) {
                const twSlipTab = document.querySelector('[data-method="tw_slip"]');
                if (twSlipTab) {
                    // Simulate click to activate the TW slip tab if it's not already active
                    if (!twSlipTab.classList.contains('active')) {
                        twSlipTab.click();
                    }
                }
            }
            
            // Animated form fields
            const formInputs = document.querySelectorAll('input, select');
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.position = 'relative';
                    this.parentElement.style.zIndex = '1';
                    this.style.boxShadow = '0 0 0 3px rgba(139, 92, 246, 0.15)';
                    this.style.borderColor = '#a5b4fc';
                    this.style.background = 'white';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.zIndex = '';
                    this.style.boxShadow = '';
                    if (!this.value) {
                        this.style.borderColor = '#e5e7eb';
                        this.style.background = '#f9fafb';
                    }
                });
            });
            
            // Hide alert message after 5 seconds
            const messageDiv = document.querySelector('.message');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.5s';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => {
                        messageDiv.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
