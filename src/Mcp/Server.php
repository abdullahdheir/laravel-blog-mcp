<?php

namespace AbdullahDheir\BlogMcp\Mcp;

use Illuminate\Validation\ValidationException;

/**
 * A minimal Model Context Protocol server (stateless JSON-RPC 2.0).
 *
 * Only what tools need is implemented: initialize, ping, tools/list and tools/call.
 * Transport (HTTP, auth) lives in the controller; this class just maps one JSON-RPC
 * message to its reply.
 */
class Server
{
    /** Protocol versions this server can speak, newest first. */
    public const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(
        private readonly string $name = 'blog-mcp',
        private readonly string $version = '1.0.0',
        private readonly ?string $instructions = null,
    ) {}

    public function register(Tool $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /** @return array<int, string> */
    public function toolNames(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Handle one JSON-RPC message. Returns null for notifications (no reply).
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        if (($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($method)) {
            return $this->error($id, -32600, 'Invalid Request');
        }

        // Notifications carry no id and never get a response.
        if (! array_key_exists('id', $message)) {
            return null;
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($method) {
            'initialize' => $this->result($id, $this->initialize($params)),
            'ping' => $this->result($id, new \stdClass),
            'tools/list' => $this->result($id, ['tools' => $this->describeTools()]),
            'tools/call' => $this->result($id, $this->callTool($params)),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    /** @return array<string, mixed> */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;

        $result = [
            'protocolVersion' => in_array($requested, self::SUPPORTED_VERSIONS, true)
                ? $requested
                : self::SUPPORTED_VERSIONS[0],
            'capabilities' => ['tools' => new \stdClass],
            'serverInfo' => ['name' => $this->name, 'version' => $this->version],
        ];

        if (filled($this->instructions)) {
            $result['instructions'] = $this->instructions;
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function describeTools(): array
    {
        return array_values(array_map(fn (Tool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
        ], $this->tools));
    }

    /** @return array<string, mixed> */
    private function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (! is_string($name) || ! isset($this->tools[$name])) {
            return $this->toolText('Unknown tool: '.(is_string($name) ? $name : '(none)'), true);
        }

        try {
            $data = $this->tools[$name]->handle($arguments);
        } catch (ValidationException $e) {
            return $this->toolText(collect($e->errors())->flatten()->implode("\n"), true);
        } catch (\InvalidArgumentException $e) {
            return $this->toolText($e->getMessage(), true);
        }

        return $this->toolText(
            is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        );
    }

    /** @return array<string, mixed> */
    private function toolText(string $text, bool $isError = false): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError];
    }

    /** @return array<string, mixed> */
    private function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /** @return array<string, mixed> */
    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
