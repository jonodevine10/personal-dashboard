<?php

namespace App\Filament\Resources\InboxItems\Pages;

use App\Filament\Resources\InboxItems\InboxItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInboxItems extends ManageRecords
{
    protected static string $resource = InboxItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
