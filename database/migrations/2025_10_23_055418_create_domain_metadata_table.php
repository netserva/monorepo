<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Key-value metadata storage for sw_domains
     * Inspired by WHMCS tbldomains_extra pattern
     */
    public function up(): void
    {
        Schema::create('domain_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sw_domain_id')->constrained('sw_domains')->onDelete('cascade');
            $table->string('key', 64);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['sw_domain_id', 'key']);
            $table->index('key'); // For querying by metadata key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_metadata');
    }
};
