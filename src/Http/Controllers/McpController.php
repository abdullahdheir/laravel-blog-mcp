<?php

namespace AbdullahDheir\BlogMcp\Http\Controllers;

use AbdullahDheir\BlogMcp\Mcp\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** MCP over Streamable HTTP, JSON responses only (no SSE): one POST carries one message or a batch. */
class McpController
{
    public function __invoke(Request $request, Server $server): JsonResponse|Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || $payload === []) {
            return response()->json(
                ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']],
                400,
            );
        }

        // A JSON-RPC batch is a list of messages; a single message is an object.
        if (array_is_list($payload)) {
            $replies = array_values(array_filter(array_map(
                fn ($message) => is_array($message) ? $server->handle($message) : null,
                $payload,
            )));

            return $replies === [] ? response('', 202) : response()->json($replies);
        }

        $reply = $server->handle($payload);

        return $reply === null ? response('', 202) : response()->json($reply);
    }

    /** Streamable HTTP clients may probe for an SSE stream with GET: we only answer POST. */
    public function notAllowed(): Response
    {
        return response('', 405, ['Allow' => 'POST']);
    }
}
