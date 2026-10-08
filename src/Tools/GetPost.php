<?php

namespace AbdullahDheir\BlogMcp\Tools;

use Illuminate\Support\Facades\Validator;

class GetPost extends StoreTool
{
    public function name(): string
    {
        return 'get_post';
    }

    public function description(): string
    {
        return 'Get one blog post, including its full Markdown body.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
    }

    public function handle(array $arguments): mixed
    {
        $v = Validator::make($arguments, ['id' => 'required|integer'])->validate();

        $post = $this->store->find($v['id']);

        if ($post === null) {
            throw new \InvalidArgumentException("Post {$v['id']} not found.");
        }

        return $this->withUrl($post);
    }
}
