<?php

use App\HiddenItem\HiddenItemSolver;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hidden-item:solve {--up= : Number of north/up steps} {--right= : Number of east/right steps} {--down= : Number of south/down steps}', function (HiddenItemSolver $solver): int {
    $up = $this->option('up');
    $right = $this->option('right');
    $down = $this->option('down');

    if ($up === null || $right === null || $down === null) {
        $this->error('Please provide --up, --right, and --down step counts.');
        $this->line('Example: php artisan hidden-item:solve --up=3 --right=4 --down=2');

        return self::FAILURE;
    }

    foreach (['up' => $up, 'right' => $right, 'down' => $down] as $name => $value) {
        if (! is_numeric($value) || (int) $value < 0 || (string) (int) $value !== (string) $value) {
            $this->error("The --{$name} option must be a non-negative integer.");

            return self::FAILURE;
        }
    }

    $result = $solver->solve((int) $up, (int) $right, (int) $down);

    $this->info('Hidden Item Solver');
    $this->line("Start position: ({$result['start']['row']}, {$result['start']['column']})");
    $this->newLine();

    $this->line('Probable item coordinates, using 1-based (row, column):');

    if ($result['probable'] === []) {
        $this->warn('No probable coordinates found. The movement is blocked immediately.');
    } else {
        foreach ($result['probable'] as $coordinate) {
            $this->line("- ({$coordinate['row']}, {$coordinate['column']})");
        }
    }

    $this->newLine();
    $this->line('Grid with probable item locations:');

    foreach ($result['grid'] as $row) {
        $this->line($row);
    }

    return self::SUCCESS;
})->purpose('Solve the hidden item grid movement puzzle');
