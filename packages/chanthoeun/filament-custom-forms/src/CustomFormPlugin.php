<?php

namespace Chanthoeun\FilamentCustomForms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;

class CustomFormPlugin implements Plugin
{
    protected ?string $formModel = null;
    protected ?string $entryModel = null;
    protected ?string $userModel = null;
    protected ?string $uploadDisk = null;
    protected ?string $uploadDirectory = null;
    protected ?string $uploadVisibility = null;
    protected ?string $navigationGroup = null;
    protected ?string $navigationEntryGroup = null;
    protected ?string $navigationFormIcon = null;
    protected ?string $navigationEntryIcon = null;
    protected bool $hasPanelAccess = false;
    protected bool $hasTranslations = false;
    protected bool $shouldHideBuilders = false;
    protected bool $shouldHideEntries = false;

    public function getId(): string
    {
        return 'filament-custom-forms';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    // --- Models ---
    public function formModel(string $model): static
    {
        $this->formModel = $model;
        return $this;
    }

    public function getFormModel(): string
    {
        return $this->formModel ?? config('filament-custom-forms.models.form', \Chanthoeun\FilamentCustomForms\Models\CustomForm::class);
    }

    public function entryModel(string $model): static
    {
        $this->entryModel = $model;
        return $this;
    }

    public function getEntryModel(): string
    {
        return $this->entryModel ?? config('filament-custom-forms.models.entry', \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::class);
    }

    public function userModel(string $model): static
    {
        $this->userModel = $model;
        return $this;
    }

    public function getUserModel(): string
    {
        return $this->userModel ?? config('filament-custom-forms.models.user', \App\Models\User::class);
    }

    // --- Uploads ---
    public function uploadDisk(string $disk): static
    {
        $this->uploadDisk = $disk;
        return $this;
    }

    public function getUploadDisk(): string
    {
        return $this->uploadDisk ?? config('filament-custom-forms.uploads.disk', 'public');
    }

    public function uploadDirectory(string $directory): static
    {
        $this->uploadDirectory = $directory;
        return $this;
    }

    public function getUploadDirectory(): string
    {
        return $this->uploadDirectory ?? config('filament-custom-forms.uploads.directory', 'custom-forms');
    }

    public function uploadVisibility(string $visibility): static
    {
        $this->uploadVisibility = $visibility;
        return $this;
    }

    public function getUploadVisibility(): string
    {
        return $this->uploadVisibility ?? config('filament-custom-forms.uploads.visibility', 'public');
    }

    // --- Navigation ---
    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    public function getNavigationGroup(): string
    {
        return $this->navigationGroup ?? config('filament-custom-forms.navigation.group', __('filament-custom-forms::fcf.form.builder_group'));
    }

    public function navigationEntryGroup(string $group): static
    {
        $this->navigationEntryGroup = $group;
        return $this;
    }

    public function getNavigationEntryGroup(): string
    {
        return $this->navigationEntryGroup ?? config('filament-custom-forms.navigation.entry_group', __('filament-custom-forms::fcf.form.entry_group'));
    }
 
    /**
     * @deprecated Use navigationEntryGroup() instead.
     */
    public function navigationOpsGroup(string $group): static
    {
        return $this->navigationEntryGroup($group);
    }
 
    /**
     * @deprecated Use getNavigationEntryGroup() instead.
     */
    public function getNavigationOpsGroup(): string
    {
        return $this->getNavigationEntryGroup();
    }

    public function navigationFormIcon(string $icon): static
    {
        $this->navigationFormIcon = $icon;
        return $this;
    }

    public function getNavigationFormIcon(): string
    {
        return $this->navigationFormIcon ?? config('filament-custom-forms.navigation.icon', 'heroicon-o-rectangle-stack');
    }

    public function navigationEntryIcon(string $icon): static
    {
        $this->navigationEntryIcon = $icon;
        return $this;
    }

    public function getNavigationEntryIcon(): string
    {
        return $this->navigationEntryIcon ?? config('filament-custom-forms.navigation.entry_icon', 'heroicon-o-document-text');
    }

    public function panelAccess(bool $condition = true): static
    {
        $this->hasPanelAccess = $condition;

        return $this;
    }

    public function hasPanelAccess(): bool
    {
        return $this->hasPanelAccess;
    }

    public function translations(bool $condition = true): static
    {
        $this->hasTranslations = $condition;

        return $this;
    }

    public function hasTranslations(): bool
    {
        return $this->hasTranslations;
    }

    public function hideBuilders(bool $condition = true): static
    {
        $this->shouldHideBuilders = $condition;

        return $this;
    }

    public function shouldHideBuilders(): bool
    {
        return $this->shouldHideBuilders;
    }

    public function hideEntries(bool $condition = true): static
    {
        $this->shouldHideEntries = $condition;

        return $this;
    }

    public function shouldHideEntries(): bool
    {
        return $this->shouldHideEntries;
    }

    public function register(Panel $panel): void
    {
        $resources = [
            CustomFormEntryResource::class,
        ];

        if (! $this->shouldHideBuilders()) {
            array_unshift($resources, CustomFormResource::class);
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
