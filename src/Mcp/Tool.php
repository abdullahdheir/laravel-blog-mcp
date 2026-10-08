<?php

namespace AbdullahDheir\BlogMcp\Mcp;

/**
 * One MCP tool. Implement this to add your own tools next to the blog ones
 * (list them in the `tools` key of config/blog-mcp.php).
 */
interface Tool
{
    /** The tool name clients call (snake_case, e.g. "create_post"). */
    public function name(): string;

    /** What the tool does, written for the model that decides when to call it. */
    public function description(): string;

    /**
     * JSON Schema of the arguments (an object schema).
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Run the tool. Return anything JSON-serialisable; it is sent back as text.
     * Throw \InvalidArgumentException (or let a ValidationException escape) to report
     * a tool error the model can read and fix; it is not an HTTP error.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): mixed;
}
