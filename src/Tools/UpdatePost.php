<?php

namespace AbdullahDheir\BlogMcp\Tools;

use Illuminate\Support\Facades\Validator;

class UpdatePost extends StoreTool
{
    public function name(): string
    {
        return 'update_post';
    }

    public function description(): string
    {
        return 'Update an existing blog post. Only the fields you pass are changed.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']] + $this->postProperties(),
            'required' => ['id'],
        ];
    }

    public function handle(array $arguments): mixed
    {
        $v = Validator::make($arguments, ['id' => 'required|integer', 'body_markdown' => 'sometimes|string|min:1'] + $this->postRules('sometimes'))->validate();

        $existing = $this->store->find($v['id']);

        if ($existing === null) {
            throw new \InvalidArgumentException("Post {$v['id']} not found.");
        }

        $attributes = [];

        if (array_key_exists('body_markdown', $v)) {
            $attributes['body_markdown'] = $v['body_markdown'];
        }

        // Status and date are resolved together (a published post needs a date), but only
        // written when the caller touched one of them.
        if (array_key_exists('status', $v) || array_key_exists('published_at', $v)) {
            $attributes += $this->resolveStatus($v['status'] ?? null, $v['published_at'] ?? null, array_key_exists('published_at', $v), $existing);
        }

        foreach (['slug', 'notify_subscribers'] as $optional) {
            if (array_key_exists($optional, $v) && $v[$optional] !== null) {
                $attributes[$optional] = $v[$optional];
            }
        }

        return $this->withUrl($this->store->update($v['id'], $attributes, $v['categories'] ?? null));
    }
}
