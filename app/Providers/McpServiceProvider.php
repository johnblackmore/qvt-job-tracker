<?php

namespace App\Providers;

use App\Mcp\LocalServer\UserResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->isLocalMcpServerRunning()) {
            $user = UserResolver::resolve();

            if ($user) {
                Auth::setUser($user);
            }
        }
    }

    /**
     * Determine if the current process is the local MCP server.
     */
    private function isLocalMcpServerRunning(): bool
    {
        return isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] === 'mcp:start';
    }
}
