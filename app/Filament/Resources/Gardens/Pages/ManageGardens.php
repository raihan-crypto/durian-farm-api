<?php

namespace App\Filament\Resources\Gardens\Pages;

use App\Filament\Resources\Gardens\GardenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGardens extends ManageRecords
{
    protected static string $resource = GardenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
