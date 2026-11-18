<?php

namespace App\Filament\Resources\SprayingRecords\Pages;

use App\Filament\Resources\SprayingRecords\SprayingRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSprayingRecords extends ManageRecords
{
    protected static string $resource = SprayingRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
