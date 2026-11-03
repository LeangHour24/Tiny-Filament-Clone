<?php

namespace Chanthoeun\FilamentDocumentBuilder\Actions;

use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Support\ResolvesTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DownloadAllPdfAction extends Action
{
    use ResolvesTemplate;

    protected ?\Closure $recordsResolver = null;

    public static function getDefaultName(): ?string
    {
        return 'download_all_pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF');
        $this->icon('heroicon-o-document-arrow-down');
        $this->color('success');

        $this->action(function () {
            $records = $this->recordsResolver ? $this->evaluate($this->recordsResolver) : collect([]);

            if ((is_countable($records) ? count($records) : 0) === 0) {
                Notification::make()
                    ->title('No records to export')
                    ->warning()
                    ->send();

                return;
            }

            $template = $this->resolveTemplate();

            if (! $template) {
                $this->notifyTemplateNotFound();

                return;
            }

            $renderer = app(DocumentRenderer::class);

            $pdf = $renderer->renderMultiple($template, $records);

            $filename = $this->resolveFilename();

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename);
        });
    }

    public function records(\Closure|iterable $resolver): static
    {
        $this->recordsResolver = $resolver;

        return $this;
    }
}
