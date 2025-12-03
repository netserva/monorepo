<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('post'); // post, portfolio, etc.
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('type');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_categories');
    }
};
