<?php

namespace AbdullahDheir\BlogMcp;

use AbdullahDheir\BlogMcp\Contracts\PostStore;
use AbdullahDheir\BlogMcp\Http\Controllers\McpController;
use AbdullahDheir\BlogMcp\Http\Middleware\AuthenticateToken;
use AbdullahDheir\BlogMcp\Mcp\Server;
use AbdullahDheir\BlogMcp\Mcp\Tool;
use AbdullahDheir\BlogMcp\Support\EloquentPostStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BlogMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blog-mcp.php', 'blog-mcp');

        $this->app->singleton(PostStore::class, function (Application $app) {
            $store = config('blog-mcp.store', EloquentPostStore::class);

            return $store === EloquentPostStore::class
                ? new EloquentPostStore(config('blog-mcp.eloquent'))
                : $app->make($store);
        });

        $this->app->singleton(Server::class, function (Application $app) {
            $server = new Server(
                name: (string) config('blog-mcp.server.name'),
                version: (string) config('blog-mcp.server.version'),
                instructions: config('blog-mcp.server.instructions'),
            );

            $store = $app->make(PostStore::class);

            foreach ((array) config('blog-mcp.tools', []) as $class) {
                /** @var Tool $tool */
                $tool = $app->make($class);

                // Without the `source_url` column there is nothing to attach external posts to.
                if ($class === Tools\CreateExternalPost::class && ! $store->supports('external')) {
                    continue;
                }

                $server->register($tool);
            }

            return $server;
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/blog-mcp.php' => config_path('blog-mcp.php')], 'blog-mcp-config');

        if (! config('blog-mcp.route.enabled', true)) {
            return;
        }

        Route::middleware([...(array) config('blog-mcp.route.middleware', []), AuthenticateToken::class])
            ->prefix((string) config('blog-mcp.route.prefix', 'api'))
            ->group(function () {
                $path = (string) config('blog-mcp.route.path', 'mcp');

                Route::post($path, McpController::class)->name((string) config('blog-mcp.route.name', 'blog-mcp'));
                Route::get($path, [McpController::class, 'notAllowed']);
            });
    }
}
