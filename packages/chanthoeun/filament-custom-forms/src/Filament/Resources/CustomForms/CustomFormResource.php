<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Schemas\CustomFormForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Tables\CustomFormsTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;
use UnitEnum;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;

class CustomFormResource extends Resource
{
    public static function getModel(): string
    {
        return CustomFormPlugin::get()->getFormModel();
    }

    public static function getModelLabel(): string
    {
        return __('filament-custom-forms::fcf.form.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-custom-forms::fcf.form.plural');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return CustomFormPlugin::get()->getNavigationFormIcon();
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return CustomFormPlugin::get()->getNavigationGroup();
    }

    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $actionName = static::normalizeShieldAction($action);
        $permission = sprintf('%s:%s', $actionName, class_basename(static::getModel()));

        if (! auth()->check()) {
            return Response::deny();
        }

        return auth()->user()->can($permission)
            ? Response::allow()
            : Response::deny();
    }

    protected static function normalizeShieldAction(string | UnitEnum $action): string
    {
        $actionValue = $action instanceof UnitEnum ? $action->name : $action;

        return match ($actionValue) {
            'viewAny' => 'ViewAny',
            'deleteAny' => 'DeleteAny',
            'forceDeleteAny' => 'ForceDeleteAny',
            'restoreAny' => 'RestoreAny',
            default => ucfirst($actionValue),
        };
    }

    public static function form(Schema $schema): Schema
    {
        return CustomFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Support\ResourceActionPermissions::table(self::roleActionsTable($table), static::class);
    }

    protected static function roleActionsTable(Table $table): Table
    {
        return CustomFormsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomForms::route('/'),
            'create' => Pages\CreateCustomForm::route('/create'),
            'edit' => Pages\EditCustomForm::route('/{record}/edit'),
        ];
    }
}
