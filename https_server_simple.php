<?php
// Simple HTTPS Server Configuration (Alternative approach)
// This file provides a simpler HTTPS setup using PHP's built-in server with SSL

class HTTPSServer {
    private $ssl_cert;
    private $ssl_key;
    private $port;
    private $host;
    
    public function __construct($host = '0.0.0.0', $port = 443) {
        $this->host = $host;
        $this->port = $port;
        $this->ssl_cert = '/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/fullchain.pem';
        $this->ssl_key = '/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/privkey.pem';
    }
    
    public function checkCertificates() {
        if (!file_exists($this->ssl_cert)) {
            throw new Exception("SSL certificate not found: {$this->ssl_cert}");
        }
        
        if (!file_exists($this->ssl_key)) {
            throw new Exception("SSL private key not found: {$this->ssl_key}");
        }
        
        return true;
    }
    
    public function start() {
        try {
            $this->checkCertificates();
            
            echo "Starting HTTPS Server...\n";
            echo "Host: {$this->host}\n";
            echo "Port: {$this->port}\n";
            echo "SSL Certificate: {$this->ssl_cert}\n";
            echo "SSL Private Key: {$this->ssl_key}\n";
            echo "Document Root: " . __DIR__ . "\n";
            echo "Access URL: https://scriptbotonline.vipv2boxth.xyz/\n\n";
            
            // Create SSL context
            $context = stream_context_create([
                'ssl' => [
                    'local_cert' => $this->ssl_cert,
                    'local_pk' => $this->ssl_key,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER,
                ],
                'socket' => [
                    'so_reuseport' => 1,
                    'backlog' => 511,
                ]
            ]);
            
            $server = stream_socket_server(
                "ssl://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
                $context
            );
            
            if (!$server) {
                throw new Exception("Failed to create server: $errstr ($errno)");
            }
            
            echo "HTTPS Server is running! Waiting for connections...\n\n";
            
            // Handle connections
            $this->handleConnections($server);
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function handleConnections($server) {
        while (true) {
            $client = stream_socket_accept($server, -1);
            
            if ($client) {
                $this->handleClient($client);
                fclose($client);
            }
        }
    }
    
    private function handleClient($client) {
        $request = $this->readRequest($client);
        $this->processRequest($client, $request);
    }
    
    private function readRequest($client) {
        $request = '';
        $headers = [];
        
        while (($line = fgets($client)) !== false) {
            $request .= $line;
            if (trim($line) === '') break;
        }
        
        return $request;
    }
    
    private function processRequest($client, $request) {
        $lines = explode("\r\n", $request);
        $requestLine = $lines[0] ?? '';
        
        if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)$/', $requestLine, $matches)) {
            $method = $matches[1];
            $uri = $matches[2];
            $protocol = $matches[3];
            
            $this->routeRequest($client, $method, $uri);
        } else {
            $this->sendResponse($client, 400, 'Bad Request');
        }
    }
    
    private function routeRequest($client, $method, $uri) {
        // Parse URL
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        
        // Security: prevent path traversal
        $path = str_replace(['../', '..\\'], '', $path);
        
        // Default to index.php for root
        if ($path === '/') {
            $path = '/index.php';
        }
        
        $file = ltrim($path, '/');
        
        if (file_exists($file)) {
            $this->serveFile($client, $file);
        } else {
            $this->sendResponse($client, 404, 'Not Found', 
                '<html><body><h1>404 - Page Not Found</h1></body></html>');
        }
    }
    
    private function serveFile($client, $file) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        if ($extension === 'php') {
            $this->servePhp($client, $file);
        } else {
            $this->serveStatic($client, $file);
        }
    }
    
    private function servePhp($client, $file) {
        // Set up environment
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = $this->port;
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['SERVER_NAME'] = 'scriptbotonline.vipv2boxth.xyz';
        
        ob_start();
        
        try {
            include $file;
            $content = ob_get_contents();
            $this->sendResponse($client, 200, 'OK', $content, 'text/html; charset=UTF-8');
        } catch (Exception $e) {
            $error = '<html><body><h1>500 - Internal Server Error</h1><p>' . 
                     htmlspecialchars($e->getMessage()) . '</p></body></html>';
            $this->sendResponse($client, 500, 'Internal Server Error', $error);
        } finally {
            ob_end_clean();
        }
    }
    
    private function serveStatic($client, $file) {
        $content = file_get_contents($file);
        $mimeType = $this->getMimeType($file);
        $this->sendResponse($client, 200, 'OK', $content, $mimeType);
    }
    
    private function getMimeType($file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        $types = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
        ];
        
        return $types[$extension] ?? 'application/octet-stream';
    }
    
    private function sendResponse($client, $code, $status, $body = '', $contentType = 'text/html') {
        $response = "HTTP/1.1 $code $status\r\n";
        $response .= "Content-Type: $contentType\r\n";
        $response .= "Content-Length: " . strlen($body) . "\r\n";
        $response .= "Connection: close\r\n";
        $response .= "Server: PHP-HTTPS/1.0\r\n";
        $response .= "\r\n";
        $response .= $body;
        
        fwrite($client, $response);
    }
}

// Start the server
if (php_sapi_name() === 'cli') {
    $server = new HTTPSServer('0.0.0.0', 443);
    $server->start();
} else {
    echo "This script must be run from command line.\n";
}
?>
