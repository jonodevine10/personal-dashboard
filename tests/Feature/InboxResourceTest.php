<?php

namespace Tests\Feature;

use App\Actions\CreateProject;
use App\Enums\ProjectStatus;
use App\Filament\Resources\InboxItems\Pages\ManageInboxItems;
use App\Models\Area;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboxResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_inbox_defaults_to_untriaged_items_and_shows_triage_actions(): void
    {
        $pending = InboxItem::create(['text' => 'Waiting to be sorted']);
        $triaged = InboxItem::create(['text' => 'Already sorted', 'triaged_at' => now()]);

        Livewire::test(ManageInboxItems::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$triaged])
            ->assertTableActionExists('triageTask', null, $pending)
            ->assertTableActionExists('triageProjectNote', null, $pending)
            ->assertTableActionExists('triageParkingLot', null, $pending);
    }

    public function test_inbox_task_action_uses_the_captured_text_and_saves_a_task(): void
    {
        $item = InboxItem::create(['text' => 'Book a room']);

        Livewire::test(ManageInboxItems::class)
            ->callTableAction('triageTask', $item)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Book a room',
            'tier' => 'should',
            'scheduled_for' => null,
            'status' => 'pending',
        ]);
        $this->assertNotNull($item->fresh()->triaged_at);
    }

    public function test_inbox_project_note_action_appends_to_the_selected_project(): void
    {
        $project = $this->createProject();
        $item = InboxItem::create(['text' => 'Ask about the draft']);

        Livewire::test(ManageInboxItems::class)
            ->callTableAction('triageProjectNote', $item, ['project_id' => $project->id])
            ->assertHasNoTableActionErrors();

        $this->assertStringContainsString('Existing notes', $project->fresh()->notes);
        $this->assertStringContainsString('Ask about the draft', $project->fresh()->notes);
        $this->assertNotNull($item->fresh()->triaged_at);
    }

    public function test_inbox_parking_action_requires_confirmation_and_creates_a_paused_project(): void
    {
        $item = InboxItem::create(['text' => 'Maybe build a tiny tool']);

        Livewire::test(ManageInboxItems::class)
            ->assertTableActionExists('triageParkingLot', null, $item)
            ->callTableAction('triageParkingLot', $item);

        $this->assertDatabaseHas('projects', [
            'title' => 'Maybe build a tiny tool',
            'status' => 'paused',
        ]);
        $this->assertNotNull($item->fresh()->triaged_at);
    }

    public function test_triaged_history_filter_exposes_triaged_items(): void
    {
        $pending = InboxItem::create(['text' => 'Pending']);
        $triaged = InboxItem::create(['text' => 'Triaged', 'triaged_at' => now()]);

        Livewire::test(ManageInboxItems::class)
            ->filterTable('triage_state', 'triaged')
            ->assertCanSeeTableRecords([$triaged])
            ->assertCanNotSeeTableRecords([$pending])
            ->assertTableActionHidden('triageTask', $triaged);
    }

    private function createProject(): Project
    {
        return app(CreateProject::class)->handle([
            'area_id' => Area::where('name', 'Dissertation')->value('id'),
            'title' => 'Dissertation',
            'status' => ProjectStatus::Active,
            'notes' => 'Existing notes',
        ], ['title' => 'Existing next action']);
    }
}
