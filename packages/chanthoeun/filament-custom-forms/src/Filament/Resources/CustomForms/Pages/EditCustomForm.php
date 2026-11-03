<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomForm extends EditRecord
{
    protected static string $resource = CustomFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalDescription(__('filament-custom-forms::fcf.admin.permanent_delete_warning'))
                ->using(fn (\Illuminate\Database\Eloquent\Model $record): ?bool => $record->forceDelete()),
        ];
    }
}
