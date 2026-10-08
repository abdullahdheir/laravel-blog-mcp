<?php

namespace AbdullahDheir\BlogMcp\Tests;

use AbdullahDheir\BlogMcp\BlogMcpServiceProvider;
use AbdullahDheir\BlogMcp\Tests\Fixtures\Category;
use AbdullahDheir\BlogMcp\Tests\Fixtures\Post;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BlogMcpServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('blog-mcp.token', 'secret-token');
        $app['config']->set('blog-mcp.eloquent.post_model', Post::class);
        $app['config']->set('blog-mcp.eloquent.category_model', Category::class);
        $app['config']->set('blog-mcp.eloquent.columns', [
            'title' => null,
            'body' => 'body_markdown',
            'status' => 'status',
            'published_at' => 'published_at',
            'slug' => 'slug',
            'notify_subscribers' => 'notify_subscribers',
            'type' => 'type',
            'source_url' => 'source_url',
            'source_platform' => 'source_platform',
        ]);
    }

    /** Send one JSON-RPC message to the endpoint with the right token. */
    protected function rpc(array $message, ?string $token = 'secret-token')
    {
        return $this->postJson('/api/mcp', $message, $token ? ['Authorization' => "Bearer {$token}"] : []);
    }

    /** Call a tool and return [isError, text, data]. */
    protected function tool(string $name, array $arguments = []): array
    {
        $result = $this->rpc([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ])->assertOk()->json('result');

        $text = $result['content'][0]['text'];

        return [
            'isError' => $result['isError'],
            'text' => $text,
            'data' => $result['isError'] ? null : json_decode($text, true),
        ];
    }

    protected function category(string $slug, string $name = null): Category
    {
        return Category::create(['slug' => $slug, 'name' => $name ?? ucfirst($slug)]);
    }
}
