<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class CustomFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-custom-forms::fcf.form.name'))
                    ->formatStateUsing(fn ($state): string => self::localeText($state))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label(__('filament-custom-forms::fcf.form.slug'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('menu_placement')
                    ->label(__('filament-custom-forms::fcf.form.menu_placement'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sidebar', null => 'success',
                        'sub_item' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'sub_item' => __('filament-custom-forms::fcf.form.menu_placement_options.sub_item'),
                        default => __('filament-custom-forms::fcf.form.menu_placement_options.sidebar'),
                    })
                    ->sortable(),

                TextColumn::make('parent_sidebar')
                    ->label(__('filament-custom-forms::fcf.form.menu_parent'))
                    ->default('-')
                    ->getStateUsing(fn ($record): mixed => $record->menu_placement === 'sub_item'
                        ? ($record->parentForm?->name ?? $record->parent_sidebar)
                        : null)
                    ->formatStateUsing(fn ($state): string => filled($state) ? self::localeText($state) : '-')
                    ->searchable(),

                TextColumn::make('workflowTemplate.name')
                    ->label(__('Workflow Template'))
                    ->placeholder(__('Not assigned'))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('filament-custom-forms::fcf.form.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('edit_template')
                    ->label(__('filament-custom-forms::fcf.action.build_template'))
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Model $record): string => route('custom-forms.document-template.edit', [
                        'customForm' => $record->getKey(),
                    ])),

                EditAction::make()
                    ->label(__('filament-custom-forms::fcf.action.edit'))
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->modalDescription(__('filament-custom-forms::fcf.admin.permanent_delete_warning'))
                    ->label(__('filament-custom-forms::fcf.action.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->using(fn (Model $record): ?bool => $record->forceDelete()),
            ])
            ->emptyStateHeading(__('filament-custom-forms::fcf.table.empty_heading'))
            ->emptyStateDescription(__('filament-custom-forms::fcf.table.empty_description'))
            ->emptyStateIcon('heroicon-o-document-plus')
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(__('filament-custom-forms::fcf.admin.permanent_delete_warning'))
                        ->fetchSelectedRecords()
                        ->using(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void {
                            $records->each(function (Model $record) use ($action): void {
                                $record->forceDelete() || $action->reportBulkProcessingFailure();
                            });
                        }),
                ]),
            ]);
    }

    private static function localeText(mixed $value): string
    {
        $decoded = self::decodeText($value);

        if (is_array($decoded)) {
            $locale = app()->getLocale();

            return (string) (
                $decoded[$locale]
                ?? $decoded['km']
                ?? $decoded['kh']
                ?? $decoded['en']
                ?? collect($decoded)->first()
                ?? ''
            );
        }

        $text = (string) $decoded;

        return filled($text) ? $text : '-';
    }

    private static function decodeText(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        return $value;
    }

}
