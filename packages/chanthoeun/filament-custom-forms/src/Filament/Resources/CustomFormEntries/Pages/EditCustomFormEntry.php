<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Filament\Support\RoleAccess;
use App\Models\StudentApplicationReview;
use App\Support\CustomFormEntryImageData;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class EditCustomFormEntry extends EditRecord
{
    protected static string $resource = CustomFormEntryResource::class;

    public function getHeading(): string|Htmlable
    {
        $customForm = $this->getRecord()->customForm;

        if ($customForm) {
            return 'Edit ' . $this->translateFormName($customForm);
        }

        return parent::getHeading();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['source_entry_id'] = $data['source_entry_id'] ?? null;
        $data['data'] = $data['data'] ?? [];
        $data['data'] = CustomFormEntryImageData::embedImages($data['data'], (int) $this->getRecord()->custom_form_id);

        return $data;
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()
            ->label(__('filament-custom-forms::fcf.entry.action.save'));
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('filament-custom-forms::fcf.entry.notification.saved_title'))
            ->body(__('filament-custom-forms::fcf.entry.notification.saved_body'));
    }

    protected function afterSave(): void
    {
        $review = StudentApplicationReview::query()
            ->where('custom_form_entry_id', $this->getRecord()->id)
            ->whereIn('status', [
                StudentApplicationReview::STATUS_REJECTED,
                StudentApplicationReview::STATUS_NEEDS_REVISION,
            ])
            ->first();

        if (! $review) {
            return;
        }

        $review->forceFill([
            'status' => StudentApplicationReview::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->saveQuietly();

        $entry = \App\Models\CustomFormEntry::query()->find($this->getRecord()->id);

        if ($entry) {
            \App\Models\CustomFormEntry::notifyAdminAndRegistrar($entry, $review);
        }
    }

    protected function getHeaderActions(): array
    {
        if ($this->isStudentProfileRecord()) {
            return [
                Actions\Action::make('export_pdf')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (): string => route('student-profile.pdf', $this->getRecord()))
                    ->openUrlInNewTab(),
            ];
        }

        return [
            Actions\Action::make('export_pdf')
                ->authorize(fn (): bool => RoleAccess::hasSystemPermission('ExportPdf:CustomFormEntry'))
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn (): bool => filled($this->getPdfRouteName()))
                ->url(fn (): string => route($this->getPdfRouteName(), $this->getRecord()))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        $record = $this->getRecord();
        $customForm = $record->customForm;

        if ($this->isStudentProfileRecord()) {
            return [];
        }

        $label = 'Custom Form Entries';
        $urlParams = [];

        if ($customForm) {
            $label = $this->translateFormName($customForm) . ' Entries';

            $urlParams = [
                'tableFilters' => [
                    'custom_form_id' => [
                        'value' => $customForm->id,
                    ],
                ],
            ];
        }

        $url = CustomFormEntryResource::getUrl('index');

        if (! empty($urlParams)) {
            $url .= '?' . http_build_query($urlParams);
        }

        return [
            $url => $label,
            'Edit',
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        if ($this->isStudentProfileRecord()) {
            return null;
        }

        $customFormId = $this->record->custom_form_id;

        return static::getResource()::getUrl('index', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $customFormId,
                ],
            ],
        ]);
    }

    protected function isStudentProfileRecord(): bool
    {
        return in_array($this->getRecord()->customForm?->slug, ['profile', 'profile-form', 'student_profile', 'student-profile', 'resume-form'], true);
    }

    protected function translateFormName($form): string
    {
        foreach ($this->getTranslationFormSlugs($form->slug ?? null) as $slug) {
            $translationKey = "filament-custom-forms::fcf.form.names.{$slug}";
            $translation = __($translationKey);

            if ($translation !== $translationKey) {
                return (string) $translation;
            }
        }

        return (string) ($form->name ?? 'Form');
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

    protected function getPdfRouteName(): ?string
    {
        return match ($this->getRecord()->customForm?->slug) {
            'enrollment' => 'enrollment.pdf',
            'profile', 'profile-form', 'resume-form', 'student_profile', 'student-profile' => 'student-profile.pdf',
            'specialty-exam-form', 'national-exam', 'print-application', 'National Exam', 'Print Application' => 'national-exam.pdf',
            default => 'custom-form-entry.pdf',
        };
    }
}
