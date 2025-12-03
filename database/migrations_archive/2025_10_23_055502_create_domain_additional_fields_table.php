<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * TLD-specific additional fields (e.g., .au eligibility requirements)
     * Inspired by WHMCS tbldomainsadditionalfields
     */
    public function up(): void
    {
        Schema::create('domain_additional_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sw_domain_id')->constrained('sw_domains')->onDelete('cascade');
            $table->string('field_name'); // e.g., "Registrant Name", "Eligibility Type"
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->index(['sw_domain_id', 'field_name']);
            $table->index('field_name'); // For querying by field type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_additional_fields');
    }
};
