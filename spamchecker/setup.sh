#!/bin/bash

# Setup script for Velkendt.com SpamChecker

echo "=================================="
echo "Velkendt.com - Setup"
echo "=================================="
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP er ikke installeret. Installer PHP 7.4 eller højere."
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✓ PHP fundet: version $PHP_VERSION"

# Check PHP version
PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")

if [ "$PHP_MAJOR" -lt 7 ] || ([ "$PHP_MAJOR" -eq 7 ] && [ "$PHP_MINOR" -lt 4 ]); then
    echo "⚠️  Advarsel: PHP 7.4 eller højere anbefales. Du har $PHP_VERSION"
fi

# Create logs directory if it doesn't exist
if [ ! -d "logs" ]; then
    mkdir -p logs
    echo "✓ Oprettet logs mappe"
fi

# Set permissions for logs directory
chmod 755 logs
echo "✓ Sat tilladelser for logs mappe"

# Create empty reports.json if it doesn't exist
if [ ! -f "reports.json" ]; then
    echo "[]" > reports.json
    echo "✓ Oprettet tom reports.json fil"
fi

# Set permissions for reports.json
chmod 644 reports.json
echo "✓ Sat tilladelser for reports.json"

# Check if Apache is running (macOS/Linux)
if command -v apachectl &> /dev/null; then
    echo ""
    echo "Apache fundet på systemet."
    echo "For at starte Apache, kør: sudo apachectl start"
elif command -v systemctl &> /dev/null; then
    echo ""
    echo "Systemd fundet på systemet."
    echo "For at starte Apache, kør: sudo systemctl start apache2"
fi

echo ""
echo "=================================="
echo "✅ Setup fuldført!"
echo "=================================="
echo ""
echo "Næste skridt:"
echo "1. Sørg for at Apache kører"
echo "2. Placer filerne i din web server mappe"
echo "3. Åbn http://localhost/velkendt/ i din browser"
echo ""
echo "For mere information, se README.md"
echo ""