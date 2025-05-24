<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return error JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Initialize response data
$responseData = [
    'onlineUsers' => 0,
    'ping' => 0,
    'load' => '0%'
];

// Get total online users by calling API for all profiles
$validProfiles = ['true_dtac_nopro', 'true_zoom', 'ais', 'true_pro_facebook'];

try {
    // Make API call to get online users from server
    $apiUrl = 'http://103.245.164.86:4040/client/onlines';
    $processedUsers = [];  // Track unique users
    
    // Get ping to server with slight random variations for more dynamic display
    $startTime = microtime(true);
    $pingTest = @file_get_contents($apiUrl, false, stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 2]]));
    $endTime = microtime(true);
    $pingMs = round(($endTime - $startTime) * 1000);
    
    // Optimize ping values to stay in the 10-25ms range as requested
    // Fixed optimal ping range between 10-25ms
    $pingMs = rand(10, 25);
    
    $responseData['ping'] = $pingMs;
    
    foreach ($validProfiles as $profileKey) {
        $postData = json_encode([
            'vpsType' => 'IDC',
            'profileKey' => $profileKey
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => 2 // Set timeout to 2 seconds
        ]);

        $response = curl_exec($ch);
        
        if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            $json = json_decode($response, true);
            if (isset($json['success']) && $json['success'] && isset($json['list'])) {
                foreach ($json['list'] as $clientName) {
                    $clientNameClean = trim($clientName);
                    if (!empty($clientNameClean) && !isset($processedUsers[$clientNameClean])) {
                        $processedUsers[$clientNameClean] = true;
                        $responseData['onlineUsers']++;
                    }
                }
            }
        }
        curl_close($ch);
    }
    
    // Set server load to be between 70-100% as requested
    $totalLoad = rand(70, 100); 
    $responseData['load'] = $totalLoad;
    
} catch (Exception $e) {
    // Error occurred, return default values
    $responseData['error'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($responseData);
?>
