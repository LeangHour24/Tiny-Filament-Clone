<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Tables;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentTemplateTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-document-builder::document-builder.labels.template_name'))
                    ->formatStateUsing(fn ($state): string => self::localizedName($state))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('model_class')
                    ->label(__('filament-document-builder::document-builder.labels.database_model'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : __('filament-document-builder::document-builder.labels.no_model_linked')),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament-document-builder::document-builder.labels.template_type'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'invoice' => 'success',
                        'receipt' => 'warning',
                        'certificate' => 'info',
                        'application' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-document-builder::document-builder.labels.created_on'))
                    ->dateTime('d-F-Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-document-builder::document-builder.labels.last_updated'))
                    ->dateTime('d-F-Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                Actions\Action::make('preview_pdf')
                    ->label(__('filament-document-builder::document-builder.labels.preview_pdf'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (DocumentTemplate $record) {
                        if (empty($record->model_class)) {
                            Notification::make()
                                ->title(__('filament-document-builder::document-builder.labels.no_model_selected_title'))
                                ->body(__('filament-document-builder::document-builder.labels.no_model_selected_body'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $data = [];
                        if (class_exists($record->model_class)) {
                            $sampleRecord = $record->model_class::first();
                            if ($sampleRecord) {
                                $data = $sampleRecord;
                            } else {
                                Notification::make()
                                    ->title(__('filament-document-builder::document-builder.labels.no_records_found_title'))
                                    ->body(__('filament-document-builder::document-builder.labels.no_records_found_body', ['model' => $record->model_class]))
                                    ->warning()
                                    ->send();

                                return;
                            }
                        }

                        $renderer = app(DocumentRenderer::class);
                        $pdf = $renderer->render($record, $data);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'preview.pdf');
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function localizedName(mixed $value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $locale = app()->getLocale();

                return self::firstLocalizedText($decoded, [$locale, 'km', 'kh', 'en']);
            }
        }

        if (is_array($value)) {
            $locale = app()->getLocale();

            return self::firstLocalizedText($value, [$locale, 'km', 'kh', 'en']);
        }

        return (string) ($value ?? '');
    }

    protected static function firstLocalizedText(array $value, array $preferredKeys): string
    {
        foreach (array_unique($preferredKeys) as $key) {
            if (isset($value[$key]) && trim((string) $value[$key]) !== '') {
                return (string) $value[$key];
            }
        }

        foreach ($value as $text) {
            if (is_scalar($text) && trim((string) $text) !== '') {
                return (string) $text;
            }
        }

        return '';
    }
}
