<?php

namespace Chanthoeun\FilamentDocumentBuilder;

use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DocumentBuilderPlugin implements Plugin
{
    protected string|bool|null $navigationGroup = 'Document Builder';

    protected string $navigationIcon = 'heroicon-o-document-duplicate';

    protected ?int $navigationSort = null;

    public function getId(): string
    {
        return 'filament-document-builder';
    }

    public function navigationGroup(bool|string|null $group = null): static
    {
        // If they pass false, they want to explicitly disable the group
        $this->navigationGroup = $group === false ? null : $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort ?? config('filament-document-builder.navigation.sort');
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string
    {
        return $this->navigationIcon;
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        // Only register the internal resource if the user hasn't published it to their own application
        if (! class_exists(\App\Filament\Resources\DocumentTemplateResource::class)) {
            $resources[] = DocumentTemplateResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
