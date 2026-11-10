<?php

namespace App\Livewire;

use App\Actions\CaptureInboxItem;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QuickCapture extends Component
{
    public string $text = '';

    public function save(CaptureInboxItem $captureInboxItem): void
    {
        $captureInboxItem->handle($this->text);

        $this->reset('text');
        Notification::make()->title('Captured to Inbox')->success()->send();
        $this->dispatch('dashboard-data-changed');
    }

    public function render(): View
    {
        return view('livewire.quick-capture');
    }
}
