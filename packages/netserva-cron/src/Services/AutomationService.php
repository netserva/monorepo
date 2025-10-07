<?php

namespace NetServa\Cron\Services;

use Illuminate\Support\Facades\Process;
use NetServa\Cron\Models\AutomationJob;
use NetServa\Cron\Models\AutomationTask;

class AutomationService
{
    /**
     * Create a new task
     */
    public function createTask(array $data): AutomationTask
    {
        $taskData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'task_type' => $data['task_type'] ?? 'shell',
            'command' => $data['command'],
            'target_host' => $data['target_host'] ?? 'localhost',
            'target_user' => $data['target_user'] ?? 'root',
            'timeout_seconds' => $data['timeout_seconds'] ?? 300,
            'max_retries' => $data['max_retries'] ?? 3,
            'retry_delay_seconds' => $data['retry_delay_seconds'] ?? 30,
            'is_active' => $data['is_active'] ?? true,
            'status' => 'active',
            'priority' => $data['priority'] ?? 2,
            'success_rate' => 0,
            'tags' => $data['tags'] ?? [],
            'metadata' => $data['metadata'] ?? [],
            'created_by' => auth()->user()?->name ?? 'system',
        ];

        return AutomationTask::create($taskData);
    }

    /**
     * Execute a task
     */
    public function executeTask(AutomationTask $task): AutomationJob
    {
        // Create job record
        $job = AutomationJob::create([
            'job_name' => "Execute {$task->name}",
            'automation_task_id' => $task->id,
            'status' => 'pending',
            'priority' => $task->priority === 1 ? 'low' : ($task->priority === 3 ? 'high' : 'normal'),
            'target_host' => $task->target_host,
            'target_user' => $task->target_user,
            'command_executed' => $task->command,
            'progress_percent' => 0,
            'tags' => $task->tags ?? [],
            'metadata' => $task->metadata ?? [],
            'created_by' => auth()->user()?->name ?? 'system',
        ]);

        try {
            $job->start();

            // Execute the command based on task type
            $result = $this->executeCommand($task, $job);

            if ($result['success']) {
                $job->update([
                    'stdout' => $result['output'],
                    'exit_code' => $result['exit_code'],
                ]);
                $job->complete();
            } else {
                $job->update([
                    'stderr' => $result['error'],
                    'exit_code' => $result['exit_code'],
                ]);
                $job->fail($result['error']);
            }
        } catch (\Exception $e) {
            $job->fail($e->getMessage());
        }

        return $job;
    }

    /**
     * Execute a command
     */
    protected function executeCommand(AutomationTask $task, AutomationJob $job): array
    {
        $command = $task->command;

        try {
            switch ($task->task_type) {
                case 'shell':
                    $result = Process::run($command);
                    break;

                case 'ssh':
                    // For SSH commands, prepend with ssh connection
                    $sshCommand = "ssh {$task->target_user}@{$task->target_host} '{$command}'";
                    $result = Process::run($sshCommand);
                    break;

                case 'script':
                    // Execute script file
                    $result = Process::run($command);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown task type: {$task->task_type}");
            }

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => 1,
            ];
        }
    }

    /**
     * Get task execution history
     */
    public function getTaskHistory(AutomationTask $task, int $limit = 10): array
    {
        return $task->executions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Validate task data
     */
    public function validateTask(array $taskData): array
    {
        $errors = [];

        if (! isset($taskData['name']) || empty($taskData['name'])) {
            $errors[] = 'Task name is required';
        }

        if (! isset($taskData['command']) || empty($taskData['command'])) {
            $errors[] = 'Command is required';
        }

        if (isset($taskData['task_type']) && ! in_array($taskData['task_type'], ['shell', 'ssh', 'script'])) {
            $errors[] = 'Invalid task type. Must be shell, ssh, or script';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
