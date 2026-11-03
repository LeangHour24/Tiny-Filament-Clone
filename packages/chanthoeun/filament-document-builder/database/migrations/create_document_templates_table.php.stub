<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable(); // e.g. invoice, certificate
            $table->string('model_class')->nullable(); // Database model reference
            $table->longText('content')->nullable(); // Stores the HTML
            $table->json('page_settings')->nullable(); // Paper size, orientation, margins
            $table->json('extra_data_sources')->nullable(); // Additional data models
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_templates');
    }
};
