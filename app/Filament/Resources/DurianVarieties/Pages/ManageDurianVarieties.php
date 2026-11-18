<?php

namespace App\Filament\Resources\DurianVarieties\Pages;

use App\Filament\Resources\DurianVarieties\DurianVarietyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDurianVarieties extends ManageRecords
{
    protected static string $resource = DurianVarietyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
