<?php

namespace Chanthoeun\FilamentDocumentBuilder\Tables\Actions;

use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Support\ResolvesTemplate;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class DownloadPdfAction extends Action
{
    use ResolvesTemplate;

    protected ?\Closure $dataResolver = null;

    public static function getDefaultName(): ?string
    {
        return 'download_pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF');
        $this->icon('heroicon-o-document-arrow-down');
        $this->color('success');

        $this->action(function (Model $record) {
            $template = $this->resolveTemplate($record);

            if (! $template) {
                $this->notifyTemplateNotFound();

                return;
            }

            $data = $this->dataResolver ? $this->evaluate($this->dataResolver, ['record' => $record]) : $record;

            $renderer = app(DocumentRenderer::class);
            $pdf = $renderer->render($template, $data);

            $filename = $this->resolveFilename($record);

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename);
        });
    }

    public function recordData(\Closure $resolver): static
    {
        $this->dataResolver = $resolver;

        return $this;
    }
}
