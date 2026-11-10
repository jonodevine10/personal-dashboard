<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $summary = $this->summary;
            $percent = $summary['focus_minutes'] > 0
                ? min(100, (int) floor($summary['estimated_minutes'] / $summary['focus_minutes'] * 100))
                : ($summary['estimated_minutes'] > 0 ? 100 : 0);
        @endphp

        <x-slot name="heading">Focus capacity</x-slot>
        <x-slot name="description">{{ now(config('dashboard.timezone'))->format('l, F j') }}</x-slot>

        <div class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ intdiv($summary['estimated_minutes'], 60) }}h {{ $summary['estimated_minutes'] % 60 }}m estimated
                    of {{ intdiv($summary['focus_minutes'], 60) }}h {{ $summary['focus_minutes'] % 60 }}m focus time
                </p>
                @if ($summary['is_over_capacity'])
                    <p class="text-sm font-medium text-danger-600 dark:text-danger-400">
                        {{ $summary['estimated_minutes'] - $summary['focus_minutes'] }}m over capacity
                    </p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ intdiv($summary['remaining_minutes'], 60) }}h {{ $summary['remaining_minutes'] % 60 }}m available
                    </p>
                @endif
            </div>

            <div
                class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                role="progressbar"
                aria-label="Estimated tasks compared with today's focus capacity"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $percent }}"
            >
                <div
                    class="{{ $summary['is_over_capacity'] ? 'bg-danger-500' : 'bg-primary-500' }} h-full rounded-full transition-all"
                    style="width: {{ $percent }}%"
                ></div>
            </div>

            @if ($summary['missing_estimate_count'] > 0)
                <p class="text-sm text-warning-600 dark:text-warning-400">
                    {{ $summary['missing_estimate_count'] }} scheduled
                    {{ \Illuminate\Support\Str::plural('task', $summary['missing_estimate_count']) }}
                    {{ $summary['missing_estimate_count'] === 1 ? 'needs' : 'need' }} an estimate.
                </p>
            @endif

            <div class="grid gap-3 border-t border-gray-200 pt-3 dark:border-gray-700 sm:grid-cols-2">
                <form wire:submit="saveDailyFocus" class="flex items-end gap-2">
                    <label class="min-w-0 flex-1">
                        <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Today’s focus (minutes)</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            required
                            wire:model="dailyFocusMinutes"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                        >
                    </label>
                    <button type="submit" class="fi-btn fi-size-sm fi-color-gray">Save</button>
                    <button type="button" wire:click="resetDailyFocus" class="fi-btn fi-size-sm fi-color-gray" aria-label="Reset today's focus time">
                        Reset
                    </button>
                </form>

                <form wire:submit="saveDefaultFocus" class="flex items-end gap-2">
                    <label class="min-w-0 flex-1">
                        <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Default focus (minutes)</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            required
                            wire:model="defaultFocusMinutes"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900"
                        >
                    </label>
                    <button type="submit" class="fi-btn fi-size-sm fi-color-gray">Save default</button>
                </form>
            </div>
            @error('dailyFocusMinutes') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            @error('defaultFocusMinutes') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
