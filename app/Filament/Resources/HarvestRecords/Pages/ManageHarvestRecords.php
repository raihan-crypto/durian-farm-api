<?php

namespace App\Filament\Resources\HarvestRecords\Pages;

use App\Filament\Resources\HarvestRecords\HarvestRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHarvestRecords extends ManageRecords
{
    protected static string $resource = HarvestRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
