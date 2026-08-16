<?php
declare(strict_types=1);

/**
 * Selvtjekkende test af spilmotoren.
 * Kør:  php test_game.php
 * Afslutter med exit-kode 0 hvis alle tests passerer, 1 ved fejl.
 */

require __DIR__ . '/Game.php';

$failures = 0;

function ok(bool $cond, string $msg): void
{
    global $failures;
    if ($cond) {
        echo "  ✓ $msg\n";
    } else {
        echo "  ✗ FEJL: $msg\n";
        $failures++;
    }
}

function makeBoard(): array
{
    return array_fill(0, 8, array_fill(0, 8, ''));
}

function gameFrom(array $board, string $turn = 'W'): Game
{
    return Game::fromArray([
        'board' => $board, 'turn' => $turn, 'winner' => null,
        'continuing' => null, 'moveCount' => 0,
    ]);
}

// Test 1: Startposition har 12 brikker pr. farve på korrekte felter.
$g = Game::create();
$b = $g->board();
$counts = ['W' => 0, 'B' => 0];
foreach ($b as $r => $row) {
    foreach ($row as $c => $p) {
        if ($p !== '') {
            $counts[$p[0]]++;
            ok((($r + $c) % 2 === 1), "Brik på mørkt felt ($r,$c)");
            if ($p[0] === 'W') ok($r >= 5, "Hvid brik på række $r");
            if ($p[0] === 'B') ok($r <= 2, "Sort brik på række $r");
        }
    }
}
ok($counts['W'] === 12, "12 hvide brikker (fik {$counts['W']})");
ok($counts['B'] === 12, "12 sorte brikker (fik {$counts['B']})");
ok($g->turn() === 'W', "Hvid starter");

// Test 2: Hvid kan rykke fremad diagonalt.
$g = Game::create();
$legal = $g->legalMoves();
ok(isset($legal['5,0']), "Hvid brik (5,0) har lovlige træk");
ok(in_array('6,1', $legal['5,0'] ?? [], true), "Hvid (5,0) kan til (6,1)");
$g->move(5, 0, 6, 1);
ok($g->turn() === 'B', "Efter hvids træk er det sorts tur");

// Test 3: Ulovligt træk afvises (baglæns for menig brik).
$g = Game::create();
try {
    $g->move(5, 0, 4, 1);
    ok(false, "Menig hvid må ikke rykke baglæns");
} catch (\InvalidArgumentException $e) {
    ok(true, "Menig hvid afvises ved baglæns træk");
}

// Test 4: Slag muligt og tilladt.
$b4 = makeBoard();
$b4[5][1] = 'W';
$b4[4][2] = 'B';
$g2 = gameFrom($b4);
$legal = $g2->legalMoves();
ok(isset($legal['5,1']), "Hvid brik med slag har træk");
ok(in_array('3,3', $legal['5,1'] ?? [], true), "Hvid (5,1) kan slå sort til (3,3)");

// Test 5: Tvunget slag forhindrer simpelt træk.
$b5 = makeBoard();
$b5[5][1] = 'W';
$b5[4][2] = 'B';
$b5[5][7] = 'W';
$g3 = gameFrom($b5);
$legal = $g3->legalMoves();
ok(isset($legal['5,1']), "Slag-brik er lovlig");
ok(!isset($legal['5,7']), "Simpel-træk-brik er ulovlig når tvunget slag findes");

// Test 6: Kaskade-slag (to slag i træk med samme brik).
$b6 = makeBoard();
$b6[5][1] = 'W';
$b6[4][2] = 'B';
$b6[2][4] = 'B';
$g4 = gameFrom($b6);
$g4->move(5, 1, 3, 3);
ok($g4->turn() === 'W', "Efter første slag i kaskade er det stadig hvids tur");
ok($g4->continuing() === '3,3', "Continuing sættes til (3,3)");
$legal = $g4->legalMoves();
ok(in_array('1,5', $legal['3,3'] ?? [], true), "Kaskade: brik kan slå igen til (1,5)");
$g4->move(3, 3, 1, 5);
ok($g4->continuing() === null, "Efter kaskade slut er continuing null");
ok($g4->turn() === 'B', "Efter kaskade er det sorts tur");

// Test 7: Promovering ved bagrække.
$b7 = makeBoard();
$b7[6][1] = 'W';
$g5 = gameFrom($b7);
$g5->move(6, 1, 7, 0);
$brd = $g5->board();
ok($brd[7][0] === 'WK', "Hvid brik promoveres til konge (WK) ved række 7");

// Test 8: Konge kan rykke baglæns.
$b8 = makeBoard();
$b8[4][4] = 'WK';
$g6 = gameFrom($b8);
$legal = $g6->legalMoves();
$targets = $legal['4,4'] ?? [];
ok(in_array('3,3', $targets, true), "Konge kan rykke baglæns (3,3)");
ok(in_array('5,5', $targets, true), "Konge kan rykke fremad (5,5)");
ok(in_array('3,5', $targets, true), "Konge kan rykke diagonal (3,5)");

// Test 9: Modstander uden brikker har ingen lovlige træk.
$b9 = makeBoard();
$b9[7][0] = 'W';
$g7 = gameFrom($b9, 'B');
$legalB = $g7->legalMoves();
ok(count($legalB) === 0, "Sort med ingen brikker har ingen lovlige træk");

// Test 10: Spil ikke slut når begge har brikker og træk.
$b10 = makeBoard();
$b10[7][0] = 'W';
$b10[1][2] = 'B';
$g8 = gameFrom($b10);
$g8->move(7, 0, 6, 1);
ok($g8->turn() === 'B' && $g8->winner() === null, "Spil ikke slut når begge har brikker og træk");

// Test 11: toArray/fromArray round-trip bevarer tilstand.
$g = Game::create();
$g->move(5, 0, 6, 1);
$state = $g->toArray();
$g9 = Game::fromArray($state);
ok($g9->turn() === $g->turn(), "Round-trip bevarer tur");
ok($g9->board() === $g->board(), "Round-trip bevarer bræt");

echo "\n";
if ($failures === 0) {
    echo "ALLE TESTS BESTÅET\n";
    exit(0);
} else {
    echo "$failures FEJL\n";
    exit(1);
}
