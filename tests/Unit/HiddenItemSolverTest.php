<?php

namespace Tests\Unit;

use App\HiddenItem\HiddenItemSolver;
use PHPUnit\Framework\TestCase;

class HiddenItemSolverTest extends TestCase
{
    public function test_it_returns_probable_coordinates_and_bonus_grid(): void
    {
        $result = (new HiddenItemSolver)->solve(up: 3, right: 4, down: 2);

        $this->assertSame(['row' => 5, 'column' => 2], $result['start']);
        $this->assertSame([
            ['row' => 4, 'column' => 2],
            ['row' => 3, 'column' => 2],
            ['row' => 2, 'column' => 2],
            ['row' => 2, 'column' => 3],
            ['row' => 2, 'column' => 4],
            ['row' => 2, 'column' => 5],
            ['row' => 2, 'column' => 6],
            ['row' => 3, 'column' => 6],
            ['row' => 4, 'column' => 6],
        ], $result['probable']);
        $this->assertSame([
            '########',
            '#$$$$$.#',
            '#$###$.#',
            '#$..#$##',
            '#X#....#',
            '########',
        ], $result['grid']);
    }

    public function test_it_stops_when_a_movement_hits_an_obstacle(): void
    {
        $result = (new HiddenItemSolver)->solve(up: 1, right: 3, down: 1);

        $this->assertSame([
            ['row' => 4, 'column' => 2],
            ['row' => 4, 'column' => 3],
            ['row' => 4, 'column' => 4],
            ['row' => 5, 'column' => 4],
        ], $result['probable']);
    }
}
