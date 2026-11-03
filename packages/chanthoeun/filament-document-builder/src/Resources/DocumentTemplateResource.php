<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources;

use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Schemas\DocumentTemplateForm;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Tables\DocumentTemplateTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        /** @var DocumentBuilderPlugin $plugin */
        $plugin = filament('filament-document-builder');

        return $plugin->getNavigationIcon();
    }

    public static function getNavigationGroup(): ?string
    {
        /** @var DocumentBuilderPlugin $plugin */
        $plugin = filament('filament-document-builder');

        return $plugin->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        /** @var DocumentBuilderPlugin $plugin */
        $plugin = filament('filament-document-builder');

        return $plugin->getNavigationSort();
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateForm::schema($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplateTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
