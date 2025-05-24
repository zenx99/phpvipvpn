<?php
// HTTPS Server Configuration for PHP VIP VPN
error_reporting(E_ALL);
ini_set('display_errors', 1);

// SSL Certificate paths
$ssl_cert = '/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/fullchain.pem';
$ssl_key = '/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/privkey.pem';

// Check if SSL certificates exist
if (!file_exists($ssl_cert) || !file_exists($ssl_key)) {
    die("SSL certificates not found. Please check the certificate paths.\n");
}

// Create SSL context
$context = stream_context_create([
    'ssl' => [
        'local_cert' => $ssl_cert,
        'local_pk' => $ssl_key,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
        'crypto_method' => STREAM_CRYPTO_METHOD_TLS_SERVER
    ]
]);

// Create HTTPS server socket
$socket = stream_socket_server(
    'ssl://0.0.0.0:443',
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if (!$socket) {
    die("Failed to create HTTPS server: $errstr ($errno)\n");
}

echo "HTTPS Server running on port 443\n";
echo "SSL Certificate: $ssl_cert\n";
echo "SSL Private Key: $ssl_key\n";
echo "Waiting for connections...\n\n";

// Function to handle HTTP requests
function handleRequest($client) {
    $request = '';
    $headers = [];
    
    // Read request headers
    while (($line = fgets($client)) !== false) {
        $request .= $line;
        if (trim($line) === '') break;
        
        if (preg_match('/^([^:]+):\s*(.+)$/', trim($line), $matches)) {
            $headers[strtolower($matches[1])] = $matches[2];
        }
    }
    
    // Parse request line
    $lines = explode("\n", $request);
    $requestLine = $lines[0];
    $parts = explode(' ', $requestLine);
    
    if (count($parts) < 3) {
        sendResponse($client, 400, 'Bad Request');
        return;
    }
    
    $method = $parts[0];
    $uri = $parts[1];
    $version = $parts[2];
    
    // Route the request
    routeRequest($client, $method, $uri, $headers);
}

// Function to route requests
function routeRequest($client, $method, $uri, $headers) {
    // Remove query string for routing
    $path = parse_url($uri, PHP_URL_PATH);
    
    // Security: Prevent path traversal
    $path = str_replace(['../', '..\\'], '', $path);
    
    // Route to appropriate PHP file
    switch ($path) {
        case '/':
        case '/index.php':
            servePhpFile($client, 'index.php');
            break;
        case '/login.php':
            servePhpFile($client, 'login.php');
            break;
        case '/register.php':
            servePhpFile($client, 'register.php');
            break;
        case '/admin_dashboard.php':
            servePhpFile($client, 'admin_dashboard.php');
            break;
        case '/settings.php':
            servePhpFile($client, 'settings.php');
            break;
        case '/vpn_history.php':
            servePhpFile($client, 'vpn_history.php');
            break;
        case '/topup.php':
            servePhpFile($client, 'topup.php');
            break;
        default:
            // Check if file exists
            $file = ltrim($path, '/');
            if (file_exists($file)) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    servePhpFile($client, $file);
                } else {
                    serveStaticFile($client, $file);
                }
            } else {
                sendResponse($client, 404, 'Not Found', '<h1>404 - Page Not Found</h1>');
            }
            break;
    }
}

// Function to serve PHP files
function servePhpFile($client, $filename) {
    if (!file_exists($filename)) {
        sendResponse($client, 404, 'Not Found', '<h1>404 - File Not Found</h1>');
        return;
    }
    
    // Capture PHP output
    ob_start();
    
    // Set up environment for PHP execution
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
    $_SERVER['REQUEST_SCHEME'] = 'https';
    
    try {
        include $filename;
        $content = ob_get_contents();
    } catch (Exception $e) {
        $content = '<h1>500 - Internal Server Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    } finally {
        ob_end_clean();
    }
    
    sendResponse($client, 200, 'OK', $content, 'text/html; charset=UTF-8');
}

// Function to serve static files
function serveStaticFile($client, $filename) {
    if (!file_exists($filename)) {
        sendResponse($client, 404, 'Not Found');
        return;
    }
    
    $content = file_get_contents($filename);
    $mimeType = getMimeType($filename);
    
    sendResponse($client, 200, 'OK', $content, $mimeType);
}

// Function to get MIME type
function getMimeType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

// Function to send HTTP response
function sendResponse($client, $code, $status, $body = '', $contentType = 'text/html; charset=UTF-8') {
    $headers = "HTTP/1.1 $code $status\r\n";
    $headers .= "Content-Type: $contentType\r\n";
    $headers .= "Content-Length: " . strlen($body) . "\r\n";
    $headers .= "Connection: close\r\n";
    $headers .= "Server: PHP-HTTPS-Server/1.0\r\n";
    $headers .= "\r\n";
    
    fwrite($client, $headers . $body);
}

// Main server loop
while (true) {
    $client = stream_socket_accept($socket, 30);
    
    if ($client) {
        $clientInfo = stream_socket_get_name($client, true);
        echo "New connection from: $clientInfo\n";
        
        try {
            handleRequest($client);
        } catch (Exception $e) {
            echo "Error handling request: " . $e->getMessage() . "\n";
        } finally {
            fclose($client);
        }
    }
}

fclose($socket);
?>
