<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_root_identifies_modrik(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertExactJson([
                'name' => 'MODRIK',
                'status' => 'bootstrap',
            ]);
    }

    public function test_health_contract_is_available(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
