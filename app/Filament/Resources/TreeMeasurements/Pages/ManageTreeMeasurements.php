<?php

namespace App\Filament\Resources\TreeMeasurements\Pages;

use App\Filament\Resources\TreeMeasurements\TreeMeasurementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTreeMeasurements extends ManageRecords
{
    protected static string $resource = TreeMeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
