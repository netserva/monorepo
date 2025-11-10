<?php

declare(strict_types=1);

use NetServa\Cms\Models\Theme;
use NetServa\Cms\Models\ThemeSetting;

describe('ThemeSetting Model', function () {
    it('can create a theme setting', function () {
        $theme = Theme::create([
            'name' => 'test-theme',
            'display_name' => 'Test Theme',
        ]);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'colors.primary',
            'value' => '#DC2626',
            'type' => 'color',
            'category' => 'colors',
        ]);

        expect($setting)->toBeInstanceOf(ThemeSetting::class)
            ->and($setting->key)->toBe('colors.primary')
            ->and($setting->value)->toBe('#DC2626')
            ->and($setting->type)->toBe('color')
            ->and($setting->category)->toBe('colors');
    });

    it('belongs to theme', function () {
        $theme = Theme::create([
            'name' => 'parent-theme',
            'display_name' => 'Parent Theme',
        ]);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.key',
            'value' => 'test',
        ]);

        expect($setting->theme)->toBeInstanceOf(Theme::class)
            ->and($setting->theme->name)->toBe('parent-theme');
    });
});

describe('Type Casting', function () {
    it('casts boolean values correctly', function () {
        $theme = Theme::create(['name' => 'bool-theme', 'display_name' => 'Bool']);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.bool',
            'value' => '1',
            'type' => 'boolean',
        ]);

        expect($setting->getTypedValue())->toBeTrue()
            ->and($setting->getTypedValue())->toBeBool();
    });

    it('casts integer values correctly', function () {
        $theme = Theme::create(['name' => 'int-theme', 'display_name' => 'Int']);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.int',
            'value' => '42',
            'type' => 'integer',
        ]);

        expect($setting->getTypedValue())->toBe(42)
            ->and($setting->getTypedValue())->toBeInt();
    });

    it('casts float values correctly', function () {
        $theme = Theme::create(['name' => 'float-theme', 'display_name' => 'Float']);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.float',
            'value' => '3.14',
            'type' => 'float',
        ]);

        expect($setting->getTypedValue())->toBe(3.14)
            ->and($setting->getTypedValue())->toBeFloat();
    });

    it('casts json values correctly', function () {
        $theme = Theme::create(['name' => 'json-theme', 'display_name' => 'JSON']);

        $array = ['key' => 'value', 'nested' => ['foo' => 'bar']];

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.json',
            'value' => json_encode($array),
            'type' => 'json',
        ]);

        expect($setting->getTypedValue())->toBeArray()
            ->and($setting->getTypedValue())->toBe($array);
    });

    it('returns color as string', function () {
        $theme = Theme::create(['name' => 'color-theme', 'display_name' => 'Color']);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.color',
            'value' => '#FF0000',
            'type' => 'color',
        ]);

        expect($setting->getTypedValue())->toBe('#FF0000')
            ->and($setting->getTypedValue())->toBeString();
    });

    it('returns string by default', function () {
        $theme = Theme::create(['name' => 'string-theme', 'display_name' => 'String']);

        $setting = ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'test.string',
            'value' => 'test value',
            'type' => 'string',
        ]);

        expect($setting->getTypedValue())->toBe('test value')
            ->and($setting->getTypedValue())->toBeString();
    });
});

describe('Auto Type Detection', function () {
    it('detects boolean type', function () {
        $theme = Theme::create(['name' => 'auto-bool', 'display_name' => 'Auto Bool']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.bool',
        ]);

        $setting->setTypedValue(true);

        expect($setting->type)->toBe('boolean')
            ->and($setting->value)->toBe('1');

        $setting->setTypedValue(false);

        expect($setting->value)->toBe('0');
    });

    it('detects integer type', function () {
        $theme = Theme::create(['name' => 'auto-int', 'display_name' => 'Auto Int']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.int',
        ]);

        $setting->setTypedValue(42);

        expect($setting->type)->toBe('integer')
            ->and($setting->value)->toBe('42');
    });

    it('detects float type', function () {
        $theme = Theme::create(['name' => 'auto-float', 'display_name' => 'Auto Float']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.float',
        ]);

        $setting->setTypedValue(3.14);

        expect($setting->type)->toBe('float')
            ->and($setting->value)->toBe('3.14');
    });

    it('detects array/json type', function () {
        $theme = Theme::create(['name' => 'auto-array', 'display_name' => 'Auto Array']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.array',
        ]);

        $array = ['foo' => 'bar'];
        $setting->setTypedValue($array);

        expect($setting->type)->toBe('json')
            ->and(json_decode($setting->value, true))->toBe($array);
    });

    it('detects hex color type', function () {
        $theme = Theme::create(['name' => 'auto-color', 'display_name' => 'Auto Color']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.color',
        ]);

        $setting->setTypedValue('#DC2626');

        expect($setting->type)->toBe('color')
            ->and($setting->value)->toBe('#DC2626');
    });

    it('detects 3-digit hex color', function () {
        $theme = Theme::create(['name' => 'auto-color-3', 'display_name' => 'Auto Color 3']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.color3',
        ]);

        $setting->setTypedValue('#F00');

        expect($setting->type)->toBe('color')
            ->and($setting->value)->toBe('#F00');
    });

    it('defaults to string type', function () {
        $theme = Theme::create(['name' => 'auto-string', 'display_name' => 'Auto String']);

        $setting = new ThemeSetting([
            'cms_theme_id' => $theme->id,
            'key' => 'auto.string',
        ]);

        $setting->setTypedValue('test value');

        expect($setting->type)->toBe('string')
            ->and($setting->value)->toBe('test value');
    });
});

describe('Query Scopes', function () {
    beforeEach(function () {
        $theme = Theme::create(['name' => 'scope-theme', 'display_name' => 'Scope']);

        ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'color1',
            'value' => '#FF0000',
            'category' => 'colors',
        ]);

        ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'font1',
            'value' => 'Inter',
            'category' => 'typography',
        ]);

        ThemeSetting::create([
            'cms_theme_id' => $theme->id,
            'key' => 'width',
            'value' => '1200px',
            'category' => 'layout',
        ]);
    });

    it('scopes by category', function () {
        $colors = ThemeSetting::category('colors')->get();

        expect($colors)->toHaveCount(1)
            ->and($colors->first()->category)->toBe('colors');
    });

    it('scopes to colors', function () {
        $colors = ThemeSetting::colors()->get();

        expect($colors)->toHaveCount(1)
            ->and($colors->first()->category)->toBe('colors');
    });

    it('scopes to typography', function () {
        $typography = ThemeSetting::typography()->get();

        expect($typography)->toHaveCount(1)
            ->and($typography->first()->category)->toBe('typography');
    });

    it('scopes to layout', function () {
        $layout = ThemeSetting::layout()->get();

        expect($layout)->toHaveCount(1)
            ->and($layout->first()->category)->toBe('layout');
    });
});
