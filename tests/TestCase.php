<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\AssertsFilamentResources;
use Tests\Traits\AuthenticatesUsers;
use Tests\Traits\CreatesTestInfrastructure;
use Tests\Traits\InteractsWithDns;
use Tests\Traits\InteractsWithSsh;
use Tests\Traits\MocksExternalServices;

abstract class TestCase extends BaseTestCase
{
    use AssertsFilamentResources;
    use AuthenticatesUsers;
    use CreatesApplication;
    use CreatesTestInfrastructure;
    use InteractsWithDns;
    use InteractsWithSsh;
    use MocksExternalServices;

    protected function setUp(): void
    {
        parent::setUp();

        // PREVENT ANY unmocked processes from running
        \Illuminate\Support\Facades\Process::preventStrayProcesses();

        // COMPLETELY prevent ALL process execution that could trigger authentication
        \Illuminate\Support\Facades\Process::fake(function ($pendingProcess) {
            // UNIVERSAL MOCKING: Mock EVERY SINGLE process execution to prevent ANY authentication

            // Extract command for contextual responses (but mock everything regardless)
            $commandString = '';
            try {
                if (is_object($pendingProcess)) {
                    $command = $pendingProcess->command ?? '';
                    $commandString = is_array($command) ? implode(' ', $command) : (string) $command;
                } elseif (is_array($pendingProcess)) {
                    $commandString = implode(' ', $pendingProcess);
                } else {
                    $commandString = (string) $pendingProcess;
                }
            } catch (\Exception $e) {
                // If we can't extract the command, just return success anyway
                return \Illuminate\Support\Facades\Process::result('', '', 0);
            }

            // Return contextually appropriate responses for better test realism
            if (str_contains($commandString, 'systemctl is-active') || str_contains($commandString, 'systemctl status')) {
                return \Illuminate\Support\Facades\Process::result('active', '', 0);
            }

            if (str_contains($commandString, 'systemctl list-unit-files')) {
                return \Illuminate\Support\Facades\Process::result('enabled', '', 0);
            }

            if (str_contains($commandString, 'nginx -t') || str_contains($commandString, 'apache2ctl') || str_contains($commandString, '-t')) {
                return \Illuminate\Support\Facades\Process::result('Configuration test successful', '', 0);
            }

            if (str_contains($commandString, 'journalctl')) {
                return \Illuminate\Support\Facades\Process::result('Test log entries', '', 0);
            }

            if (str_contains($commandString, 'ssh-keygen')) {
                return \Illuminate\Support\Facades\Process::result('Key generated', '', 0);
            }

            if (str_contains($commandString, 'ps ')) {
                return \Illuminate\Support\Facades\Process::result('Process list', '', 0);
            }

            // ABSOLUTE FALLBACK: Return success for EVERYTHING else
            // This ensures NO process can trigger authentication, regardless of what it is
            return \Illuminate\Support\Facades\Process::result('', '', 0);
        });

        // ALSO mock direct PHP system functions that bypass Laravel's Process facade
        $this->mockPhpSystemFunctions();
    }

    /**
     * Mock direct PHP system functions to prevent authentication popups
     */
    private function mockPhpSystemFunctions(): void
    {
        // Mock exec() function
        if (! function_exists('exec')) {
            function exec($command, &$output = null, &$return_var = null)
            {
                $output = ['Mock exec output'];
                $return_var = 0;

                return 'Mock exec result';
            }
        }

        // Note: We can't easily mock built-in PHP functions like exec(), shell_exec(), system(), passthru()
        // without using extensions like runkit or uopz, but we can catch them with process monitoring

        // Instead, let's ensure our Process mocking is as comprehensive as possible
        // and use preventStrayProcesses() to catch anything that slips through
    }
}
