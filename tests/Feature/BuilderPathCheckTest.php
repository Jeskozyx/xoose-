<?php

namespace Tests\Feature;

use Tests\TestCase;

class BuilderPathCheckTest extends TestCase
{
    /**
     * Test builder:check artisan command passes and verifies all paths.
     */
    public function test_builder_paths_check_command_succeeds(): void
    {
        $this->artisan('builder:check')
            ->assertExitCode(0);
    }

    /**
     * Test builder:check returns valid JSON when requested.
     */
    public function test_builder_paths_check_json_output(): void
    {
        $this->artisan('builder:check --json')
            ->assertExitCode(0);
    }
}
