<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Commitments</x-slot>

        @php($occurrences = $this->getOccurrences())
        @if (count($occurrences))
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($occurrences as $occurrence)
                    <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-950 dark:text-white">
                                {{ $occurrence['commitment']->title }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $occurrence['commitment']->area->name }}
                                @if ($occurrence['starts_before_today'])
                                    · continues from yesterday
                                @endif
                            </p>
                        </div>
                        <time class="shrink-0 text-sm tabular-nums text-gray-600 dark:text-gray-300">
                            {{ $occurrence['starts_at']->format('H:i') }}–{{ $occurrence['ends_at']->format('H:i') }}
                        </time>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No fixed commitments today.</p>
        @endif

        <a
            href="{{ \App\Filament\Resources\Commitments\CommitmentResource::getUrl() }}"
            class="mt-4 inline-flex text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
        >
            Configure commitments
        </a>

        <a
            href="{{ \App\Filament\Resources\InboxItems\InboxItemResource::getUrl() }}"
            class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700"
        >
            <span class="font-medium text-gray-950 dark:text-white">Inbox</span>
            <span class="fi-badge fi-size-sm fi-color-gray">{{ $this->inboxCount }}</span>
        </a>
    </x-filament::section>
</x-filament-widgets::widget>
