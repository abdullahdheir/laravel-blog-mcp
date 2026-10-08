<?php

namespace AbdullahDheir\BlogMcp\Tools;

use Illuminate\Support\Facades\Validator;

class CreatePost extends StoreTool
{
    public function name(): string
    {
        return 'create_post';
    }

    public function description(): string
    {
        return 'Create a blog post. It is a draft unless you pass status "published" or "scheduled".';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->postProperties(),
            'required' => ['body_markdown'],
        ];
    }

    public function handle(array $arguments): mixed
    {
        $v = Validator::make($arguments, ['body_markdown' => 'required|string|min:1'] + $this->postRules('nullable'))->validate();

        $resolved = $this->resolveStatus($v['status'] ?? null, $v['published_at'] ?? null, array_key_exists('published_at', $v));

        $attributes = $resolved + ['body_markdown' => $v['body_markdown']];

        foreach (['slug', 'notify_subscribers'] as $optional) {
            if (isset($v[$optional])) {
                $attributes[$optional] = $v[$optional];
            }
        }

        return $this->withUrl($this->store->create($attributes, $v['categories'] ?? null));
    }
}
