<?php
// Simple test script for the API

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Velkendt.com - API Test</h1>";
echo "<hr>";

// Test 1: Check if config loads
echo "<h2>Test 1: Config fil</h2>";
if (defined('DB_FILE')) {
    echo "✓ Config loaded successfully<br>";
    echo "✓ Database file: " . DB_FILE . "<br>";
    echo "✓ Log directory: " . LOG_DIR . "<br>";
    echo "✓ Legitimate companies: " . count($legitimate_companies) . "<br>";
} else {
    echo "✗ Config failed to load<br>";
}

// Test 2: Test analysis function
echo "<h2>Test 2: Analyse funktion</h2>";
$test_text = "Kære kunde, din konto er blevet suspenderet. Klik her for at bekræfte: http://danskebank-secure.com/login";
$results = analyzeMessage($test_text, 'email');

echo "Test tekst: " . htmlspecialchars($test_text) . "<br>";
echo "Score: " . $results['score'] . "<br>";
echo "Verdict: " . htmlspecialchars($results['verdict']) . "<br>";
echo "Warnings: " . count($results['warnings']) . "<br>";
echo "URLs found: " . count($results['urls']) . "<br>";

if ($results['score'] > 0) {
    echo "✓ Analysis working<br>";
} else {
    echo "✗ Analysis not working<br>";
}

// Test 3: Test legitimate domain detection
echo "<h2>Test 3: Domæne tjek</h2>";
$test_url = "https://www.danskebank.dk/login";
$domain_check = checkDomain($test_url, 'Danske Bank');
echo "URL: " . $test_url . "<br>";
echo "Status: " . $domain_check['status'] . "<br>";
echo "Message: " . htmlspecialchars($domain_check['message']) . "<br>";

if ($domain_check['status'] === 'legitimate') {
    echo "✓ Domain check working<br>";
} else {
    echo "✗ Domain check failed<br>";
}

// Test 4: Test suspicious domain detection
echo "<h2>Test 4: Mistænkeligt domæne</h2>";
$test_url2 = "https://danskebank-secure.com/login";
$domain_check2 = checkDomain($test_url2, 'Danske Bank');
echo "URL: " . $test_url2 . "<br>";
echo "Status: " . $domain_check2['status'] . "<br>";
echo "Message: " . htmlspecialchars($domain_check2['message']) . "<br>";

if ($domain_check2['status'] === 'suspicious') {
    echo "✓ Suspicious domain detection working<br>";
} else {
    echo "✗ Suspicious domain detection failed<br>";
}

// Test 5: Test report saving
echo "<h2>Test 5: Gem rapport</h2>";
$report_id = saveReport($test_text, 'email', $results);
if ($report_id) {
    echo "✓ Report saved with ID: " . $report_id . "<br>";
} else {
    echo "✗ Failed to save report<br>";
}

// Test 6: Test report retrieval
echo "<h2>Test 6: Hent rapporter</h2>";
$reports = getReports(5);
echo "✓ Retrieved " . count($reports) . " reports<br>";

// Test 7: Test logging
echo "<h2>Test 7: Logning</h2>";
logActivity('API Test', 'Kørt test_api.php');
$log_file = LOG_DIR . '/activity_' . date('Y-m-d') . '.txt';
if (file_exists($log_file)) {
    echo "✓ Log file created: " . $log_file . "<br>";
} else {
    echo "✗ Log file not created<br>";
}

echo "<hr>";
echo "<h2>Test afsluttet</h2>";
echo "<p><a href='index.php'>← Tilbage til hovedsiden</a></p>";
?>