<form wire:submit="save" class="flex w-[min(48vw,20rem)] min-w-0 items-center gap-1.5 sm:w-72 sm:gap-2">
    <label class="sr-only" for="quick-capture-text">Quick capture to Inbox</label>
    <input
        id="quick-capture-text"
        type="text"
        wire:model="text"
        maxlength="5000"
        required
        placeholder="Capture to Inbox…"
        autocomplete="off"
        class="fi-input block min-w-0 flex-1 rounded-lg border-gray-300 bg-white text-sm shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
    >
    <button
        type="submit"
        aria-label="Save to Inbox"
        class="fi-btn fi-size-sm fi-color-primary shrink-0"
    >
        <span class="sm:hidden">+</span>
        <span class="hidden sm:inline">Capture</span>
    </button>
    @error('text') <span class="sr-only" role="alert">{{ $message }}</span> @enderror
</form>
