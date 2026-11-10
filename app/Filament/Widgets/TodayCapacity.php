<?php

namespace App\Filament\Widgets;

use App\Models\DailyCapacity;
use App\Models\DashboardSetting;
use App\Services\DailyCapacityCalculator;
use App\Services\DashboardClock;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class TodayCapacity extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    public string $defaultFocusMinutes = '240';

    public string $dailyFocusMinutes = '240';

    protected string $view = 'filament.widgets.today-capacity';

    public function mount(): void
    {
        $this->loadFocusSettings();
    }

    #[On('dashboard-data-changed')]
    public function refreshCapacity(): void
    {
        $this->loadFocusSettings();
    }

    public function saveDefaultFocus(): void
    {
        $validated = $this->validate([
            'defaultFocusMinutes' => ['required', 'integer', 'min:0'],
        ]);

        DashboardSetting::query()->findOrFail(1)->update([
            'default_focus_minutes' => (int) $validated['defaultFocusMinutes'],
        ]);

        $this->loadFocusSettings();
        Notification::make()->title('Default focus time saved')->success()->send();
        $this->dispatch('dashboard-data-changed');
    }

    public function saveDailyFocus(): void
    {
        $validated = $this->validate([
            'dailyFocusMinutes' => ['required', 'integer', 'min:0'],
        ]);

        DailyCapacity::query()->updateOrCreate(
            ['date' => app(DashboardClock::class)->today()->toDateString()],
            ['focus_minutes' => (int) $validated['dailyFocusMinutes']],
        );

        $this->loadFocusSettings();
        Notification::make()->title('Today’s focus time saved')->success()->send();
        $this->dispatch('dashboard-data-changed');
    }

    public function resetDailyFocus(): void
    {
        $this->resetValidation('dailyFocusMinutes');

        DailyCapacity::query()
            ->whereDate('date', app(DashboardClock::class)->today()->toDateString())
            ->delete();

        $this->loadFocusSettings();
        Notification::make()->title('Using the default focus time')->success()->send();
        $this->dispatch('dashboard-data-changed');
    }

    /** @return array<string, mixed> */
    public function getSummaryProperty(): array
    {
        return app(DailyCapacityCalculator::class)->forDate(app(DashboardClock::class)->today());
    }

    private function loadFocusSettings(): void
    {
        $date = app(DashboardClock::class)->today()->toDateString();
        $this->defaultFocusMinutes = (string) DashboardSetting::query()->findOrFail(1)->default_focus_minutes;
        $this->dailyFocusMinutes = (string) (
            DailyCapacity::query()->whereDate('date', $date)->value('focus_minutes')
            ?? $this->defaultFocusMinutes
        );
    }
}
