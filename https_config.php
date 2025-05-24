<?php
// Configuration file for HTTPS server
return [
    'ssl' => [
        'cert_path' => '/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/fullchain.pem',
        'key_path' => '/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/privkey.pem',
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ],
    
    'server' => [
        'host' => '0.0.0.0',
        'port' => 443,
        'document_root' => __DIR__,
        'default_file' => 'index.php'
    ],
    
    'security' => [
        'prevent_path_traversal' => true,
        'allowed_extensions' => ['php', 'html', 'css', 'js', 'json', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'],
        'blocked_files' => ['.env', '.htaccess', 'config.php']
    ],
    
    'logging' => [
        'enabled' => true,
        'log_file' => 'https_server.log',
        'log_level' => 'info' // debug, info, warning, error
    ],
    
    'performance' => [
        'max_connections' => 100,
        'timeout' => 30,
        'memory_limit' => '128M'
    ]
];
?>
