<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Create CMS Settings Migration
 *
 * Populates the settings table with default CMS values from config files.
 * This migration only runs when netserva-core (with Spatie Settings) is installed.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // Load defaults from CmsSettings class (which reads from config)
        $defaults = \NetServa\Cms\Settings\CmsSettings::defaults();

        // Create each setting in the database
        foreach ($defaults as $key => $value) {
            $this->migrator->add("cms.{$key}", $value);
        }
    }

    public function down(): void
    {
        // Remove all CMS settings
        $defaults = \NetServa\Cms\Settings\CmsSettings::defaults();

        foreach (array_keys($defaults) as $key) {
            $this->migrator->delete("cms.{$key}");
        }
    }
};
