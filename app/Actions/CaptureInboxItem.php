<?php

namespace App\Actions;

use App\Models\InboxItem;
use Illuminate\Support\Facades\Validator;

class CaptureInboxItem
{
    public function handle(string $text): InboxItem
    {
        $data = Validator::make(['text' => trim($text)], [
            'text' => ['required', 'string', 'max:5000'],
        ])->validate();

        return InboxItem::create($data);
    }
}
