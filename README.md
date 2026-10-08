# Laravel Blog MCP

A small, dependency-light **MCP (Model Context Protocol) server for Laravel** with ready-made **blog tools**.
Connect Claude (or any MCP client) to your app and let it list, draft, edit, schedule and publish your posts,
or add posts you wrote on LinkedIn/X to your blog as external links.

- **Stateless Streamable HTTP**: one `POST` per message (or batch), JSON responses, no sessions, no SSE.
- **Protected by a bearer token**; with no token configured the endpoint simply does not exist (404).
- **Works with your own models**: map your `Post` / `Category` columns in a config file. Features whose column you do not have switch themselves off.
- **Unicode-friendly slugs** (Arabic stays Arabic), unique and never purely numeric.
- **Extensible**: add your own tools by implementing one small interface, or swap the whole storage layer.

> It does not depend on `laravel/mcp`; it implements just the pieces tools need (`initialize`, `ping`, `tools/list`, `tools/call`).
> If you need resources, prompts, OAuth or streaming, use the official package instead.

## Install

```bash
composer require abdullahdheir/laravel-blog-mcp
php artisan vendor:publish --tag=blog-mcp-config
```

Add a token to `.env` (the endpoint is disabled without one):

```bash
php -r "echo bin2hex(random_bytes(32));"   # copy the output
```

```dotenv
BLOG_MCP_TOKEN=paste-the-token-here
```

## Map your models

Edit `config/blog-mcp.php`. The minimum is your two model classes and the column names that differ from the defaults:

```php
'eloquent' => [
    'post_model' => App\Models\Post::class,
    'category_model' => App\Models\Category::class,   // null: no categories

    'columns' => [
        'title' => null,                 // null: the title is the first "# Heading" of the body
        'body' => 'body_markdown',
        'status' => 'status',
        'published_at' => 'published_at',
        'slug' => 'slug',                // null: no slug argument
        'notify_subscribers' => null,    // set it if you email subscribers when a post goes live
        'type' => null,                  // set both `type` and `source_url` to enable external posts
        'source_url' => null,
        'source_platform' => null,
    ],

    'status_values' => ['draft' => 'draft', 'scheduled' => 'scheduled', 'published' => 'published'],
    'type_values' => ['internal' => 'internal', 'external' => 'syndicated'],
    'category_relation' => 'categories',
    'category_columns' => ['slug' => 'slug', 'name' => 'name'],
],
```

Your table needs, at least: a Markdown body, a status, and a nullable `published_at`.
Models are filled with `forceFill()`, so you do not need to edit `$fillable`.

## Connect Claude

```bash
claude mcp add --transport http blog https://your-site.com/api/mcp \
  --header "Authorization: Bearer YOUR_TOKEN"
```

The endpoint is `POST {prefix}/{path}` (`api/mcp` by default; change `route.prefix` / `route.path`, or set
`route.enabled` to `false` and register the route yourself with `AbdullahDheir\BlogMcp\Http\Controllers\McpController`).

> Never paste the token into a chat or commit it. If you do, rotate it: change `BLOG_MCP_TOKEN` and reconnect.

## Tools

| Tool | What it does |
|---|---|
| `list_categories` | The category slugs and names to use in the other tools. |
| `list_posts` | Recent own posts (newest first), optionally filtered by `status`; external posts are not listed. |
| `get_post` | One post with its full Markdown body. |
| `create_post` | A new post. **Draft** unless you pass `status: "published"` (live now) or `"scheduled"` (needs `published_at`). |
| `update_post` | Changes only the fields you pass. Categories passed here **replace** the old ones. |
| `create_external_post` | Adds a LinkedIn / X (or other) post to the blog as an external link, published immediately. Idempotent by `source_url`. |

Every post comes back as plain data: `id`, `slug`, `url_path`, `title`, `type`, `status`, `published_at`, `live`,
`categories`, `excerpt` (and `body_markdown` from `get_post`).

Behaviour worth knowing:

- A **published post without a date goes live now**; a **scheduled post needs a date** and is `live` only once it has passed.
- The **slug** is made once from the title and **does not change when the title does**, so links keep working. Pass `slug` to choose it.
- Titles are plain text: quotes and ampersands are never HTML entities.
- A rejected category leaves nothing half-created, and the error lists the valid slugs so the model can fix its call.
- `url_path` comes from the `url_path` template (`/blog/{slug}` by default; `{id}` works too; `null` removes it).

## Tell the model how your blog works

`server.instructions` in the config is shown to the model when it connects. Use it for language, tone, categories
and, above all, **when it may publish**:

```php
'server' => [
    'instructions' => 'Write in Arabic. Give each post 1-3 categories. Always create drafts; only publish when the user says so.',
],
```

## Add your own tool

```php
use AbdullahDheir\BlogMcp\Mcp\Tool;

class CountSubscribers implements Tool
{
    public function name(): string { return 'count_subscribers'; }
    public function description(): string { return 'How many confirmed newsletter subscribers there are.'; }
    public function inputSchema(): array { return ['type' => 'object', 'properties' => new stdClass]; }

    public function handle(array $arguments): mixed
    {
        return ['count' => Subscriber::confirmed()->count()];
    }
}
```

Then list it in `config('blog-mcp.tools')`. Tools are resolved from the container, so constructor injection works.
Throw `\InvalidArgumentException` (or let a validation exception escape) to return an error the model can read and fix.

## Use other storage

Implement `AbdullahDheir\BlogMcp\Contracts\PostStore` (markdown files, a headless CMS, an API...) and set
`'store' => YourStore::class` in the config. The contract documents the array shape of a post.

## Security

- One static bearer token, compared in constant time. Treat it like a password; use HTTPS.
- Publishing is a public action: say in `server.instructions` when the model may do it, and keep new posts as drafts by default.
- Everything fetched from the web by the model is untrusted: do not let it write posts blindly into a site that emails subscribers.
- `create_external_post` only stores text and a URL; it never fetches the URL.

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT. Built by [Abdullah Dheir](https://abdullahdheir.dev).
