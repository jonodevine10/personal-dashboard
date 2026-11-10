<?php

namespace App\Filament\Widgets;

use App\Models\InboxItem;
use App\Services\CommitmentOccurrences;
use App\Services\DashboardClock;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class TodayCommitments extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    public int $inboxCount = 0;

    protected string $view = 'filament.widgets.today-commitments';

    public function mount(): void
    {
        $this->refreshInboxCount();
    }

    #[On('dashboard-data-changed')]
    public function refreshInboxCount(): void
    {
        $this->inboxCount = InboxItem::query()->whereNull('triaged_at')->count();
    }

    /** @return list<array<string, mixed>> */
    public function getOccurrences(): array
    {
        return app(CommitmentOccurrences::class)->forDate(app(DashboardClock::class)->today());
    }
}
