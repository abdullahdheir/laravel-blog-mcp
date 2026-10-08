<?php

namespace AbdullahDheir\BlogMcp\Tools;

use Illuminate\Support\Facades\Validator;

class ListPosts extends StoreTool
{
    public function name(): string
    {
        return 'list_posts';
    }

    public function description(): string
    {
        return 'List recent blog posts (newest first) with id, slug, status, date and a short excerpt.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['draft', 'scheduled', 'published']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Default 10.'],
            ],
        ];
    }

    public function handle(array $arguments): mixed
    {
        $v = Validator::make($arguments, [
            'status' => 'nullable|in:draft,scheduled,published',
            'limit' => 'nullable|integer|min:1|max:50',
        ])->validate();

        return array_map(
            fn (array $post) => $this->withUrl($post),
            $this->store->list($v['status'] ?? null, $v['limit'] ?? 10),
        );
    }
}
