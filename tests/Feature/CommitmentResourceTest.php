<?php

namespace Tests\Feature;

use App\Filament\Resources\Commitments\Pages\ManageCommitments;
use App\Models\Commitment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommitmentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_disabled_placeholders_are_managed_and_visible_in_the_commitment_resource(): void
    {
        $this->get('/admin/commitments')->assertOk()->assertSee('Early shifts');
        $this->assertDatabaseCount('commitments', 2);
        $this->assertFalse(Commitment::where('title', 'like', 'Early shifts%')->firstOrFail()->is_enabled);
    }

    public function test_commitment_resource_can_configure_and_enable_a_placeholder(): void
    {
        $commitment = Commitment::where('title', 'like', 'Early shifts%')->firstOrFail();

        Livewire::test(ManageCommitments::class)
            ->callTableAction('edit', $commitment, [
                'area_id' => (string) $commitment->area_id,
                'title' => 'Early shifts',
                'days_of_week' => ['1', '3', '5', '6'],
                'start_time' => '04:00',
                'end_time' => '08:00',
                'is_enabled' => true,
            ])
            ->assertHasNoTableActionErrors();

        $commitment->refresh();
        $this->assertTrue($commitment->is_enabled);
        $this->assertSame([1, 3, 5, 6], $commitment->days_of_week);
    }
}
