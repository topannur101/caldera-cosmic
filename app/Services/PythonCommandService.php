<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonCommandService
{
    private string $baseUrl;

    private int $timeout;

    private int $cacheTime;

    public function __construct()
    {
        $this->baseUrl = config('services.python_command_manager.url', 'http://127.0.0.1:8765');
        $this->timeout = config('services.python_command_manager.timeout', 10);
        $this->cacheTime = config('services.python_command_manager.cache_time', 30); // seconds
    }

    /**
     * Check if the Python API is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/health");

            return $response->successful() && $response->json('success', false);
        } catch (Exception $e) {
            Log::warning('Python Command Manager API unavailable', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl,
            ]);

            return false;
        }
    }

    /**
     * Get all commands with their status
     */
    public function getCommands(): array
    {
        $cacheKey = 'python_commands_list';

        return Cache::remember($cacheKey, $this->cacheTime, function () {
            try {
                $response = Http::timeout($this->timeout)
                    ->get("{$this->baseUrl}/api/commands");

                if ($response->successful()) {
                    $data = $response->json();

                    return $data['success'] ? $data['data'] : [];
                }

                Log::error('Failed to fetch commands from Python API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            } catch (RequestException $e) {
                Log::error('Python Command API request failed', [
                    'error' => $e->getMessage(),
                    'url' => "{$this->baseUrl}/api/commands",
                ]);

                return [];
            }
        });
    }

    /**
     * Start a specific command
     */
    public function startCommand(string $commandId): array
    {
        try {
            // Clear cache when making changes
            $this->clearCache();

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/commands/{$commandId}/start");

            $data = $response->json();

            if ($response->successful() && $data['success']) {
                Log::info('Command started successfully', [
                    'command_id' => $commandId,
                    'message' => $data['message'] ?? 'Command started',
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Command started successfully',
                ];
            }

            Log::warning('Failed to start command', [
                'command_id' => $commandId,
                'status' => $response->status(),
                'error' => $data['error'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'message' => $data['error'] ?? 'Failed to start command',
            ];

        } catch (RequestException $e) {
            Log::error('Failed to start command via API', [
                'command_id' => $commandId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'API communication error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Stop a specific command
     */
    public function stopCommand(string $commandId): array
    {
        try {
            // Clear cache when making changes
            $this->clearCache();

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/commands/{$commandId}/stop");

            $data = $response->json();

            if ($response->successful() && $data['success']) {
                Log::info('Command stopped successfully', [
                    'command_id' => $commandId,
                    'message' => $data['message'] ?? 'Command stopped',
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Command stopped successfully',
                ];
            }

            Log::warning('Failed to stop command', [
                'command_id' => $commandId,
                'status' => $response->status(),
                'error' => $data['error'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'message' => $data['error'] ?? 'Failed to stop command',
            ];

        } catch (RequestException $e) {
            Log::error('Failed to stop command via API', [
                'command_id' => $commandId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'API communication error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get detailed status of a specific command
     */
    public function getCommandStatus(string $commandId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/commands/{$commandId}/status");

            if ($response->successful()) {
                $data = $response->json();

                return $data['success'] ? $data['data'] : [];
            }

            return [];

        } catch (RequestException $e) {
            Log::warning('Failed to get command status', [
                'command_id' => $commandId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get logs for a specific command
     */
    public function getCommandLogs(string $commandId, int $lines = 100): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/commands/{$commandId}/logs", [
                    'lines' => $lines,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['success'] ? $data['data']['logs'] : [];
            }

            return [];

        } catch (RequestException $e) {
            Log::warning('Failed to get command logs', [
                'command_id' => $commandId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get a specific command by ID
     */
    public function getCommand(string $commandId): ?array
    {
        $commands = $this->getCommands();

        foreach ($commands as $command) {
            if ($command['id'] === $commandId) {
                return $command;
            }
        }

        return null;
    }

    /**
     * Get only running commands
     */
    public function getRunningCommands(): array
    {
        $commands = $this->getCommands();

        return array_filter($commands, function ($command) {
            return $command['status'] === 'running';
        });
    }

    /**
     * Get only enabled commands
     */
    public function getEnabledCommands(): array
    {
        $commands = $this->getCommands();

        return array_filter($commands, function ($command) {
            return $command['enabled'] === true;
        });
    }

    /**
     * Clear cached data
     */
    public function clearCache(): void
    {
        Cache::forget('python_commands_list');
    }

    /**
     * Get API health status
     */
    public function getHealthStatus(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/health");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'available' => $data['success'] ?? false,
                    'message' => $data['message'] ?? 'Unknown status',
                    'version' => $data['version'] ?? 'Unknown',
                ];
            }

            return [
                'available' => false,
                'message' => 'API not responding',
                'version' => null,
            ];

        } catch (RequestException $e) {
            return [
                'available' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
                'version' => null,
            ];
        }
    }

    /**
     * Batch operation: start multiple commands
     */
    public function startMultipleCommands(array $commandIds): array
    {
        $results = [];

        foreach ($commandIds as $commandId) {
            $results[$commandId] = $this->startCommand($commandId);
        }

        return $results;
    }

    /**
     * Batch operation: stop multiple commands
     */
    public function stopMultipleCommands(array $commandIds): array
    {
        $results = [];

        foreach ($commandIds as $commandId) {
            $results[$commandId] = $this->stopCommand($commandId);
        }

        return $results;
    }

    /**
     * Get summary statistics
     */
    public function getSummary(): array
    {
        $commands = $this->getCommands();

        $total = count($commands);
        $running = count(array_filter($commands, fn ($cmd) => $cmd['status'] === 'running'));
        $stopped = count(array_filter($commands, fn ($cmd) => $cmd['status'] === 'stopped'));
        $enabled = count(array_filter($commands, fn ($cmd) => $cmd['enabled'] === true));

        return [
            'total' => $total,
            'running' => $running,
            'stopped' => $stopped,
            'enabled' => $enabled,
            'disabled' => $total - $enabled,
            'api_available' => $this->isAvailable(),
        ];
    }
}
