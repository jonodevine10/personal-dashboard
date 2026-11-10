<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_login_and_registration_is_not_available(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Personal Dashboard');
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_the_single_owner_can_access_the_dashboard(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $unprovisioned = new User;
        $unprovisioned->id = 2;
        $this->assertFalse($unprovisioned->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_filament_login_authenticates_the_provisioned_owner(): void
    {
        $user = User::factory()->create(['password' => 'A-long-test-password']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'A-long-test-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_is_provisioned_interactively_with_a_hashed_password(): void
    {
        $this->artisan('dashboard:user')
            ->expectsQuestion('Name', 'Dashboard Owner')
            ->expectsQuestion('Email', 'owner@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'A-long-test-password')
            ->expectsQuestion('Confirm password', 'A-long-test-password')
            ->expectsOutput('Account created. Sign in at /admin.')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame(1, $user->id);
        $this->assertTrue(Hash::check('A-long-test-password', $user->password));
        $this->artisan('dashboard:user')
            ->expectsOutput('An account already exists. This dashboard supports only one user.')
            ->assertFailed();
    }

    public function test_provisioning_rejects_invalid_input_without_creating_an_account(): void
    {
        $this->artisan('dashboard:user')
            ->expectsQuestion('Name', 'Dashboard Owner')
            ->expectsQuestion('Email', 'owner@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->expectsOutput('The password field must be at least 12 characters.')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
