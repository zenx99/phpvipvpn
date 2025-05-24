<?php
// Test TrueMoney API directly
header('Content-Type: text/html; charset=utf-8');

echo "<h2>ทดสอบ TrueMoney API</h2>";

// Test voucher hash - ใช้ hash ที่คุณต้องการทดสอบ
$testHash = '0196fe0966d57bc8ae5789f50f9747889a5'; // แก้ไขเป็น hash จริงที่ต้องการทดสอบ

echo "<p><strong>กำลังทดสอบ Hash:</strong> $testHash</p>";

// Test 1: Direct API call
echo "<h3>1. ทดสอบ API โดยตรง</h3>";
$curl = curl_init();

$verifyUrl = "https://gift.truemoney.com/campaign/vouchers/{$testHash}";
echo "<p><strong>URL:</strong> $verifyUrl</p>";

curl_setopt_array($curl, [
    CURLOPT_URL => $verifyUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json, text/plain, */*',
        'Accept-Language: th-TH,th;q=0.9,en;q=0.8',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Referer: https://gift.truemoney.com/',
        'Origin: https://gift.truemoney.com'
    ],
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_VERBOSE => true
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
if ($curlError) {
    echo "<p><strong>cURL Error:</strong> $curlError</p>";
}

echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

curl_close($curl);

// Test 2: Alternative endpoint
echo "<h3>2. ทดสอบ Alternative Endpoint</h3>";
$curl2 = curl_init();

$previewUrl = "https://gift.truemoney.com/campaign/vouchers/{$testHash}/redeem_preview";
echo "<p><strong>URL:</strong> $previewUrl</p>";

curl_setopt_array($curl2, [
    CURLOPT_URL => $previewUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);

$response2 = curl_exec($curl2);
$httpCode2 = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
$curlError2 = curl_error($curl2);

echo "<p><strong>HTTP Code:</strong> $httpCode2</p>";
if ($curlError2) {
    echo "<p><strong>cURL Error:</strong> $curlError2</p>";
}

echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response2) . "</pre>";

curl_close($curl2);

// Test 3: Test our verify_voucher.php
echo "<h3>3. ทดสอบ verify_voucher.php</h3>";
if (file_exists('verify_voucher.php')) {
    echo "<p>ไฟล์ verify_voucher.php พบแล้ว</p>";
    
    // Simulate POST request
    $_POST['voucher_hash'] = $testHash;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    include 'verify_voucher.php';
    $verifyResponse = ob_get_clean();
    
    echo "<p><strong>verify_voucher.php Response:</strong></p>";
    echo "<pre>" . htmlspecialchars($verifyResponse) . "</pre>";
} else {
    echo "<p style='color: red;'>ไฟล์ verify_voucher.php ไม่พบ</p>";
}

// Test 4: Check database and form processing
echo "<h3>4. ทดสอบการประมวลผลฟอร์ม</h3>";
if (file_exists('topup.php')) {
    echo "<p>ไฟล์ topup.php พบแล้ว</p>";
    
    // Check if form processing works
    if (isset($_POST['verify_angpao_link'])) {
        echo "<p>Form submitted with angpao link processing</p>";
    } else {
        echo "<p>No form submission detected</p>";
    }
} else {
    echo "<p style='color: red;'>ไฟล์ topup.php ไม่พบ</p>";
}

echo "<hr>";
echo "<p><strong>วิธีการทดสอบ:</strong></p>";
echo "<ol>";
echo "<li>แก้ไข \$testHash ในไฟล์นี้เป็น voucher hash จริงที่ต้องการทดสอบ</li>";
echo "<li>เปิดไฟล์นี้ในเบราว์เซอร์</li>";
echo "<li>ดูผลลัพธ์จากการทดสอบแต่ละขั้นตอน</li>";
echo "</ol>";
?>
