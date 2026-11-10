<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_authenticated_header_capture_creates_an_untriaged_item_and_clears_the_field(): void
    {
        Livewire::test(QuickCapture::class)
            ->set('text', 'Book a study room')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('text', '');

        $this->assertDatabaseHas('inbox_items', [
            'text' => 'Book a study room',
            'triaged_at' => null,
        ]);
    }

    public function test_capture_rejects_empty_and_overlong_text(): void
    {
        Livewire::test(QuickCapture::class)
            ->set('text', '   ')
            ->call('save')
            ->assertHasErrors(['text']);

        Livewire::test(QuickCapture::class)
            ->set('text', str_repeat('x', 5001))
            ->call('save')
            ->assertHasErrors(['text']);

        $this->assertDatabaseCount('inbox_items', 0);
    }

    public function test_header_capture_is_rendered_on_the_authenticated_dashboard_and_resource_pages(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Capture to Inbox');
        $this->get('/admin/inbox-items')->assertOk()->assertSee('Capture to Inbox');
        $this->get('/admin/commitments')->assertOk()->assertSee('Capture to Inbox');
    }
}
