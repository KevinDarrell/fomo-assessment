<?php

namespace App\HiddenItem;

use InvalidArgumentException;

class HiddenItemSolver
{
    /**
     * @var list<string>
     */
    private const GRID = [
        '########',
        '#......#',
        '#.###..#',
        '#...#.##',
        '#X#....#',
        '########',
    ];

    /**
     * @return array{start: array{row: int, column: int}, probable: list<array{row: int, column: int}>, grid: list<string>}
     */
    public function solve(int $up, int $right, int $down): array
    {
        foreach (['up' => $up, 'right' => $right, 'down' => $down] as $direction => $steps) {
            if ($steps < 0) {
                throw new InvalidArgumentException("The {$direction} step count must be zero or greater.");
            }
        }

        $grid = $this->parseGrid();
        $position = $this->findStart($grid);
        $probable = [];

        foreach ([[-1, 0, $up], [0, 1, $right], [1, 0, $down]] as [$rowDelta, $columnDelta, $steps]) {
            for ($step = 0; $step < $steps; $step++) {
                $next = [
                    'row' => $position['row'] + $rowDelta,
                    'column' => $position['column'] + $columnDelta,
                ];

                if (! $this->isClearPath($grid, $next['row'], $next['column'])) {
                    break;
                }

                $position = $next;
                $probable[$this->coordinateKey($position)] = $position;
            }
        }

        return [
            'start' => $this->toOneBased($this->findStart($grid)),
            'probable' => array_map(fn (array $coordinate): array => $this->toOneBased($coordinate), array_values($probable)),
            'grid' => $this->renderProbableLocations($grid, array_values($probable)),
        ];
    }

    /**
     * @return list<list<string>>
     */
    private function parseGrid(): array
    {
        return array_map(str_split(...), self::GRID);
    }

    /**
     * @param  list<list<string>>  $grid
     * @return array{row: int, column: int}
     */
    private function findStart(array $grid): array
    {
        foreach ($grid as $row => $cells) {
            foreach ($cells as $column => $cell) {
                if ($cell === 'X') {
                    return ['row' => $row, 'column' => $column];
                }
            }
        }

        throw new InvalidArgumentException('The grid does not contain a starting position.');
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private function isClearPath(array $grid, int $row, int $column): bool
    {
        return isset($grid[$row][$column]) && $grid[$row][$column] === '.';
    }

    /**
     * @param  array{row: int, column: int}  $coordinate
     */
    private function coordinateKey(array $coordinate): string
    {
        return $coordinate['row'].':'.$coordinate['column'];
    }

    /**
     * @param  array{row: int, column: int}  $coordinate
     * @return array{row: int, column: int}
     */
    private function toOneBased(array $coordinate): array
    {
        return [
            'row' => $coordinate['row'] + 1,
            'column' => $coordinate['column'] + 1,
        ];
    }

    /**
     * @param  list<list<string>>  $grid
     * @param  list<array{row: int, column: int}>  $probableLocations
     * @return list<string>
     */
    private function renderProbableLocations(array $grid, array $probableLocations): array
    {
        foreach ($probableLocations as $coordinate) {
            $grid[$coordinate['row']][$coordinate['column']] = '$';
        }

        return array_map(fn (array $row): string => implode('', $row), $grid);
    }
}
