<?php
declare(strict_types=1);

/**
 * Spilmotor for netværksbaseret Dam (Checkers).
 *
 * Regler (kort):
 *  - 8x8 bræt, spillere spiller på mørke felter.
 *  - Hvid (W) starter nederst og rykker "op" (rækkenummer stiger).
 *  - Sort (B) starter øverst og rykker "ned" (rækkenummer falder).
 *  - Almindelige brikker rykker 1 felt diagonalt fremad.
 *  - Konger (WK/BK) rykker 1 felt diagonalt i alle retninger.
 *  - Slag foregår ved at hoppe over en modstander-brik til et tomt felt bagved.
 *    Et slag KAN være del af en kaskade: hvis en brik lige har slået og kan slå
 *    igen, fortsætter samme brik indtil ingen flere slag er mulige fra dens felt.
 *  - Tvunget slag: hvis mindst ét slag er muligt for en spiller, SKAL spilleren slå.
 *  - Promovering: når en brik når bagste række bliver den konge. Promovering
 *    afslutter brikken tur (den kan ikke fortsætte kaskade-slå efter promovering).
 *  - Vinder: modstanderen har ingen brikker, eller modstanderen ikke kan flytte.
 */

final class Game
{
    public const SIZE = 8;
    public const EMPTY = '';

    /** @var array<int, array<int,string>> $board række => kolonne => 'W'|'B'|'WK'|'BK'|'' */
    private array $board;

    private string $turn = 'W';          // 'W' eller 'B'
    private ?string $winner = null;      // 'W' | 'B' | null
    private ?string $continuing = null;  // felt "r,c" for en brik midt i en kaskade, ellers null
    private int $moveCount = 0;

    private function __construct(array $board, string $turn, ?string $winner, ?string $continuing, int $moveCount)
    {
        $this->board = $board;
        $this->turn = $turn;
        $this->winner = $winner;
        $this->continuing = $continuing;
        $this->moveCount = $moveCount;
    }

