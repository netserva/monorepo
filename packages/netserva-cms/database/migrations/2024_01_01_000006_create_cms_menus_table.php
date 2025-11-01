<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->unique(); // header, footer, sidebar
            $table->json('items')->nullable(); // Array of menu items with hierarchy
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('location');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
    }
};
