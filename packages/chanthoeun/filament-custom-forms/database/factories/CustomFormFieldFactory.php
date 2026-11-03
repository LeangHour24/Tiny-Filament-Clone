<?php

namespace Chanthoeun\FilamentCustomForms\Database\Factories;

use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFormFieldFactory extends Factory
{
    protected $model = CustomFormField::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'label' => $this->faker->word,
            'type' => 'text_input',
            'required' => false,
            'sort' => 0,
            'options' => [],
        ];
    }
}