    public static function create(): self
    {
        $b = [];
        for ($r = 0; $r < self::SIZE; $r++) {
            $b[$r] = array_fill(0, self::SIZE, self::EMPTY);
        }
        // Sort (top) på række 0,1,2; Hvid (bund) på række 5,6,7. Kun mørke felter:
        // mørkt felt når (r+c) er ulige.
        for ($r = 0; $r < 3; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                if (($r + $c) % 2 === 1) {
                    $b[$r][$c] = 'B';
                }
            }
        }
        for ($r = 5; $r < 8; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                if (($r + $c) % 2 === 1) {
                    $b[$r][$c] = 'W';
                }
            }
        }
        return new self($b, 'W', null, null, 0);
    }

    public static function fromArray(array $state): self
    {
        return new self(
            $state['board'] ?? [],
            $state['turn'] ?? 'W',
            $state['winner'] ?? null,
            $state['continuing'] ?? null,
            $state['moveCount'] ?? 0
        );
    }

    public function toArray(): array
    {
        return [
            'board'       => $this->board,
            'turn'        => $this->turn,
            'winner'      => $this->winner,
            'continuing'  => $this->continuing,
            'moveCount'   => $this->moveCount,
        ];
    }

    public function turn(): string
    {
        return $this->turn;
    }

    public function winner(): ?string
    {
        return $this->winner;
    }

    public function board(): array
    {
        return $this->board;
    }

    public function continuing(): ?string
    {
        return $this->continuing;
    }

    /**
     * Returner alle lovlige træk for den aktuelle spiller som [from => [to,...]].
     * Hvis slag er mulige returneres KUN slag (tvunget slag).
     *
     * @return array<string, array<int,string>>
     */
    public function legalMoves(): array
    {
        $captures = [];
        $moves = [];

        foreach ($this->board as $r => $row) {
            foreach ($row as $c => $piece) {
                if ($piece === '' || $piece[0] !== $this->turn) {
                    continue;
                }
                $from = "$r,$c";
                if ($this->continuing !== null && $this->continuing !== $from) {
                    // Midt i en kaskade: kun den aktuelle brik må fortsætte.
                    continue;
                }
                [$caps, $mvs] = $this->movesForPiece($r, $c, $piece);
                foreach ($caps as $to) {
                    $captures[$from][] = $to;
                }
                foreach ($mvs as $to) {
                    $moves[$from][] = $to;
                }
            }
        }

        return count($captures) > 0 ? $captures : $moves;
    }

    /**
     * @return array{0: list<string>, 1: list<string>} [captures, simpleMoves]
     */
    private function movesForPiece(int $r, int $c, string $piece): array
    {
        $isKing = strlen($piece) === 2;
        $directions = $this->directions($piece, $isKing);

        $captures = [];
        $simple = [];

        foreach ($directions as [$dr, $dc]) {
            $midR = $r + $dr;
            $midC = $c + $dc;
            $landR = $r + 2 * $dr;
            $landC = $c + 2 * $dc;
            if ($this->inBounds($landR, $landC)) {
                $mid = $this->board[$midR][$midC] ?? '';
                $land = $this->board[$landR][$landC] ?? '';
                if ($mid !== '' && $mid[0] !== $this->turn && $land === '') {
                    $captures[] = "$landR,$landC";
                }
            }
            // Enkelt træk fremad/side for konger, kun fremad for almindelige.
            $oneR = $r + $dr;
            $oneC = $c + $dc;
            if ($this->inBounds($oneR, $oneC) && $this->board[$oneR][$oneC] === '') {
                // For almindelige brikker tillader vi kun "fremad" simple træk
                // (directions() sikrer det allerede for menige; for konge er det alle 4).
                $simple[] = "$oneR,$oneC";
            }
        }

        return [$captures, $simple];
    }

    /**
     * @return list<array{0:int,1:int}>
     */
    private function directions(string $piece, bool $isKing): array
    {
        if ($isKing) {
            return [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        }
        // Menig hvid rykker op (+1), sort ned (-1).
        return $piece[0] === 'W'
            ? [[1, -1], [1, 1]]
            : [[-1, -1], [-1, 1]];
    }

    private function inBounds(int $r, int $c): bool
    {
        return $r >= 0 && $r < self::SIZE && $c >= 0 && $c < self::SIZE;
    }

    private function piece(int $r, int $c): string
    {
        return $this->board[$r][$c] ?? '';
    }

    /**
     * Udfør et træk. Kaster InvalidArgumentException ved ulovligt træk.
     * Håndterer slag (fjern midterste brik), kaskade, promovering og tur-skift.
     */
    public function move(int $fromR, int $fromC, int $toR, int $toC): void
    {
        if ($this->winner !== null) {
            throw new \InvalidArgumentException('Spillet er slut');
        }
        $legal = $this->legalMoves();
        $from = "$fromR,$fromC";
        $to = "$toR,$toC";
        if (!isset($legal[$from]) || !in_array($to, $legal[$from], true)) {
            throw new \InvalidArgumentException('Ulovligt træk');
        }

        $piece = $this->board[$fromR][$fromC];
        $this->board[$fromR][$fromC] = '';

        $isCapture = (abs($toR - $fromR) === 2);
        if ($isCapture) {
            $midR = $fromR + ($toR - $fromR) / 2;
            $midC = $fromC + ($toC - $fromC) / 2;
            $this->board[$midR][$midC] = '';
        }

        // Promovering ved bagste række.
        $promoted = false;
        if (strlen($piece) === 1) {
            if (($piece === 'W' && $toR === self::SIZE - 1) || ($piece === 'B' && $toR === 0)) {
                $piece .= 'K';
                $promoted = true;
            }
        }
        $this->board[$toR][$toC] = $piece;
        $this->moveCount++;

        // Kaskade: hvis det var et slag, ikke promoveret, og brikken kan slå igen,
        // forbliver turen hos samme brik.
        if ($isCapture && !$promoted) {
            [$caps,] = $this->movesForPiece($toR, $toC, $piece);
            if (count($caps) > 0) {
                $this->continuing = "$toR,$toC";
                return; // samme spillers tur fortsætter
            }
        }

        $this->continuing = null;
        $this->endTurn();
    }

    private function endTurn(): void
    {
        $this->turn = $this->turn === 'W' ? 'B' : 'W';
        $this->checkWinner();
    }

    private function checkWinner(): void
    {
        // Ingen brikker tilbage?
        $counts = ['W' => 0, 'B' => 0];
        foreach ($this->board as $row) {
            foreach ($row as $piece) {
                if ($piece !== '') {
                    $counts[$piece[0]]++;
                }
            }
        }
        if ($counts['W'] === 0) {
            $this->winner = 'B';
            return;
        }
        if ($counts['B'] === 0) {
            $this->winner = 'W';
            return;
        }
        // Ingen lovlige træk => modstander vinder.
        if (count($this->legalMoves()) === 0) {
            $this->winner = $this->turn === 'W' ? 'B' : 'W';
        }
    }
}
