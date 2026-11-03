<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Support\LayoutTemplates;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('load_example_layout')
                ->label('Load Example Layout')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Load Example Layout')
                ->modalDescription('Warning: This will overwrite any existing content in your Document Designer.')
                ->modalSubmitActionLabel('Load Layout')
                ->form([
                    Select::make('layout')
                        ->label('Select a Layout')
                        ->options(LayoutTemplates::getOptions())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $html = LayoutTemplates::getTemplate($data['layout']);

                    $this->data['content'] = $html;

                    if ($data['layout'] === 'certificate') {
                        $this->data['page_settings']['orientation'] = 'landscape';
                    } else {
                        $this->data['page_settings']['orientation'] = 'portrait';
                    }

                    Notification::make()
                        ->title('Layout Loaded')
                        ->success()
                        ->send();
                }),
            Action::make('preview_pdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('success')
                ->action(function () {
                    /** @var DocumentTemplate $record */
                    $record = $this->record;
                    $record->fill($this->form->getState());

                    if (empty($record->model_class)) {
                        Notification::make()
                            ->title('No Database Model Selected')
                            ->body('You must select a Database Model in the Template Details and click Save before you can preview.')
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
                                ->title('No Records Found')
                                ->body("There are no records in the {$record->model_class} table to preview with.")
                                ->warning()
                                ->send();

                            return;
                        }
                    } else {
                        Notification::make()
                            ->title('Invalid Model')
                            ->body("The model {$record->model_class} does not exist.")
                            ->danger()
                            ->send();

                        return;
                    }

                    $renderer = app(DocumentRenderer::class);
                    $pdf = $renderer->render($record, $data);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'preview-'.Str::slug((string) $record->getAttribute('name')).'.pdf');
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
