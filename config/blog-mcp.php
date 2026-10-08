<?php

use AbdullahDheir\BlogMcp\Tools\CreateExternalPost;
use AbdullahDheir\BlogMcp\Tools\CreatePost;
use AbdullahDheir\BlogMcp\Tools\GetPost;
use AbdullahDheir\BlogMcp\Tools\ListCategories;
use AbdullahDheir\BlogMcp\Tools\ListPosts;
use AbdullahDheir\BlogMcp\Tools\UpdatePost;

return [

    /*
    |--------------------------------------------------------------------------
    | Bearer token
    |--------------------------------------------------------------------------
    | The endpoint is protected by one static bearer token. With no token set the
    | endpoint answers 404, so it can never be left open by accident.
    | Generate one with:  php -r "echo bin2hex(random_bytes(32));"
    */
    'token' => env('BLOG_MCP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    | POST {prefix}/{path} speaks MCP over Streamable HTTP (JSON responses).
    | Set 'enabled' to false to register no route at all and wire it yourself.
    */
    'route' => [
        'enabled' => true,
        'prefix' => 'api',
        'path' => 'mcp',
        'name' => 'blog-mcp',
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server identity and instructions
    |--------------------------------------------------------------------------
    | `instructions` is shown to the model when it connects: say how your blog
    | works (languages, tone, categories, when it may publish).
    */
    'server' => [
        'name' => env('BLOG_MCP_NAME', 'blog-mcp'),
        'version' => '1.0.0',
        'instructions' => 'Manage the blog. Posts are Markdown; give each post 1-3 categories (list_categories). '
            .'New posts are drafts unless status is "published" (live immediately, whatever the date) or '
            .'"scheduled" (goes live at published_at). Never publish unless the user asked you to.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    | Classes implementing AbdullahDheir\BlogMcp\Mcp\Tool, resolved from the
    | container. Remove one to hide it, or add your own.
    | CreateExternalPost needs the `source_url` column (see `eloquent.columns`).
    */
    'tools' => [
        ListCategories::class,
        ListPosts::class,
        GetPost::class,
        CreatePost::class,
        UpdatePost::class,
        CreateExternalPost::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public URL of a post
    |--------------------------------------------------------------------------
    | Returned as `url_path` so the model can tell you where a post lives.
    | {slug} and {id} are replaced. Set to null to leave it out.
    */
    'url_path' => '/blog/{slug}',

    /*
    |--------------------------------------------------------------------------
    | Storage: your Eloquent models
    |--------------------------------------------------------------------------
    | `store` may be any class implementing AbdullahDheir\BlogMcp\Contracts\PostStore
    | (bind your own for non-Eloquent storage). The default maps your models; a
    | column set to null means "my table has no such column" and that feature is
    | switched off (for example no `slug` column: no slug argument).
    */
    'store' => AbdullahDheir\BlogMcp\Support\EloquentPostStore::class,

    'eloquent' => [
        'post_model' => 'App\\Models\\Post',
        'category_model' => 'App\\Models\\Category',   // null: posts have no categories

        'columns' => [
            'title' => null,                 // null: the title is the first "# Heading" of the body
            'body' => 'body_markdown',
            'status' => 'status',
            'published_at' => 'published_at',
            'slug' => 'slug',
            'notify_subscribers' => null,    // set it if you email subscribers on publish
            'type' => null,                  // set it to tell your own posts from external ones
            'source_url' => null,            // set it to enable create_external_post
            'source_platform' => null,
        ],

        // Values stored in the `status` and `type` columns.
        'status_values' => ['draft' => 'draft', 'scheduled' => 'scheduled', 'published' => 'published'],
        'type_values' => ['internal' => 'internal', 'external' => 'syndicated'],

        // Categories: relation on the post model, and the slug / name columns of the category model.
        'category_relation' => 'categories',
        'category_columns' => ['slug' => 'slug', 'name' => 'name'],

        // Generate a unique slug from the title when none is given. Turn off if your model does it.
        'generate_slug' => true,
    ],
];
