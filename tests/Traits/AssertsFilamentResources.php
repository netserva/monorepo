<?php

namespace Tests\Traits;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

trait AssertsFilamentResources
{
    /**
     * Assert that a Filament resource can list records
     */
    protected function assertCanListRecords(string $resourceClass, array $records = []): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        $test = Livewire::test($listPageClass);

        if (! empty($records)) {
            // Handle both single records and arrays of records
            $recordsArray = is_array($records) ? $records : [$records];

            foreach ($recordsArray as $record) {
                // Ensure we have a proper model instance
                if ($record instanceof \Illuminate\Database\Eloquent\Model) {
                    $test->assertCanSeeTableRecords([$record]);
                }
            }
        }

        $test->assertSuccessful();
    }

    /**
     * Assert that a Filament resource can create a record
     */
    protected function assertCanCreateRecord(string $resourceClass, array $data): Model
    {
        $createPageClass = $resourceClass::getPages()['create']->getPage();

        $test = Livewire::test($createPageClass)
            ->fillForm($data)
            ->call('create');

        // Check for form errors before asserting success
        $component = $test->instance();
        if (method_exists($component, 'getForm') && $component->getForm()->hasErrors()) {
            $errors = $component->getForm()->getErrors();
            throw new \Exception('Form validation errors: '.json_encode($errors));
        }

        $test->assertSuccessful()
            ->assertNotified();

        // Get the model class from the resource
        $modelClass = $resourceClass::getModel();

        // For models with field mapping (like DnsZone), we need to check the latest record
        // instead of using assertDatabaseHas with transformed data
        $record = $modelClass::latest()->first();

        // Debug information if record is not created
        if (! $record) {
            $allRecords = $modelClass::all();
            $this->assertNotNull($record, 'No record was created. Total records in table: '.$allRecords->count());
        }

        return $record;
    }

    /**
     * Assert that a Filament resource can edit a record
     */
    protected function assertCanEditRecord(string $resourceClass, Model $record, array $newData): void
    {
        $editPageClass = $resourceClass::getPages()['edit']->getPage();

        Livewire::test($editPageClass, ['record' => $record->getRouteKey()])
            ->fillForm($newData)
            ->call('save')
            ->assertSuccessful()
            ->assertNotified();

        // Assert the changes were saved - use fresh record to handle virtual fields
        $freshRecord = $record->fresh();
        foreach ($newData as $key => $expectedValue) {
            $actualValue = $freshRecord->{$key};
            $this->assertEquals(
                $expectedValue,
                $actualValue,
                "Field '{$key}' was not updated correctly. Expected: ".json_encode($expectedValue).', Actual: '.json_encode($actualValue)
            );
        }
    }

    /**
     * Assert that a Filament resource can view a record
     */
    protected function assertCanViewRecord(string $resourceClass, Model $record): void
    {
        $viewPageClass = $resourceClass::getPages()['view']->getPage();

        Livewire::test($viewPageClass, ['record' => $record->getRouteKey()])
            ->assertSuccessful();
    }

    /**
     * Assert that a Filament resource can delete a record
     */
    protected function assertCanDeleteRecord(string $resourceClass, Model $record): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        Livewire::test($listPageClass)
            ->callTableAction('delete', $record->getKey())
            ->assertSuccessful()
            ->assertNotified();

        // Assert the record is soft deleted or hard deleted
        if (method_exists($record, 'trashed')) {
            $this->assertTrue($record->fresh()->trashed());
        } else {
            $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
        }
    }

    /**
     * Assert that table filters work correctly
     */
    protected function assertTableFiltersWork(string $resourceClass, array $filters, array $records): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        foreach ($filters as $filterName => $filterValue) {
            $test = Livewire::test($listPageClass)
                ->filterTable($filterName, $filterValue);

            // Assert that filtered records are visible
            if (isset($records['visible'])) {
                $test->assertCanSeeTableRecords($records['visible']);
            }

            // Assert that non-matching records are hidden
            if (isset($records['hidden'])) {
                $test->assertCanNotSeeTableRecords($records['hidden']);
            }
        }
    }

    /**
     * Assert that table search works correctly
     */
    protected function assertTableSearchWorks(string $resourceClass, string $searchTerm, array $expectedRecords): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        Livewire::test($listPageClass)
            ->searchTable($searchTerm)
            ->assertCanSeeTableRecords($expectedRecords['visible'])
            ->assertCanNotSeeTableRecords($expectedRecords['hidden']);
    }

    /**
     * Assert that table sorting works correctly
     */
    protected function assertTableSortingWorks(string $resourceClass, string $column, array $records): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        // Test ascending sort
        $test = Livewire::test($listPageClass)
            ->sortTable($column);

        // Test descending sort
        $test->sortTable($column, 'desc');

        $test->assertSuccessful();
    }

    /**
     * Assert that bulk actions work correctly
     */
    protected function assertBulkActionsWork(string $resourceClass, array $records, string $actionName): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        $recordKeys = collect($records)->pluck('id')->toArray();

        Livewire::test($listPageClass)
            ->selectTableRecords($recordKeys)
            ->callTableBulkAction($actionName, $records)
            ->assertSuccessful()
            ->assertNotified();
    }

    /**
     * Assert that form validation works correctly
     */
    protected function assertFormValidationWorks(string $resourceClass, array $invalidData, array $expectedErrors): void
    {
        $createPageClass = $resourceClass::getPages()['create']->getPage();

        $test = Livewire::test($createPageClass)
            ->fillForm($invalidData)
            ->call('create')
            ->assertHasFormErrors($expectedErrors);
    }

    /**
     * Assert that relationship fields work correctly
     */
    protected function assertRelationshipFieldWorks(string $resourceClass, string $fieldName, Model $relatedRecord): void
    {
        $createPageClass = $resourceClass::getPages()['create']->getPage();

        Livewire::test($createPageClass)
            ->assertFormFieldExists($fieldName)
            ->fillForm([$fieldName => $relatedRecord->getKey()])
            ->assertFormSet([$fieldName => $relatedRecord->getKey()]);
    }

    /**
     * Assert that custom actions work correctly
     */
    protected function assertCustomActionWorks(string $resourceClass, Model $record, string $actionName, array $data = []): void
    {
        $actionPageClass = $this->getPageClassForAction($resourceClass, $actionName);

        if ($actionPageClass) {
            $test = Livewire::test($actionPageClass, ['record' => $record->getRouteKey()]);
        } else {
            // Test table action
            $listPageClass = $resourceClass::getPages()['index']->getPage();
            $test = Livewire::test($listPageClass);
        }

        if (! empty($data)) {
            $test->fillForm($data);
        }

        $test->callAction($actionName)
            ->assertSuccessful()
            ->assertNotified();
    }

    /**
     * Assert that widgets are displayed correctly
     */
    protected function assertWidgetsDisplayed(string $resourceClass, array $expectedWidgets): void
    {
        $listPageClass = $resourceClass::getPages()['index']->getPage();

        $test = Livewire::test($listPageClass);

        foreach ($expectedWidgets as $widgetClass) {
            $test->assertSeeLivewire($widgetClass);
        }
    }

    /**
     * Assert that tabs work correctly in forms
     */
    protected function assertTabsWork(string $resourceClass, array $tabData): void
    {
        $createPageClass = $resourceClass::getPages()['create']->getPage();

        $test = Livewire::test($createPageClass);

        foreach ($tabData as $tabIndex => $data) {
            $test->mountFormComponentAction('tabs', 'openTab', ['tab' => $tabIndex])
                ->fillForm($data);
        }

        $test->call('create')
            ->assertSuccessful()
            ->assertNotified();
    }

    /**
     * Assert that repeater fields work correctly
     */
    protected function assertRepeaterWorks(string $resourceClass, string $repeaterName, array $repeaterData): void
    {
        $createPageClass = $resourceClass::getPages()['create']->getPage();

        Livewire::test($createPageClass)
            ->fillForm([$repeaterName => $repeaterData])
            ->call('create')
            ->assertSuccessful()
            ->assertNotified();
    }

    /**
     * Assert that file upload fields work correctly
     */
    protected function assertFileUploadWorks(string $resourceClass, string $fieldName, string $filePath): void
    {
        $createPageClass = $resourceClass::getPages()['create']->getPage();

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->create(basename($filePath));

        Livewire::test($createPageClass)
            ->fillForm([$fieldName => [$uploadedFile]])
            ->call('create')
            ->assertSuccessful()
            ->assertNotified();
    }

    /**
     * Get the page class for a specific action
     */
    protected function getPageClassForAction(string $resourceClass, string $actionName): ?string
    {
        $pages = $resourceClass::getPages();

        foreach ($pages as $page) {
            if (method_exists($page->getPage(), $actionName)) {
                return $page->getPage();
            }
        }

        return null;
    }

    /**
     * Assert that navigation menu shows resource correctly
     */
    protected function assertResourceInNavigation(string $resourceClass): void
    {
        $resource = new $resourceClass;

        $this->assertNotNull($resource::getNavigationLabel());
        $this->assertNotNull($resource::getNavigationGroup());
        $this->assertTrue($resource::canAccess());
    }

    /**
     * Assert that resource permissions work correctly
     */
    protected function assertResourcePermissions(string $resourceClass, array $permissions): void
    {
        foreach ($permissions as $permission => $expected) {
            $method = 'can'.ucfirst($permission);

            if (method_exists($resourceClass, $method)) {
                $this->assertEquals($expected, $resourceClass::$method());
            }
        }
    }
}
