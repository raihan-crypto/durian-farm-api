<?php

namespace App\Filament\Resources\HarvestSessions\Pages;

use App\Filament\Resources\HarvestSessions\HarvestSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHarvestSessions extends ManageRecords
{
    protected static string $resource = HarvestSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
