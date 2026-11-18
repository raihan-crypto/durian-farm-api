<?php

namespace App\Filament\Resources\FertilizationRecords\Pages;

use App\Filament\Resources\FertilizationRecords\FertilizationRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFertilizationRecords extends ManageRecords
{
    protected static string $resource = FertilizationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
