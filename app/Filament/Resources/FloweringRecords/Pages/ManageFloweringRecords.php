<?php

namespace App\Filament\Resources\FloweringRecords\Pages;

use App\Filament\Resources\FloweringRecords\FloweringRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFloweringRecords extends ManageRecords
{
    protected static string $resource = FloweringRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
