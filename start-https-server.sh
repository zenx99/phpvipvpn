#!/bin/bash

# HTTPS Server Startup Script for PHP VIP VPN
# This script starts the HTTPS server using PHP

echo "Starting PHP HTTPS Server..."
echo "================================"

# Check if running as root (required for port 443)
if [ "$EUID" -ne 0 ]; then
    echo "Error: This script must be run as root to bind to port 443"
    echo "Please run: sudo ./start-https-server.sh"
    exit 1
fi

# Check if SSL certificates exist
SSL_CERT="/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/fullchain.pem"
SSL_KEY="/etc/letsencrypt/live/scriptbotonline.vipv2boxth.xyz/privkey.pem"

if [ ! -f "$SSL_CERT" ]; then
    echo "Error: SSL certificate not found at $SSL_CERT"
    echo "Please ensure Let's Encrypt certificates are properly installed."
    exit 1
fi

if [ ! -f "$SSL_KEY" ]; then
    echo "Error: SSL private key not found at $SSL_KEY"
    echo "Please ensure Let's Encrypt certificates are properly installed."
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed or not in PATH"
    echo "Please install PHP first: apt update && apt install php-cli"
    exit 1
fi

# Check if port 443 is already in use
if netstat -tuln | grep -q ":443 "; then
    echo "Warning: Port 443 is already in use"
    echo "Please stop any existing web server (Apache, Nginx, etc.)"
    echo "Or use a different port"
    exit 1
fi

# Change to script directory
cd "$(dirname "$0")"

echo "SSL Certificate: $SSL_CERT"
echo "SSL Private Key: $SSL_KEY"
echo "Document Root: $(pwd)"
echo ""

# Choose which server to start
echo "Choose HTTPS server implementation:"
echo "1) Full-featured server (https_server.php)"
echo "2) Simple server (https_server_simple.php)"
echo -n "Enter choice [1-2]: "
read choice

case $choice in
    1)
        echo "Starting full-featured HTTPS server..."
        php https_server.php
        ;;
    2)
        echo "Starting simple HTTPS server..."
        php https_server_simple.php
        ;;
    *)
        echo "Invalid choice. Starting simple server by default..."
        php https_server_simple.php
        ;;
esac
