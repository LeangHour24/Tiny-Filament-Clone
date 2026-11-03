<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries;

use App\Filament\Student\Pages\ContactUs;
use App\Models\StudentApplicationReview;
use App\Services\StudentWorkflowGate;
use App\Support\ClosingDateWorkflow;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas\CustomFormEntryForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use UnitEnum;

class CustomFormEntryResource extends Resource
{
    protected static ?string $slug = 'custom-form-entries';

    protected static array $formCache = [];

    public static function getModel(): string
    {
        return CustomFormPlugin::get()->getEntryModel();
    }

    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (! auth()->check()) {
            return Response::deny();
        }

        if (static::isStudentPanel()) {
            $customFormId = static::getCustomFormIdFromRequestOrRecord($record);

            if ($record && static::studentOwnsRecord($record) && static::isStudentOwnRecordAction($action)) {
                return static::studentAuthorizationResponseForOwnRecord($action, $record);
            }

            if ($customFormId && ! static::canStudentSeeCustomForm($customFormId)) {
                return Response::deny('This student application is not open yet.');
            }
        }

        $actionName = static::normalizeShieldAction($action);
        $permission = sprintf('%s:%s', $actionName, class_basename(static::getModel()));

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
            default => ucfirst((string) $actionValue),
        };
    }

    protected static function isStudentOwnRecordAction(string | UnitEnum $action): bool
    {
        $actionValue = $action instanceof UnitEnum ? $action->name : (string) $action;

        return in_array($actionValue, ['view', 'update', 'delete'], true);
    }

    protected static function studentOwnsRecord(Model $record): bool
    {
        return isset($record->created_by)
            && (int) $record->created_by === (int) auth()->id();
    }

    public static function canEdit(Model $record): bool
    {
        if (static::isStudentPanel() && static::studentOwnsRecord($record)) {
            return static::studentCanUpdateOwnRecord($record);
        }

        return parent::canEdit($record);
    }

    protected static function studentAuthorizationResponseForOwnRecord(string | UnitEnum $action, Model $record): Response
    {
        $actionValue = $action instanceof UnitEnum ? $action->name : (string) $action;

        if (in_array($actionValue, ['view', 'delete'], true)) {
            return Response::allow();
        }

        if ($actionValue !== 'update') {
            return Response::deny();
        }

        return static::studentCanUpdateOwnRecord($record)
            ? Response::allow()
            : Response::deny('This submission is already approved and cannot be edited.');
    }

    public static function studentCanUpdateOwnRecord(Model $record): bool
    {
        if (! isset($record->id)) {
            return false;
        }

        $review = StudentApplicationReview::query()
            ->where('custom_form_entry_id', $record->id)
            ->latest('id')
            ->first();

        if (! $review) {
            return true;
        }

        return in_array($review->status, [
            StudentApplicationReview::STATUS_PENDING,
            StudentApplicationReview::STATUS_NEEDS_REVISION,
            StudentApplicationReview::STATUS_REJECTED,
        ], true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->check()) {
            return $query->whereRaw('1 = 0');
        }

        if (static::isStudentPanel()) {
            $customFormId = static::getCustomFormIdFromRequestOrRecord();

            if ($customFormId && ! static::canStudentSeeCustomForm($customFormId)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('created_by', auth()->id());
        }

        return $query;
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return CustomFormPlugin::get()->getNavigationEntryIcon();
    }

    public static function getModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value');

        if ($id) {
            $form = static::getFormFromCache((string) $id);

            if ($form) {
                return __('filament-custom-forms::fcf.entry.entry', [
                    'form' => static::translateFormName($form),
                ]);
            }
        }

        return __('filament-custom-forms::fcf.entry.single');
    }

    public static function getPluralModelLabel(): string
    {
        $id = request()->input('tableFilters.custom_form_id.value');

        if ($id) {
            $form = static::getFormFromCache((string) $id);

            if ($form) {
                return __('filament-custom-forms::fcf.entry.entries', [
                    'form' => static::translateFormName($form),
                ]);
            }
        }

        return __('filament-custom-forms::fcf.entry.plural');
    }

    protected static function getFormFromCache(string $id): ?CustomForm
    {
        if (! isset(static::$formCache[$id])) {
            static::$formCache[$id] = CustomForm::query()->find($id);
        }

        return static::$formCache[$id];
    }

    public static function shouldRegisterNavigation(): bool
    {
        try {
            if (CustomFormPlugin::get()->shouldHideEntries()) {
                return false;
            }
        } catch (\Throwable) {
            //
        }

        return parent::shouldRegisterNavigation();
    }

    public static function getNavigationItems(): array
    {
        if (! static::shouldRegisterNavigation()) {
            return [];
        }

        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $query = CustomForm::query()
            ->where('is_active', true)
            ->whereNotNull('name');

        if (DatabaseSchema::hasColumn('custom_forms', 'menu_placement')) {
            $query->where(function ($query): void {
                $query
                    ->whereNull('menu_placement')
                    ->orWhere('menu_placement', 'sidebar');
            });
        }

        if (DatabaseSchema::hasColumn('custom_forms', 'display_order')) {
            $query->orderBy('display_order');
        }

        $forms = $query
            ->orderBy('id')
            ->get()
            ->sortBy(fn (CustomForm $form): string => sprintf(
                '%04d-%04d-%08d',
                static::getNavigationSortPriority($form),
                (int) ($form->display_order ?? 0),
                (int) $form->id,
            ))
            ->values();

        if (static::isStudentPanel()) {
            $forms = $forms->filter(
                fn (CustomForm $form): bool => ClosingDateWorkflow::canSeeCustomFormId((int) $form->id)
                    && app(StudentWorkflowGate::class)->canOpenFormSlug($user, $form->slug)
            );
        }

        return $forms
            ->map(function (CustomForm $form): NavigationItem {
                $status = static::isStudentPanel()
                    ? ClosingDateWorkflow::checkByCustomFormId((int) $form->id)
                    : ['status' => 'open'];

                if (static::isStudentPanel() && ($status['status'] ?? null) === 'expired') {
                    $url = ContactUs::getUrl([
                        'form_id' => $form->id,
                    ]);
                } else {
                    $url = in_array($form->slug, static::studentProfileSlugs(), true)
                        ? static::getStudentProfileUrl($form)
                        : static::getUrl('index', [
                            'tableFilters' => [
                                'custom_form_id' => [
                                    'value' => $form->id,
                                ],
                            ],
                        ]);
                }

                $navigationGroup = static::isStudentPanel()
                    ? CustomFormPlugin::get()->getNavigationGroup()
                    : CustomFormPlugin::get()->getNavigationEntryGroup();

                return NavigationItem::make(static::translateFormName($form))
                    ->group($navigationGroup)
                    ->icon('heroicon-o-document-text')
                    ->url($url)
                    ->isActiveWhen(function () use ($form): bool {
                        $currentFormId = request()->input('tableFilters.custom_form_id.value')
                            ?? request()->query('tableFilters.custom_form_id.value')
                            ?? request()->input('custom_form_id')
                            ?? request()->query('custom_form_id')
                            ?? request()->input('form_id')
                            ?? request()->query('form_id');

                        return (int) $currentFormId === (int) $form->id;
                    });
            })
            ->values()
            ->all();
    }

    protected static function getNavigationSortPriority(CustomForm $form): int
    {
        return match (static::normalizeFormSlug($form->slug)) {
            'profile',
            'student_profile' => 10,
            'enrollment' => 20,
            'registration-form',
            'registration.form' => 30,
            'end-of-year-registration' => 40,
            'foreign-internship-registration',
            'foreign-internship',
            'foreign_internship',
            'internship' => 50,
            default => 1000,
        };
    }

    protected static function getStudentProfileUrl(CustomForm $form): string
    {
        $entry = CustomFormEntry::query()
            ->where('custom_form_id', $form->id)
            ->where('created_by', auth()->id())
            ->latest('id')
            ->first();

        if ($entry) {
            return static::getUrl('edit', ['record' => $entry]);
        }

        return static::getUrl('create', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $form->id,
                ],
            ],
        ]);
    }

    protected static function isStudentPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'student';
    }

    protected static function getCustomFormIdFromRequestOrRecord(?Model $record = null): ?int
    {
        $customFormId = request()->input('tableFilters.custom_form_id.value')
            ?? request()->query('tableFilters.custom_form_id.value')
            ?? request()->input('custom_form_id')
            ?? request()->query('custom_form_id')
            ?? request()->input('form_id')
            ?? request()->query('form_id');

        if (! $customFormId && $record && isset($record->custom_form_id)) {
            $customFormId = $record->custom_form_id;
        }

        return filled($customFormId) ? (int) $customFormId : null;
    }

    protected static function canStudentSeeCustomForm(?int $customFormId): bool
    {
        if (! $customFormId) {
            return true;
        }

        $form = static::getFormFromCache((string) $customFormId);

        if (! $form) {
            return false;
        }

        return ClosingDateWorkflow::canSeeCustomFormId((int) $customFormId)
            && app(StudentWorkflowGate::class)->canOpenFormSlug(auth()->user(), $form->slug);
    }

    protected static function studentProfileSlugs(): array
    {
        return [
            'profile',
            'profile-form',
            'resume-form',
            'student_profile',
            'student-profile',
        ];
    }

    public static function translateFormName(CustomForm $form): string
    {
        foreach (static::getTranslationFormSlugs($form->slug) as $slug) {
            $translationKey = "filament-custom-forms::fcf.form.names.{$slug}";
            $translation = __($translationKey);

            if ($translation !== $translationKey) {
                return (string) $translation;
            }
        }

        return static::translateStoredText($form->name, (string) $form->slug);
    }

    public static function translateStoredText(mixed $value, string $fallback = ''): string
    {
        if (is_array($value)) {
            return static::translatedArrayValue($value, $fallback);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return static::translatedArrayValue($decoded, $fallback);
            }

            return $value !== '' ? $value : $fallback;
        }

        return filled($value) ? (string) $value : $fallback;
    }

    protected static function translatedArrayValue(array $value, string $fallback = ''): string
    {
        $locale = app()->getLocale();

        $translated = $value[$locale]
            ?? $value[str_replace('-', '_', $locale)]
            ?? $value['en']
            ?? $value['km']
            ?? collect($value)->first(fn ($item): bool => filled($item));

        return filled($translated) ? (string) $translated : $fallback;
    }

    protected static function getTranslationFormSlugs(?string $formSlug = null): array
    {
        $slugs = [];
        $normalizedSlug = static::normalizeFormSlug($formSlug);

        if ($normalizedSlug) {
            $slugs[] = $normalizedSlug;
        }

        if (in_array($normalizedSlug, ['profile', 'profile-form', 'student-profile', 'resume-form'], true)) {
            $slugs[] = 'resume-form';
        }

        if (in_array($normalizedSlug, ['national-exam', 'print-application', 'specialty-exam-form'], true)) {
            $slugs[] = 'national-exam';
            $slugs[] = 'print-application';
            $slugs[] = 'specialty-exam-form';
        }

        return array_values(array_unique($slugs));
    }

    protected static function normalizeFormSlug(?string $slug): ?string
    {
        if (! filled($slug)) {
            return null;
        }

        return Str::of($slug)
            ->lower()
            ->replace(['_', ' '], '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();
    }

    public static function form(Schema $schema): Schema
    {
        return CustomFormEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomFormEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomFormEntries::route('/'),
            'create' => Pages\CreateCustomFormEntry::route('/create'),
            'edit' => Pages\EditCustomFormEntry::route('/{record}/edit'),
        ];
    }
}
