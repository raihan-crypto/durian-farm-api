<?php

namespace App\Filament\Resources\Trees\Pages;

use App\Filament\Resources\Trees\TreeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTrees extends ManageRecords
{
    protected static string $resource = TreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
