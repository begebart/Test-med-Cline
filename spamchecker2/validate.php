<?php
header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$dbname = 'your_database_name';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Function to extract URLs from text
function extractUrls($text) {
    $regex = '/https?\:\/\/[^\s]+/';
    preg_match_all($regex, $text, $matches);
    return $matches[0];
}

// Function to check if a domain is trusted
function isTrustedDomain($url, $pdo) {
    $domain = parse_url($url, PHP_URL_HOST);
    $domain = strtolower($domain);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trusted_domains WHERE domain = ? AND is_active = 1");
    $stmt->execute([$domain]);
    return $stmt->fetchColumn() > 0;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    $urls = extractUrls($content);
    $results = [];

    foreach ($urls as $url) {
        $results[] = [
            'url' => $url,
            'valid' => isTrustedDomain($url, $pdo)
        ];
    }

    echo json_encode(['results' => $results]);
    exit;
}
?>