<?php

namespace Tests\Feature;

use Tests\TestCase;

class HiddenItemCommandTest extends TestCase
{
    public function test_hidden_item_command_outputs_coordinates_and_bonus_grid(): void
    {
        $this->artisan('hidden-item:solve --up=3 --right=4 --down=2')
            ->expectsOutput('Hidden Item Solver')
            ->expectsOutput('Start position: (5, 2)')
            ->expectsOutput('Probable item coordinates, using 1-based (row, column):')
            ->expectsOutput('- (4, 2)')
            ->expectsOutput('- (2, 6)')
            ->expectsOutput('- (4, 6)')
            ->expectsOutput('Grid with probable item locations:')
            ->expectsOutput('########')
            ->expectsOutput('#$$$$$.#')
            ->expectsOutput('#$###$.#')
            ->expectsOutput('#$..#$##')
            ->expectsOutput('#X#....#')
            ->expectsOutput('########')
            ->assertSuccessful();
    }

    public function test_hidden_item_command_requires_all_movement_options(): void
    {
        $this->artisan('hidden-item:solve --up=3 --right=4')
            ->expectsOutput('Please provide --up, --right, and --down step counts.')
            ->expectsOutput('Example: php artisan hidden-item:solve --up=3 --right=4 --down=2')
            ->assertFailed();
    }

    public function test_hidden_item_command_rejects_negative_steps(): void
    {
        $this->artisan('hidden-item:solve --up=-1 --right=4 --down=2')
            ->expectsOutput('The --up option must be a non-negative integer.')
            ->assertFailed();
    }
}
