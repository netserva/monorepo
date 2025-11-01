<?php

namespace Pest\Livewire {
    use Livewire\Livewire;

    if (! function_exists('Pest\Livewire\livewire')) {
        /**
         * Test a Livewire component
         */
        function livewire(string $component, array $params = [])
        {
            return Livewire::test($component, $params);
        }
    }
}
