<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Support\ClosingDateWorkflow;
use App\Support\CustomFormEntryImageData;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CreateCustomFormEntry extends CreateRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    protected bool $isSavingDraft = false;

    public function mount(): void
    {
        parent::mount();

        if ($entry = $this->getExistingStudentProfileEntry()) {
            $url = CustomFormEntryResource::getUrl('edit', ['record' => $entry]);

            $this->redirect($url, navigate: FilamentView::hasSpaMode($url));

            return;
        }

        $status = $this->closingDateStatus();

        if (! ($status['can_see_form'] ?? true)) {
            Notification::make()
                ->title($status['title'] ?? 'This application is not open yet.')
                ->body($status['message'] ?? null)
                ->warning()
                ->send();

            $url = CustomFormEntryResource::getUrl('index');

            $this->redirect($url, navigate: FilamentView::hasSpaMode($url));

            return;
        }

        if (($status['status'] ?? null) === 'expired') {
            Notification::make()
                ->title($status['title'] ?? 'This form has expired.')
                ->body($status['message'] ?? 'You can view this form, but you cannot submit it anymore.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function getHeading(): string|Htmlable
    {
        if ($this->isStudentProfileForm()) {
            $form = CustomForm::query()->find($this->getActiveFormId());

            return $form ? $this->translateFormName($form) : 'Student profile';
        }

        return parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $status = $this->closingDateStatus();

        if (($status['status'] ?? null) === 'expired') {
            return new HtmlString('
                <div style="
                    margin-top: 1rem;
                    padding: 1rem 1.15rem;
                    border-radius: 14px;
                    border: 1px solid rgba(239, 68, 68, 0.35);
                    background: rgba(239, 68, 68, 0.10);
                ">
                    <div style="display: flex; align-items: flex-start; gap: 0.85rem;">
                        <div style="
                            width: 36px;
                            height: 36px;
                            min-width: 36px;
                            border-radius: 999px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #ffffff;
                            font-weight: 800;
                            background: #ef4444;
                        ">
                            !
                        </div>

                        <div>
                            <div style="font-weight: 800; font-size: 1rem; color: #ef4444;">
                                ' . e($status['title'] ?? 'This form has expired.') . '
                            </div>

                            <div style="margin-top: 0.35rem; color: #9ca3af; line-height: 1.55;">
                                ' . e($status['message'] ?? 'You can view this form, but you cannot submit it anymore.') . '
                            </div>

                            <div style="margin-top: 0.65rem; font-size: 0.875rem; color: #9ca3af;">
                                Application Period:
                                <strong>' . e($status['start_date'] ?? '-') . '</strong>
                                -
                                <strong>' . e($status['end_date'] ?? '-') . '</strong>
                            </div>
                        </div>
                    </div>
                </div>
            ');
        }

        return parent::getSubheading();
    }

    public function canCreateAnother(): bool
    {
        if (! $this->canSubmitCurrentForm()) {
            return false;
        }

        return $this->isStudentProfileForm()
            ? false
            : parent::canCreateAnother();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('filament-custom-forms::fcf.entry.action.save'))
            ->disabled(fn (): bool => ! $this->canSubmitCurrentForm());
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label(__('filament-custom-forms::fcf.entry.action.save_draft'))
            ->disabled(fn (): bool => ! $this->canSubmitCurrentForm());
    }

    public function create(bool $another = false): void
    {
        $this->isSavingDraft = $another;

        try {
            parent::create($another);
        } finally {
            $this->isSavingDraft = false;
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__(
                $this->isSavingDraft
                    ? 'filament-custom-forms::fcf.entry.notification.draft_saved_title'
                    : 'filament-custom-forms::fcf.entry.notification.saved_title'
            ))
            ->body(__(
                $this->isSavingDraft
                    ? 'filament-custom-forms::fcf.entry.notification.draft_saved_body'
                    : 'filament-custom-forms::fcf.entry.notification.saved_body'
            ));
    }

    protected function getFormActions(): array
    {
        $status = $this->closingDateStatus();

        if (! ($status['can_submit'] ?? true)) {
            return [
                Action::make('expired_message')
                    ->label($status['title'] ?? 'This form has expired.')
                    ->disabled()
                    ->color('danger'),

                Action::make('back')
                    ->label('Back')
                    ->color('gray')
                    ->url($this->getClosedBackUrl()),
            ];
        }

        return parent::getFormActions();
    }

    protected function beforeCreate(): void
    {
        $this->blockSubmitIfClosed();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->blockSubmitIfClosed();

        $data['custom_form_id'] = $data['custom_form_id']
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->integer('custom_form_id')
            ?? request()->integer('form_id');

        $data['source_entry_id'] = $data['source_entry_id'] ?? null;

        $data['created_by'] = auth()->id();

        if (Schema::hasColumn('custom_form_entries', 'user_id')) {
            $data['user_id'] = auth()->id();
        }

        $data['data'] = $data['data'] ?? [];
        $data['data'] = CustomFormEntryImageData::embedImages($data['data'], (int) $data['custom_form_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $entry = $this->record;

        if (! $entry instanceof \App\Models\CustomFormEntry) {
            $entry = \App\Models\CustomFormEntry::find($this->record->id);
        }

        if (! $entry) {
            return;
        }

        $review = \App\Models\StudentApplicationReview::firstOrCreate(
            ['custom_form_entry_id' => $entry->id],
            ['status' => \App\Models\StudentApplicationReview::STATUS_PENDING]
        );

        \App\Models\CustomFormEntry::notifyAdminAndRegistrar($entry, $review);
    }

    protected function getRedirectUrl(): string
    {
        if (in_array($this->record?->customForm?->slug, $this->studentProfileSlugs(), true)) {
            return static::getResource()::getUrl('edit', [
                'record' => $this->record,
            ]);
        }

        return static::getResource()::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $this->record->custom_form_id,
                ],
            ],
        ]);
    }

    protected function getActiveFormId(): ?int
    {
        $formId = data_get($this->data, 'custom_form_id')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->integer('custom_form_id')
            ?? request()->integer('form_id');

        return filled($formId) ? (int) $formId : null;
    }

    protected function getCurrentApplicationType(): ?string
    {
        $formId = $this->getActiveFormId();

        if (! $formId) {
            return null;
        }

        return CustomForm::query()
            ->whereKey($formId)
            ->value('name');
    }

    protected function closingDateStatus(): array
    {
        return ClosingDateWorkflow::check($this->getCurrentApplicationType());
    }

    protected function canSubmitCurrentForm(): bool
    {
        return $this->closingDateStatus()['can_submit'] ?? true;
    }

    protected function blockSubmitIfClosed(): void
    {
        $status = $this->closingDateStatus();

        if ($status['can_submit'] ?? true) {
            return;
        }

        Notification::make()
            ->title($status['title'] ?? 'This form has expired.')
            ->body($status['message'] ?? 'You cannot submit this form anymore.')
            ->danger()
            ->send();

        $this->halt();
    }

    protected function getClosedBackUrl(): string
    {
        $formId = $this->getActiveFormId();

        if (! $formId) {
            return CustomFormEntryResource::getUrl('index');
        }

        return CustomFormEntryResource::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $formId,
                ],
            ],
        ]);
    }

    protected function isStudentProfileForm(): bool
    {
        $formId = $this->getActiveFormId();

        if (! $formId) {
            return false;
        }

        return CustomForm::query()
            ->whereKey($formId)
            ->whereIn('slug', $this->studentProfileSlugs())
            ->exists();
    }

    protected function studentProfileSlugs(): array
    {
        return [
            'profile',
            'profile-form',
            'resume-form',
            'student_profile',
            'student-profile',
        ];
    }

    protected function translateFormName(CustomForm $form): string
    {
        foreach ($this->getTranslationFormSlugs($form->slug) as $slug) {
            $translationKey = "filament-custom-forms::fcf.form.names.{$slug}";
            $translation = __($translationKey);

            if ($translation !== $translationKey) {
                return (string) $translation;
            }
        }

        return (string) $form->name;
    }

    protected function getTranslationFormSlugs(?string $formSlug = null): array
    {
        $slugs = [];
        $normalizedSlug = $this->normalizeFormSlug($formSlug);

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

    protected function normalizeFormSlug(?string $slug): ?string
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

    protected function getExistingStudentProfileEntry(): ?CustomFormEntry
    {
        $formId = $this->getActiveFormId();

        if (! $formId || ! $this->isStudentProfileForm()) {
            return null;
        }

        return CustomFormEntry::query()
            ->where('custom_form_id', $formId)
            ->where('created_by', auth()->id())
            ->latest('id')
            ->first();
    }
}
