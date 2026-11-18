<?php

namespace App\Filament\Resources\AgriculturalProducts\Pages;

use App\Filament\Resources\AgriculturalProducts\AgriculturalProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAgriculturalProducts extends ManageRecords
{
    protected static string $resource = AgriculturalProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
