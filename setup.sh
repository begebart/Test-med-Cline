#!/bin/bash

# Collaborative Paper Application Setup Script
# This script helps set up the application on macOS/Linux

echo "=== Collaborative Paper Application Setup ==="
echo ""

# Get the current directory
APP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
echo "Application directory: $APP_DIR"
echo ""

# Check if running with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script should be run with sudo for proper permissions"
    echo "   Run: sudo ./setup.sh"
    echo ""
fi

# Create logs directory if it doesn't exist
echo "1. Setting up logs directory..."
if [ ! -d "logs" ]; then
    mkdir -p logs
    echo "   ✓ Created logs directory"
else
    echo "   ✓ logs directory exists"
fi

# Set permissions for logs directory
echo "   Setting permissions..."
chmod 755 logs
echo "   ✓ Permissions set"

# Detect web server user
echo ""
echo "2. Detecting web server user..."
if [ "$(uname)" == "Darwin" ]; then
    # macOS
    WEB_USER="_www"
    WEB_GROUP="_www"
    echo "   Detected macOS, using _www user"
else
    # Linux
    WEB_USER="www-data"
    WEB_GROUP="www-data"
    echo "   Detected Linux, using www-data user"
fi

# Set ownership
echo ""
echo "3. Setting file ownership..."
if [ "$EUID" -eq 0 ]; then
    chown -R $WEB_USER:$WEB_GROUP "$APP_DIR"
    echo "   ✓ Ownership set to $WEB_USER:$WEB_GROUP"
else
    echo "   ⚠️  Skipped (run with sudo to set ownership)"
fi

# Create paper_content.txt if it doesn't exist
echo ""
echo "4. Creating paper content file..."
if [ ! -f "paper_content.txt" ]; then
    echo "Welcome to the Collaborative Paper!

Start writing here...

This is a shared document where only one person can write at a time.
Click 'Take Control' to start writing, and 'Leave Control' when you're done." > paper_content.txt
    echo "   ✓ Created paper_content.txt"
else
    echo "   ✓ paper_content.txt exists"
fi

# Set permissions for paper_content.txt
chmod 644 paper_content.txt
echo "   ✓ Permissions set"

# Check PHP installation
echo ""
echo "5. Checking PHP installation..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo "   ✓ PHP found: $PHP_VERSION"
else
    echo "   ✗ PHP not found. Please install PHP 7.4 or higher"
fi

# Check Apache installation
echo ""
echo "6. Checking Apache installation..."
if command -v apachectl &> /dev/null; then
    echo "   ✓ Apache found"
    
    # Try to start Apache
    echo ""
    echo "7. Starting Apache..."
    if [ "$EUID" -eq 0 ]; then
        apachectl start 2>/dev/null
        if [ $? -eq 0 ]; then
            echo "   ✓ Apache started successfully"
        else
            echo "   ⚠️  Apache may already be running or failed to start"
        fi
    else
        echo "   ⚠️  Skipped (run with sudo to start Apache)"
    fi
else
    echo "   ✗ Apache not found. Please install Apache web server"
fi

# Create Apache configuration suggestion
echo ""
echo "8. Apache configuration..."
echo "   If you need to configure a virtual host, add this to your Apache config:"
echo ""
echo "   <VirtualHost *:80>"
echo "       DocumentRoot \"$APP_DIR\""
echo "       ServerName collaborative-paper.local"
echo "       <Directory \"$APP_DIR\">"
echo "           Options Indexes FollowSymLinks"
echo "           AllowOverride All"
echo "           Require all granted"
echo "       </Directory>"
echo "       ErrorLog \"/usr/local/var/log/httpd/collaborative-paper-error_log\""
echo "       CustomLog \"/usr/local/var/log/httpd/collaborative-paper-access_log\" common"
echo "   </VirtualHost>"
echo ""

# Summary
echo "=== Setup Complete ==="
echo ""
echo "To access the application:"
echo "1. Make sure Apache is running: sudo apachectl start"
echo "2. Open your browser and navigate to:"
echo "   http://localhost/$(basename "$APP_DIR")/index.php"
echo ""
echo "Or if you configured a virtual host:"
echo "   http://collaborative-paper.local/index.php"
echo ""
echo "Default users: Alice, Bob, Charlie, Diana, Eve, Frank, Grace, Henry, Ivy, Jack"
echo ""
echo "For more information, see README.md"