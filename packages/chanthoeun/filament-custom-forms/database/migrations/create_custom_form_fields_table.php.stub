<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('custom_form_fields')) {
            Schema::create('custom_form_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('custom_form_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('custom_form_fields')->cascadeOnDelete();
                $table->string('name');
                $table->string('label')->nullable();
                $table->string('type');
                $table->boolean('required')->default(false);
                $table->json('options')->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_form_fields');
    }
};
