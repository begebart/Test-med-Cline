<?php
// Diagnose: test buildStatus og legalMoves direkte, vis alle fejl.
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/Game.php';

echo "=== PHP version ===\n" . PHP_VERSION . "\n\n";

echo "=== Game::create() + legalMoves() ===\n";
try {
    $game = Game::create();
    $legal = $game->legalMoves();
    echo "legalMoves() returnerede " . count($legal) . " brikker med træk\n";
    foreach ($legal as $from => $tos) {
        echo "  $from => " . implode(',', $tos) . "\n";
    }
} catch (\Throwable $e) {
    echo "FEJL i legalMoves: " . $e->getMessage() . "\n";
    echo "Fil: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== buildStatus() ===\n";
// Definer de samme konstanter som api.php
define('DATA_DIR', __DIR__ . '/data');
define('STATE_FILE', DATA_DIR . '/state.json');
define('PLAYERS_FILE', DATA_DIR . '/players.json');

$state = ['rev' => 1, 'game' => $game->toArray()];
$players = ['slots' => ['W' => 'token1', 'B' => 'token2']];

// Kopier buildStatus logikken
try {
    $game2 = Game::fromArray($state['game']);
    $result = [
        'ok'    => true,
        'rev'   => $state['rev'],
        'game'  => $state['game'],
        'legal' => $game2->legalMoves(),
        'slots' => ['W' => $players['slots']['W'] !== null, 'B' => $players['slots']['B'] !== null],
    ];
    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    echo "JSON længde: " . strlen($json) . " bytes\n";
    echo "Indeholder 'legal': " . (strpos($json, 'legal') !== false ? 'JA' : 'NEJ') . "\n";
    echo "Første 300 tegn:\n" . substr($json, 0, 300) . "\n";
} catch (\Throwable $e) {
    echo "FEJL i buildStatus: " . $e->getMessage() . "\n";
    echo "Fil: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
