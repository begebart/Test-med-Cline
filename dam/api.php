<?php
declare(strict_types=1);

/**
 * REST-ish endpoint for netværksbaseret Dam.
 * Endpoints: join | status | move | reset
 */

require __DIR__ . '/Game.php';

// Midlertidig fejlvisning for at finde runtime-fejl (fjern i produktion).
ini_set('display_errors', '0'); // ikke HTML i JSON
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

const DATA_DIR = __DIR__ . '/data';
const STATE_FILE = DATA_DIR . '/state.json';
const PLAYERS_FILE = DATA_DIR . '/players.json';

function ensureDataDir(): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function err(string $msg, int $code = 400): void
{
    jsonResponse(['ok' => false, 'error' => $msg], $code);
}

function withLock(callable $fn)
{
    ensureDataDir();
    $lockFile = DATA_DIR . '/.lock';
    $fp = fopen($lockFile, 'cb+');
    if (!$fp) {
        err('Kunne ikke åbne låsefil', 500);
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        err('Kunne ikke opnå lås', 500);
    }
    try {
        return $fn();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function loadPlayers(): array
{
    if (!file_exists(PLAYERS_FILE)) {
        return ['slots' => ['W' => null, 'B' => null]];
    }
    $raw = @file_get_contents(PLAYERS_FILE);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data) || !isset($data['slots'])) {
        return ['slots' => ['W' => null, 'B' => null]];
    }
    return $data;
}

function savePlayers(array $players): void
{
    file_put_contents(PLAYERS_FILE, json_encode($players, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function loadState(): array
{
    if (!file_exists(STATE_FILE)) {
        $game = Game::create();
        return ['rev' => 1, 'game' => $game->toArray()];
    }
    $raw = @file_get_contents(STATE_FILE);
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data) || !isset($data['game']['board'])) {
        $game = Game::create();
        return ['rev' => 1, 'game' => $game->toArray()];
    }
    return $data;
}

function saveState(array $state): void
{
    file_put_contents(STATE_FILE, json_encode($state, JSON_UNESCAPED_UNICODE));
}

function cookieToken(): string
{
    $t = $_SERVER['HTTP_X_PLAYER_TOKEN'] ?? ($_COOKIE['player_token'] ?? '');
    return is_string($t) ? $t : '';
}

function newToken(): string
{
    return bin2hex(random_bytes(16));
}

/** Byg status-svar inkl. server-beregnete lovlige træk. Fejl fanges og eksponeres. */
function buildStatus(array $state, array $players): array
{
    $game = Game::fromArray($state['game']);
    try {
        $legal = $game->legalMoves();
    } catch (\Throwable $e) {
        // Vis fejlen i stedet for at lade JSON mangle legal.
        $legal = ['__error__' => $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()];
    }
    return [
        'ok'    => true,
        'rev'   => $state['rev'],
        'game'  => $state['game'],
        'legal' => $legal,
        'slots' => [
            'W' => $players['slots']['W'] !== null,
            'B' => $players['slots']['B'] !== null,
        ],
    ];
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'join':
        $assigned = null;
        $token = withLock(function () use (&$assigned) {
            $players = loadPlayers();
            $token = cookieToken();
            foreach ($players['slots'] as $slot => $tok) {
                if ($tok === $token && $token !== '') {
                    $assigned = $slot;
                    return $token;
                }
            }
            foreach (['W', 'B'] as $slot) {
                if ($players['slots'][$slot] === null) {
                    $token = newToken();
                    $players['slots'][$slot] = $token;
                    savePlayers($players);
                    $assigned = $slot;
                    return $token;
                }
            }
            $assigned = null;
            return null;
        });
        if ($token === null) {
            err('Spillet er fuldt (2 spillere). Prøv igen senere.', 409);
        }
        setcookie('player_token', $token, [
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'expires'  => time() + 3600 * 24,
        ]);
        jsonResponse(['ok' => true, 'token' => $token, 'slot' => $assigned]);
        break;

    case 'status':
        $rev = isset($_GET['rev']) ? (int) $_GET['rev'] : 0;
        $deadline = time() + 6;
        while (time() < $deadline) {
            $state = loadState();
            if ($state['rev'] > $rev) {
                jsonResponse(buildStatus($state, loadPlayers()));
            }
            usleep(400000);
        }
        jsonResponse(buildStatus(loadState(), loadPlayers()));
        break;

    case 'move':
        $token = cookieToken();
        if ($token === '') {
            err('Ikke logget ind. Kald join først.', 401);
        }
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        if (!preg_match('/^(\d),(\d)$/', $from, $f) || !preg_match('/^(\d),(\d)$/', $to, $t)) {
            err('Ugyldige koordinater. Brug from=r,c&to=r,c');
        }
        $fromR = (int) $f[1]; $fromC = (int) $f[2];
        $toR = (int) $t[1]; $toC = (int) $t[2];

        $error = withLock(function () use ($token, $fromR, $fromC, $toR, $toC) {
            $players = loadPlayers();
            $mySlot = null;
            foreach ($players['slots'] as $slot => $tok) {
                if ($tok === $token) { $mySlot = $slot; break; }
            }
            if ($mySlot === null) {
                return 'Du er ikke med i spillet';
            }
            $state = loadState();
            $game = Game::fromArray($state['game']);
            if ($game->turn() !== $mySlot) {
                return 'Det er ikke din tur';
            }
            try {
                $game->move($fromR, $fromC, $toR, $toC);
            } catch (\InvalidArgumentException $e) {
                return $e->getMessage();
            }
            $state['game'] = $game->toArray();
            $state['rev'] = ($state['rev'] ?? 1) + 1;
            saveState($state);
            return null;
        });

        if ($error !== null) {
            err($error);
        }
        jsonResponse(['ok' => true]);
        break;

    case 'reset':
        withLock(function () {
            $game = Game::create();
            saveState(['rev' => 1, 'game' => $game->toArray()]);
            savePlayers(['slots' => ['W' => null, 'B' => null]]);
        });
        setcookie('player_token', '', ['path' => '/', 'expires' => time() - 1]);
        jsonResponse(['ok' => true]);
        break;

    default:
        err('Ukendt action. Brug join|status|move|reset');
}
