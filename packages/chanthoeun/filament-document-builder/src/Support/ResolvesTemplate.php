<?php

namespace Chanthoeun\FilamentDocumentBuilder\Support;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Notifications\Notification;

trait ResolvesTemplate
{
    protected \Closure|string|null $templateResolver = null;

    protected \Closure|string|null $filenameResolver = null;

    public function templateType(\Closure|string $resolver): static
    {
        $this->templateResolver = $resolver;

        return $this;
    }

    public function template(\Closure|DocumentTemplate $resolver): static
    {
        $this->templateResolver = $resolver;

        return $this;
    }

    public function filename(\Closure|string $resolver): static
    {
        $this->filenameResolver = $resolver;

        return $this;
    }

    protected function resolveTemplate(mixed $context = null): ?DocumentTemplate
    {
        $templateType = $this->templateResolver
            ? $this->evaluate($this->templateResolver, ['record' => $context])
            : null;

        if ($templateType instanceof DocumentTemplate) {
            return $templateType;
        }

        if (is_string($templateType)) {
            $template = DocumentTemplate::where('type', $templateType)->first();

            if ($template) {
                return $template;
            }
        }

        return DocumentTemplate::first();
    }

    protected function resolveFilename(mixed $context = null): string
    {
        $filename = $this->filenameResolver
            ? $this->evaluate($this->filenameResolver, ['record' => $context])
            : 'document-'.now()->format('Y-m-d-His').'.pdf';

        if (! str_ends_with($filename, '.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }

    protected function notifyTemplateNotFound(): void
    {
        Notification::make()
            ->title('No Document Template Found')
            ->danger()
            ->send();
    }
}
