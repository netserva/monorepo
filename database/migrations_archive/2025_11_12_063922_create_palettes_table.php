<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('palettes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'slate', 'rose', 'emerald'
            $table->string('label'); // 'Slate', 'Rose', 'Emerald'
            $table->string('group')->default('default'); // 'neutral', 'colors'
            $table->text('description')->nullable();
            $table->json('colors'); // ['primary' => 'slate', 'success' => 'emerald', ...]
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('palettes');
    }
};
