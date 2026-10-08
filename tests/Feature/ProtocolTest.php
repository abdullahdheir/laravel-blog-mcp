<?php

namespace AbdullahDheir\BlogMcp\Tests\Feature;

use AbdullahDheir\BlogMcp\Tests\TestCase;

class ProtocolTest extends TestCase
{
    public function test_it_does_not_exist_without_a_token(): void
    {
        config(['blog-mcp.token' => null]);

        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertNotFound();
    }

    public function test_it_rejects_a_missing_or_wrong_token(): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], null)->assertUnauthorized();
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], 'nope')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_get_is_not_allowed(): void
    {
        $this->get('/api/mcp', ['Authorization' => 'Bearer secret-token'])->assertStatus(405)->assertHeader('Allow', 'POST');
    }

    public function test_initialize_negotiates_the_protocol_version(): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26']])
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-03-26')
            ->assertJsonStructure(['result' => ['capabilities' => ['tools'], 'serverInfo' => ['name', 'version'], 'instructions']]);

        $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']])
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_it_lists_the_tools_with_their_schemas(): void
    {
        $tools = collect($this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'));

        $this->assertSame(
            ['list_categories', 'list_posts', 'get_post', 'create_post', 'update_post', 'create_external_post'],
            $tools->pluck('name')->all(),
        );

        $create = $tools->firstWhere('name', 'create_post');
        $this->assertSame(['body_markdown'], $create['inputSchema']['required']);
        $this->assertArrayHasKey('slug', $create['inputSchema']['properties']);
        $this->assertArrayHasKey('notify_subscribers', $create['inputSchema']['properties']);
        $this->assertArrayHasKey('categories', $create['inputSchema']['properties']);
    }

    public function test_the_schema_only_offers_what_the_models_support(): void
    {
        config([
            'blog-mcp.eloquent.columns.slug' => null,
            'blog-mcp.eloquent.columns.notify_subscribers' => null,
            'blog-mcp.eloquent.columns.source_url' => null,
            'blog-mcp.eloquent.category_model' => null,
        ]);

        $tools = collect($this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'));

        $this->assertNotContains('create_external_post', $tools->pluck('name')->all());

        $properties = array_keys($tools->firstWhere('name', 'create_post')['inputSchema']['properties']);
        $this->assertSame(['body_markdown', 'status', 'published_at'], $properties);
    }

    public function test_notifications_get_no_reply(): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);
    }

    public function test_invalid_requests_and_unknown_methods(): void
    {
        $this->rpc(['id' => 1, 'method' => 'ping'])->assertJsonPath('error.code', -32600);
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'nope'])->assertJsonPath('error.code', -32601);
        $this->call('POST', '/api/mcp', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer secret-token', 'CONTENT_TYPE' => 'application/json'], '{not json')
            ->assertStatus(400)
            ->assertJsonPath('error.code', -32700);
    }

    public function test_batches_are_answered_in_one_response(): void
    {
        $replies = $this->rpc([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ])->assertOk()->json();

        $this->assertCount(2, $replies);
        $this->assertSame([1, 2], array_column($replies, 'id'));
    }

    public function test_unknown_tools_are_a_tool_error_not_a_crash(): void
    {
        $result = $this->tool('nope');

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Unknown tool', $result['text']);
    }
}
