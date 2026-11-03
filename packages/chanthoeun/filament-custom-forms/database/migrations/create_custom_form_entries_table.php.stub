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
        if (!Schema::hasTable('custom_form_entries')) {
            Schema::create('custom_form_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
                $table->json('data')->nullable(); // Key-value pairs matching the schema
                
                if (Schema::hasTable('users')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('created_by')->nullable();
                }

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_form_entries');
    }
};
