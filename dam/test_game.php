<?php
declare(strict_types=1);

/**
 * Selvtjekkende test af spilmotoren (international dam, land-bagved-regel).
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

// Test 1: Startposition — 12 brikker pr. farve.
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
ok($g4->continuing() === null && $g4->turn() === 'B', "Efter kaskade er det sorts tur");

// Test 7: Promovering — hvid ved række 0.
$b7 = makeBoard();
$b7[1][2] = 'W';
$g5 = gameFrom($b7);
$g5->move(1, 2, 0, 3);
ok($g5->board()[0][3] === 'WK', "Hvid brik promoveres til Dam (WK) ved række 0");

// Test 8: Sort promovering ved række 7.
$b8 = makeBoard();
$b8[6][1] = 'B';
$g = gameFrom($b8, 'B');
$g->move(6, 1, 7, 0);
ok($g->board()[7][0] === 'BK', "Sort brik promoveres til Dam (BK) ved række 7");

// Test 9: Dam glider diagonalt (simpelt træk).
$b9 = makeBoard();
$b9[4][4] = 'WK';
$g6 = gameFrom($b9);
$targets = $g6->legalMoves()['4,4'] ?? [];
ok(in_array('2,2', $targets, true), "Dam glider til (2,2)");
ok(in_array('1,1', $targets, true), "Dam glider til (1,1)");
ok(in_array('0,0', $targets, true), "Dam glider til (0,0)");
ok(in_array('6,6', $targets, true), "Dam glider nedad til (6,6)");

// Test 10: Dam kan IKKE glide igennem en brik.
$b10 = makeBoard();
$b10[4][4] = 'WK';
$b10[2][2] = 'B';
$g7 = gameFrom($b10);
$targets = $g7->legalMoves()['4,4'] ?? [];
ok(in_array('3,3', $targets, true), "Dam kan til (3,3) før blokaden");
ok(!in_array('1,1', $targets, true), "Dam kan IKKE passere blokerende brik til (1,1)");

// Test 11: Dam-slag — SKAL lande lige bagved den slåede brik (streng regel).
// Dam på (4,4), modstander på (2,2). Landing SKAL være (1,1) — IKKE (0,0).
$b11 = makeBoard();
$b11[4][4] = 'WK';
$b11[2][2] = 'B';
$g8 = gameFrom($b11);
$targets = $g8->legalMoves()['4,4'] ?? [];
ok(in_array('1,1', $targets, true), "Dam slår og lander LIGE bagved på (1,1)");
ok(!in_array('0,0', $targets, true), "Dam må IKKE lande længere ude på (0,0) (streng regel)");

// Test 12: Udfør Dam-slag og verificér at brikken fjernes.
$g8->move(4, 4, 1, 1);
$brd = $g8->board();
ok($brd[4][4] === '', "Dammen flyttet fra (4,4)");
ok($brd[1][1] === 'WK', "Dammen landet på (1,1)");
ok($brd[2][2] === '', "Slået modstander-brik (2,2) fjernet");
ok($g8->turn() === 'B', "Efter Dam-slag (ikke kaskade) er det sorts tur");

// Test 13: Tvunget slag gælder også for Dam.
$b13 = makeBoard();
$b13[4][4] = 'WK';
$b13[4][6] = 'W';
$b13[2][2] = 'B';
$g9 = gameFrom($b13);
$legal = $g9->legalMoves();
ok(isset($legal['4,4']), "Dam med slag-mulighed er lovlig");
ok(!isset($legal['4,6']), "Brik uden slag er ulovlig når Dam har tvunget slag");

// Test 14: Kaskade for Dam — slag i én retning, så slag i en anden retning.
// Dam (4,4) slår B(2,2)->(1,1); derfra kan den slå B(0,3) diagonalt ned-højre? Nej,
// (1,1)->(0,3) er ikke diagonal. Brug korrekt opsætning: Dam (4,4) slår B(2,2) lander (1,1),
// så skal slå B på diagonal fra (1,1). Sæt B(2,4) så (1,1)->slå til (3,5)? heller ikke diagonal.
// Enklere: Dam (4,0) slår B(2,2)->(1,3), så B(3,5) diagonal ned-højre fra (1,3)->(5,7)? nej diagonal.
// Hold det enkelt: verificér at efter Dam-slag, hvis nyt slag muligt, fortsætter turen.
$b14 = makeBoard();
$b14[5][5] = 'WK';
$b14[3][3] = 'B';   // dam slår op-venstre: (5,5)->slå B(3,3)->(2,2)
$b14[1][1] = 'B';   // fra (2,2) kan slå B(1,1)? Nej, (1,1) er 1 felt diagonalt fra (2,2),
                    // men landingsfelt (0,0) er tomt -> slag muligt -> kaskade.
$g10 = gameFrom($b14);
$g10->move(5, 5, 2, 2);  // første slag
ok($g10->continuing() === '2,2', "Efter Dam-slag er continuing sat til (2,2)");
ok($g10->turn() === 'W', "Det er stadig hvids tur (kaskade)");
$legal = $g10->legalMoves();
ok(in_array('0,0', $legal['2,2'] ?? [], true), "Kaskade: Dam kan slå igen til (0,0)");
$g10->move(2, 2, 0, 0);
ok($g10->continuing() === null && $g10->turn() === 'B', "Efter kaskade slut er det sorts tur");

// Test 15: Vinder når modstander ingen brikker har.
$b15 = makeBoard();
$b15[7][0] = 'W';
$g11 = gameFrom($b15, 'B');
ok(count($g11->legalMoves()) === 0, "Sort med ingen brikker har ingen lovlige træk");

// Test 16: Round-trip bevarer tilstand.
$g = Game::create();
$g->move(5, 0, 4, 1);
$state = $g->toArray();
$g12 = Game::fromArray($state);
ok($g12->turn() === $g->turn() && $g12->board() === $g->board(), "Round-trip bevarer tilstand");

echo "\n";
if ($failures === 0) {
    echo "ALLE TESTS BESTÅET\n";
    exit(0);
} else {
    echo "$failures FEJL\n";
    exit(1);
}
