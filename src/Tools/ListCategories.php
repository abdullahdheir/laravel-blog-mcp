<?php

namespace AbdullahDheir\BlogMcp\Tools;

class ListCategories extends StoreTool
{
    public function name(): string
    {
        return 'list_categories';
    }

    public function description(): string
    {
        return 'List the blog categories (slug and name). Use these slugs in create_post / update_post.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function handle(array $arguments): mixed
    {
        return $this->store->categories();
    }
}
