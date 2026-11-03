<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomForms extends ListRecords
{
    protected static string $resource = CustomFormResource::class;



    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('filament-custom-forms::fcf.action.new_custom_form'))
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
