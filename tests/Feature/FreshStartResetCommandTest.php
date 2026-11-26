<?php

namespace Tests\Feature;

use App\Console\Commands\FreshStartResetCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreshStartResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_start_command_is_prohibited_when_prohibitable_is_enabled(): void
    {
        FreshStartResetCommand::prohibit(true);

        try {
            $this->artisan('data:fresh-start', ['--force' => true])
                ->assertExitCode(1);
        } finally {
            FreshStartResetCommand::prohibit(false);
        }
    }
}
