<?php

namespace App\Filament\Resources\FruitDevelopmentRecords\Pages;

use App\Filament\Resources\FruitDevelopmentRecords\FruitDevelopmentRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFruitDevelopmentRecords extends ManageRecords
{
    protected static string $resource = FruitDevelopmentRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
