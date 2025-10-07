<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // database, api, csv
            $table->json('connection'); // Connection config (host, database, etc)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_data_sources');
    }
};
