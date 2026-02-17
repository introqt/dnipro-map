<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Filament\Resources\PointResource\Actions\ApproveBulkAction;
use App\Filament\Resources\PointResource\Actions\RejectBulkAction;
use App\Filament\Resources\PointResource\PointResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListPoints extends ListRecords
{
    protected static string $resource = PointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->toolbarActions([
                BulkActionGroup::make([
                    ApproveBulkAction::make(),
                    RejectBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
