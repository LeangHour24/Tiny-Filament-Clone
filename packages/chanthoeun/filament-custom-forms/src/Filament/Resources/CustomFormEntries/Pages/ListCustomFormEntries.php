<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Support\ClosingDateWorkflow;
use App\Filament\Support\RoleAccess;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCustomFormEntries extends ListRecords
{
    protected static string $resource = CustomFormEntryResource::class;

    public function getTitle(): string|Htmlable
    {
        $form = $this->getActiveForm();

        return $form ? CustomFormEntryResource::translateFormName($form) : parent::getTitle();
    }

    public function getHeading(): string|Htmlable
    {
        $form = $this->getActiveForm();

        return $form ? CustomFormEntryResource::translateFormName($form) : parent::getHeading();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! $this->isStudentPanel()) {
            return parent::getSubheading();
        }

        $status = $this->closingDateStatus();

        if (($status['status'] ?? null) !== 'expired') {
            return parent::getSubheading();
        }

        return new HtmlString('
            <div style="margin-top: 1rem; margin-bottom: 2rem;">
                <div style="max-width: 980px; margin: 0 auto 1.5rem auto; text-align: center;">
                    <div style="
                        display: inline-flex;
                        align-items: center;
                        gap: 0.4rem;
                        padding: 0.35rem 0.8rem;
                        border-radius: 999px;
                        background: rgba(245, 158, 11, 0.14);
                        color: #f59e0b;
                        font-weight: 900;
                        font-size: 0.78rem;
                        margin-bottom: 0.85rem;
                    ">
                        ☎ Student Support
                    </div>

                    <div style="
                        color: #ffffff;
                        font-size: 1.7rem;
                        font-weight: 900;
                        line-height: 1.2;
                    ">
                        Contact Admissions Support
                    </div>

                    <div style="
                        max-width: 720px;
                        margin: 0.75rem auto 0 auto;
                        color: #a1a1aa;
                        line-height: 1.65;
                        font-size: 0.95rem;
                    ">
                        The application period for this form has ended. Please contact our support team for more information or assistance.
                    </div>
                </div>

                <div style="
                    max-width: 900px;
                    margin: 0 auto 1.5rem auto;
                    padding: 1rem 1.15rem;
                    border-radius: 16px;
                    border: 1px solid rgba(239, 68, 68, 0.38);
                    background: rgba(239, 68, 68, 0.12);
                ">
                    <div style="display: flex; align-items: flex-start; gap: 0.85rem;">
                        <div style="
                            width: 40px;
                            height: 40px;
                            min-width: 40px;
                            border-radius: 999px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #ffffff;
                            font-weight: 900;
                            background: #ef4444;
                        ">!</div>

                        <div>
                            <div style="font-weight: 900; font-size: 1rem; color: #ff5c5c;">
                                ' . e($status['title'] ?? 'This form has expired.') . '
                            </div>

                            <div style="margin-top: 0.35rem; color: #a1a1aa; line-height: 1.55;">
                                ' . e($status['message'] ?? 'You can view this form, but you cannot submit it anymore.') . '
                            </div>

                            <div style="margin-top: 0.65rem; font-size: 0.875rem; color: #a1a1aa;">
                                Application Period:
                                <strong>' . e($status['start_date'] ?? '-') . '</strong>
                                -
                                <strong>' . e($status['end_date'] ?? '-') . '</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="
                    max-width: 1080px;
                    margin: 0 auto;
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 1.25rem;
                ">
                    <div style="
                        border-radius: 22px;
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        background: linear-gradient(180deg, #1c1c20, #18181b);
                        padding: 1.35rem;
                        box-shadow: 0 22px 50px rgba(0, 0, 0, 0.28);
                        border-top: 5px solid #f59e0b;
                    ">
                        <div style="display: flex; align-items: center; gap: 0.9rem; padding-bottom: 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <div style="
                                width: 64px;
                                height: 64px;
                                min-width: 64px;
                                border-radius: 18px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: rgba(245, 158, 11, 0.14);
                                border: 1px solid rgba(245, 158, 11, 0.28);
                                color: #f59e0b;
                                font-weight: 900;
                                font-size: 1.2rem;
                            ">AO</div>

                            <div>
                                <div style="color: #ffffff; font-weight: 900; font-size: 1rem;">ADMISSIONS OFFICE</div>
                                <div style="color: #a1a1aa; margin-top: 0.25rem;">Student Application Support</div>
                            </div>
                        </div>

                        <div style="margin-top: 1rem; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem;">
                            <a href="tel:+85512345678" style="text-decoration: none; text-align: center; padding: 0.9rem 0.7rem; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                                <div style="color: #ffffff; font-weight: 900;">☎</div>
                                <div style="color: #ffffff; font-weight: 800; margin-top: 0.35rem;">Call Us</div>
                                <div style="color: #a1a1aa; font-size: 0.78rem; margin-top: 0.25rem;">+855 12 345 678</div>
                            </a>

                            <a href="mailto:admission@uhs.edu.kh" style="text-decoration: none; text-align: center; padding: 0.9rem 0.7rem; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                                <div style="color: #ffffff; font-weight: 900;">✉</div>
                                <div style="color: #ffffff; font-weight: 800; margin-top: 0.35rem;">Email Us</div>
                                <div style="color: #a1a1aa; font-size: 0.78rem; margin-top: 0.25rem;">admission@uhs.edu.kh</div>
                            </a>

                            <a href="https://t.me/uhs_admission" target="_blank" style="text-decoration: none; text-align: center; padding: 0.9rem 0.7rem; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                                <div style="color: #ffffff; font-weight: 900;">✈</div>
                                <div style="color: #ffffff; font-weight: 800; margin-top: 0.35rem;">Telegram</div>
                                <div style="color: #a1a1aa; font-size: 0.78rem; margin-top: 0.25rem;">@uhs_admission</div>
                            </a>
                        </div>
                    </div>

                    <div style="
                        border-radius: 22px;
                        border: 1px solid rgba(255, 255, 255, 0.10);
                        background: linear-gradient(180deg, #1c1c20, #18181b);
                        padding: 1.35rem;
                        box-shadow: 0 22px 50px rgba(0, 0, 0, 0.28);
                        border-top: 5px solid #f59e0b;
                    ">
                        <div style="display: flex; align-items: center; gap: 0.9rem; padding-bottom: 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <div style="
                                width: 64px;
                                height: 64px;
                                min-width: 64px;
                                border-radius: 18px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: rgba(245, 158, 11, 0.14);
                                border: 1px solid rgba(245, 158, 11, 0.28);
                                color: #f59e0b;
                                font-weight: 900;
                                font-size: 1.2rem;
                            ">RO</div>

                            <div>
                                <div style="color: #ffffff; font-weight: 900; font-size: 1rem;">REGISTRAR OFFICE</div>
                                <div style="color: #a1a1aa; margin-top: 0.25rem;">Registration Support</div>
                            </div>
                        </div>

                        <div style="margin-top: 1rem; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem;">
                            <a href="tel:+85523123456" style="text-decoration: none; text-align: center; padding: 0.9rem 0.7rem; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                                <div style="color: #ffffff; font-weight: 900;">☎</div>
                                <div style="color: #ffffff; font-weight: 800; margin-top: 0.35rem;">Call Us</div>
                                <div style="color: #a1a1aa; font-size: 0.78rem; margin-top: 0.25rem;">+855 23 123 456</div>
                            </a>

                            <a href="mailto:registrar@uhs.edu.kh" style="text-decoration: none; text-align: center; padding: 0.9rem 0.7rem; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                                <div style="color: #ffffff; font-weight: 900;">✉</div>
                                <div style="color: #ffffff; font-weight: 800; margin-top: 0.35rem;">Email Us</div>
                                <div style="color: #a1a1aa; font-size: 0.78rem; margin-top: 0.25rem;">registrar@uhs.edu.kh</div>
                            </a>

                            <a href="https://t.me/uhs_registrar" target="_blank" style="text-decoration: none; text-align: center; padding: 0.9rem 0.7rem; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                                <div style="color: #ffffff; font-weight: 900;">✈</div>
                                <div style="color: #ffffff; font-weight: 800; margin-top: 0.35rem;">Telegram</div>
                                <div style="color: #a1a1aa; font-size: 0.78rem; margin-top: 0.25rem;">@uhs_registrar</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        ');
    }

    public function getTableRecords(): \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Contracts\Pagination\CursorPaginator
    {
        if ($this->isStudentPanel() && (($this->closingDateStatus()['status'] ?? null) === 'expired')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        return parent::getTableRecords();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->authorize(fn (): bool => RoleAccess::hasSystemPermission('Print:CustomFormEntry'))
                ->url('#')
                ->extraAttributes([
                    'onclick' => 'window.print(); return false;',
                ]),

            Actions\Action::make('exportData')
                ->label('Export')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->authorize(fn (): bool => RoleAccess::hasSystemPermission('ExportData:CustomFormEntry'))
                ->action('exportData'),

            Actions\CreateAction::make()
                ->label('New')
                ->icon('heroicon-o-plus')
                ->color('warning')
                ->url(fn (): string => $this->getCreateUrl())
                ->visible(fn (): bool => ! $this->isStudentPanel() || $this->canSubmitCurrentForm()),
        ];
    }

    public function exportData(): StreamedResponse
    {
        $form = $this->getActiveForm();

        $filename = str($form?->name ?: 'custom-form-entries')
            ->slug()
            ->append('-')
            ->append(now()->format('Y-m-d-His'))
            ->append('.csv')
            ->toString();

        $query = static::getResource()::getEloquentQuery();

        if ($form) {
            $query->where('custom_form_id', $form->id);
        }

        if ($this->isStudentPanel()) {
            $query->where('created_by', auth()->id());
        }

        $records = $query->latest('id')->get();

        return response()->streamDownload(function () use ($records) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID',
                'Form ID',
                'Created By',
                'Created At',
                'Data',
            ]);

            foreach ($records as $record) {
                fputcsv($file, [
                    $record->id,
                    $record->custom_form_id,
                    $record->created_by,
                    optional($record->created_at)->format('Y-m-d H:i:s'),
                    is_array($record->data)
                        ? json_encode($record->data, JSON_UNESCAPED_UNICODE)
                        : $record->data,
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function getCreateUrl(): string
    {
        $formId = $this->getActiveFormId();

        if (! $formId) {
            return static::getResource()::getUrl('create');
        }

        return static::getResource()::getUrl('create', [
            'tableFilters' => [
                'custom_form_id' => [
                    'value' => $formId,
                ],
            ],
        ]);
    }

    protected function getActiveFormId(): ?int
    {
        $formId = data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->integer('custom_form_id')
            ?? request()->integer('form_id');

        return filled($formId) ? (int) $formId : null;
    }

    protected function getActiveForm(): ?CustomForm
    {
        $formId = $this->getActiveFormId();

        if (! $formId) {
            return null;
        }

        return CustomForm::query()->find($formId);
    }

    protected function closingDateStatus(): array
    {
        return ClosingDateWorkflow::checkByCustomFormId($this->getActiveFormId());
    }

    protected function canSubmitCurrentForm(): bool
    {
        return $this->closingDateStatus()['can_submit'] ?? true;
    }

    protected function isStudentPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'student';
    }
}
