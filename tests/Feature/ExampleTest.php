<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_settings_requires_admin_role(): void
    {
        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/settings')->assertForbidden();
    }
}
