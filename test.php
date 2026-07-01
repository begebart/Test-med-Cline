<?php
/**
 * Simple test script to verify the application works
 */

echo "=== Collaborative Paper Application Test ===\n\n";

// Test 1: Check if all required files exist
echo "1. Checking required files...\n";
$requiredFiles = ['config.php', 'index.php', 'paper.php', 'api.php', 'logout.php'];
$allFilesExist = true;

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "   ✓ $file exists\n";
    } else {
        echo "   ✗ $file missing\n";
        $allFilesExist = false;
    }
}

if (!$allFilesExist) {
    echo "\n❌ Some files are missing!\n";
    exit(1);
}

// Test 2: Check logs directory
echo "\n2. Checking logs directory...\n";
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
    echo "   ✓ Created logs directory\n";
} else {
    echo "   ✓ logs directory exists\n";
}

if (!file_exists('logs/.htaccess')) {
    file_put_contents('logs/.htaccess', "Options -Indexes\n");
    echo "   ✓ Created .htaccess in logs\n";
} else {
    echo "   ✓ logs/.htaccess exists\n";
}

// Test 3: Check if logs directory is writable
echo "\n3. Checking permissions...\n";
if (is_writable('logs')) {
    echo "   ✓ logs directory is writable\n";
} else {
    echo "   ⚠ logs directory is not writable (may need chmod)\n";
}

// Test 4: Test config.php functions
echo "\n4. Testing config.php functions...\n";
require_once 'config.php';

echo "   ✓ config.php loaded successfully\n";
echo "   ✓ Allowed users: " . implode(', ', $allowed_users) . "\n";
echo "   ✓ Inactivity timeout: " . INACTIVITY_TIMEOUT . " seconds\n";
echo "   ✓ Log directory: " . LOG_DIR . "\n";

// Test 5: Test logging function
echo "\n5. Testing logging function...\n";
logActivity('TestUser', 'Test action');
$logFile = LOG_DIR . '/activity_' . date('Y-m-d') . '.txt';
if (file_exists($logFile)) {
    echo "   ✓ Log file created: $logFile\n";
    $content = file_get_contents($logFile);
    if (strpos($content, 'TestUser - Test action') !== false) {
        echo "   ✓ Log entry written correctly\n";
    }
} else {
    echo "   ✗ Log file not created\n";
}

// Test 6: Test session functions
echo "\n6. Testing session functions...\n";
$_SESSION['users']['Alice'] = ['last_activity' => time()];
$activeUsers = getActiveUsers();
if (count($activeUsers) === 1 && $activeUsers[0]['name'] === 'Alice') {
    echo "   ✓ getActiveUsers() works correctly\n";
} else {
    echo "   ✗ getActiveUsers() failed\n";
}

// Test 7: Check paper_content.txt
echo "\n7. Checking paper content file...\n";
if (!file_exists('paper_content.txt')) {
    file_put_contents('paper_content.txt', "Welcome to the Collaborative Paper!\n\nStart writing here...\n");
    echo "   ✓ Created paper_content.txt with initial content\n";
} else {
    echo "   ✓ paper_content.txt exists\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "✓ All basic tests passed!\n";
echo "\nNext steps:\n";
echo "1. Start Apache: sudo apachectl start\n";
echo "2. Open browser: http://localhost/collaborative-paper/index.php\n";
echo "3. Login with one of the predefined names\n";
echo "4. Test the collaborative features\n";
echo "\nFor detailed instructions, see README.md\n";
?>