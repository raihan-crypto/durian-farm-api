<?php

namespace App\Filament\Resources\HarvestSeasons\Pages;

use App\Filament\Resources\HarvestSeasons\HarvestSeasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHarvestSeasons extends ManageRecords
{
    protected static string $resource = HarvestSeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
