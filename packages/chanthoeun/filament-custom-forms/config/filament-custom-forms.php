<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | If you want to use your own models, you can define them here.
    | They must extend the original models.
    |
    */
    'models' => [
        'form' => \Chanthoeun\FilamentCustomForms\Models\CustomForm::class,
        'entry' => \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::class,
        'user' => config('auth.providers.users.model') ?? 'App\Models\User',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Define the disk and directory where file uploads should be stored.
    |
    */
    'uploads' => [
        'disk' => 'public',
        'directory' => 'custom-form-uploads',
        'visibility' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Customize the navigation icons and groups.
    |
    */
    'navigation' => [
        'group' => 'Form Builder',
        'entry_group' => 'Form Entry',
        'icon' => 'heroicon-o-rectangle-stack',
        'entry_icon' => 'heroicon-o-document-text',
        'dynamic_navigation' => true,
    ],
];
