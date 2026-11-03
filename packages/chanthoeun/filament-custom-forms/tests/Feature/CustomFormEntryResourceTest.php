<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class CustomFormEntryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = \Chanthoeun\FilamentCustomForms\Tests\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $this->actingAs($user);
    }

    /** @test */
    public function it_can_render_page()
    {
        Livewire::test(Pages\ListCustomFormEntries::class)
            ->assertSuccessful();
    }

    /** @test */
    public function it_can_create_entry()
    {
        $form = CustomForm::factory()->create(['name' => 'Registration Form']);
        
        // Add a field to the form
        $form->fields()->create([
            'name' => 'full_name',
            'label' => 'Full Name',
            'type' => 'text_input',
            'required' => true,
        ]);

        Livewire::test(Pages\CreateCustomFormEntry::class)
            ->fillForm([
                'custom_form_id' => $form->id,
                'data.full_name' => 'John Doe',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_form_entries', [
            'custom_form_id' => $form->id,
        ]);
        
        $entry = CustomFormEntry::first();
        $this->assertEquals('John Doe', $entry->data['full_name']);
    }
}
