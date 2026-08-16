<?php
declare(strict_types=1);

/**
 * Selvtjekkende test af spilmotoren (international dam).
 * Kør:  php test_game.php
 * Exit 0 hvis alle tests passerer, 1 ved fejl.
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

// Test 1: Startposition — 12 brikker pr. farve, korrekt orientering.
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

// Test 2: Hvid rykker opad (fra række 5 til række 4).
$g = Game::create();
$legal = $g->legalMoves();
ok(isset($legal['5,0']), "Hvid brik (5,0) har lovlige træk");
ok(in_array('4,1', $legal['5,0'] ?? [], true), "Hvid (5,0) kan til (4,1)");
$g->move(5, 0, 4, 1);
ok($g->turn() === 'B', "Efter hvids træk er det sorts tur");

// Test 3: Menig hvid må ikke rykke baglæns.
$g = Game::create();
try {
    $g->move(5, 0, 6, 1);
    ok(false, "Menig hvid må ikke rykke baglæns");
} catch (\InvalidArgumentException $e) {
    ok(true, "Menig hvid afvises ved baglæns træk");
}

// Test 4: Menig slag muligt.
$b4 = makeBoard();
$b4[5][1] = 'W';
$b4[4][2] = 'B';
$g2 = gameFrom($b4);
$legal = $g2->legalMoves();
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

// Test 6: Kaskade-slag for menig.
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

// Test 7: Promovering — hvid ved række 0.
$b7 = makeBoard();
$b7[1][2] = 'W';
$g5 = gameFrom($b7);
$g5->move(1, 2, 0, 3);
$brd = $g5->board();
ok($brd[0][3] === 'WK', "Hvid brik promoveres til konge (WK) ved række 0");

// Test 8: Sort promovering ved række 7.
$b8 = makeBoard();
$b8[6][1] = 'B';
$g = gameFrom($b8, 'B');
$g->move(6, 1, 7, 0);
$brd = $g->board();
ok($brd[7][0] === 'BK', "Sort brik promoveres til konge (BK) ved række 7");

// Test 9: KONGE glider diagonalt (vilkårlig afstand) — simpelt træk.
$b9 = makeBoard();
$b9[4][4] = 'WK';
$g6 = gameFrom($b9);
$legal = $g6->legalMoves();
$targets = $legal['4,4'] ?? [];
ok(in_array('2,2', $targets, true), "Konge glider til (2,2)");
ok(in_array('1,1', $targets, true), "Konge glider til (1,1)");
ok(in_array('0,0', $targets, true), "Konge glider til (0,0)");
ok(in_array('6,6', $targets, true), "Konge glider nedad til (6,6)");
ok(in_array('5,3', $targets, true), "Konge glider nedad-anden diagonal (5,3)");

// Test 10: Konge kan IKKE glide igennem en brik.
$b10 = makeBoard();
$b10[4][4] = 'WK';
$b10[2][2] = 'B';   // blokerer diagonal op-venstre fra (4,4)
$g7 = gameFrom($b10);
$legal = $g7->legalMoves();
$targets = $legal['4,4'] ?? [];
ok(in_array('3,3', $targets, true), "Konge kan til (3,3) før blokaden");
ok(!in_array('1,1', $targets, true), "Konge kan IKKE passere blokerende brik til (1,1)");
ok(!in_array('0,0', $targets, true), "Konge kan IKKE passere blokerende brik til (0,0)");

// Test 11: Flyvende konge-slag (hop over modstander, land på afstand).
$b11 = makeBoard();
$b11[4][4] = 'WK';
$b11[2][2] = 'B';   // modstander-brik på diagonalen
$g8 = gameFrom($b11);
$legal = $g8->legalMoves();
$targets = $legal['4,4'] ?? [];
ok(in_array('1,1', $targets, true), "Flyvende konge slår og lander på (1,1)");
ok(in_array('0,0', $targets, true), "Flyvende konge slår og lander på (0,0)");

// Test 12: Udfør flyvende konge-slag og verificér at brikken fjernes.
$g8->move(4, 4, 0, 0);
$brd = $g8->board();
ok($brd[4][4] === '', "Kongen er flyttet fra (4,4)");
ok($brd[0][0] === 'WK', "Kongen landet på (0,0)");
ok($brd[2][2] === '', "Slået modstander-brik (2,2) fjernet");
ok($g8->turn() === 'B', "Efter konge-slag (ikke kaskade) er det sorts tur");

// Test 13: Tvunget slag gælder også for konger.
$b13 = makeBoard();
$b13[4][4] = 'WK';
$b13[4][6] = 'W';     // anden hvid brik uden slag-mulighed
$b13[2][2] = 'B';     // kongen kan slå
$g9 = gameFrom($b13);
$legal = $g9->legalMoves();
ok(isset($legal['4,4']), "Konge med slag-mulighed er lovlig");
ok(!isset($legal['4,6']), "Brik uden slag er ulovlig når konge har tvunget slag");

// Test 14: Vinder når modstander ingen brikker har.
$b14 = makeBoard();
$b14[7][0] = 'W';
$g10 = gameFrom($b14, 'B');
$legalB = $g10->legalMoves();
ok(count($legalB) === 0, "Sort med ingen brikker har ingen lovlige træk");

// Test 15: Round-trip bevarer tilstand.
$g = Game::create();
$g->move(5, 0, 4, 1);
$state = $g->toArray();
$g11 = Game::fromArray($state);
ok($g11->turn() === $g->turn(), "Round-trip bevarer tur");
ok($g11->board() === $g->board(), "Round-trip bevarer bræt");

echo "\n";
if ($failures === 0) {
    echo "ALLE TESTS BESTÅET\n";
    exit(0);
} else {
    echo "$failures FEJL\n";
    exit(1);
}
