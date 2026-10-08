<?php

namespace AbdullahDheir\BlogMcp\Tools;

use AbdullahDheir\BlogMcp\Contracts\PostStore;
use AbdullahDheir\BlogMcp\Mcp\Tool;
use AbdullahDheir\BlogMcp\Support\Slug;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;

/** Shared plumbing of the blog tools: the store, the URL of a post and the argument schemas. */
abstract class StoreTool implements Tool
{
    public function __construct(
        protected readonly PostStore $store,
        protected readonly Config $config,
    ) {}

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    protected function withUrl(array $post): array
    {
        $template = $this->config->get('blog-mcp.url_path');

        if (! is_string($template) || $template === '') {
            return $post;
        }

        $key = filled($post['slug'] ?? null) ? $post['slug'] : $post['id'];

        return [...array_slice($post, 0, 2, true), 'url_path' => str_replace(['{slug}', '{id}'], [rawurlencode((string) $key), (string) $post['id']], $template), ...array_slice($post, 2, null, true)];
    }

    /**
     * Resolve status and date the way an editor expects: a published post without a date goes
     * live now, a scheduled post needs a date.
     *
     * @return array{status: string, published_at: ?CarbonImmutable}
     */
    protected function resolveStatus(?string $status, ?string $publishedAt, bool $dateGiven, ?array $existing = null): array
    {
        $status ??= $existing['status'] ?? 'draft';

        $date = $dateGiven
            ? ($publishedAt ? CarbonImmutable::parse($publishedAt) : null)
            : ($existing['published_at'] ?? null ? CarbonImmutable::parse($existing['published_at']) : null);

        if ($status === 'scheduled' && $date === null) {
            throw new \InvalidArgumentException('A scheduled post needs published_at (the go-live time).');
        }

        if ($status === 'published' && $date === null) {
            $date = CarbonImmutable::now();
        }

        return ['status' => $status, 'published_at' => $date];
    }

    // ── Schema pieces ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    protected function postProperties(): array
    {
        $properties = [
            'body_markdown' => [
                'type' => 'string',
                'description' => 'Post content in Markdown. Start with a "# Title" line: it becomes the title.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'scheduled', 'published'],
                'description' => 'Use "published" only when the user asked to publish (it is visible immediately). '
                    .'"scheduled" goes live at published_at, which it requires. Defaults to "draft".',
            ],
            'published_at' => [
                'type' => 'string',
                'description' => 'ISO 8601 date-time. Required for "scheduled" (the go-live time). For "published" it is only the date shown and used for ordering; defaults to now.',
            ],
        ];

        if ($this->store->supports('notify_subscribers')) {
            $properties['notify_subscribers'] = [
                'type' => 'boolean',
                'description' => 'Publishing emails the subscribers automatically, once. Pass false to publish without emailing them. Defaults to true.',
            ];
        }

        if ($this->store->supports('slug')) {
            $properties['slug'] = [
                'type' => 'string',
                'description' => 'URL slug: 3-6 lowercase words that carry the main search keyword, hyphen-separated (letters and digits only), e.g. "laravel-13-new-features". Optional: made from the title when omitted. Stable once set.',
            ];
        }

        if ($this->store->supports('categories')) {
            $properties['categories'] = $this->categoriesProperty();
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    protected function categoriesProperty(): array
    {
        return [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Category slugs (see list_categories), e.g. ["laravel", "ai"]. Pick 1-3 that fit. On update this replaces the existing categories.',
        ];
    }

    /** Validation rules shared by create and update; $presence is "nullable" or "sometimes". */
    protected function postRules(string $presence): array
    {
        $rules = [
            'status' => "{$presence}|in:draft,scheduled,published",
            'published_at' => "{$presence}|nullable|date",
        ];

        if ($this->store->supports('notify_subscribers')) {
            $rules['notify_subscribers'] = "{$presence}|nullable|boolean";
        }

        if ($this->store->supports('slug')) {
            $rules['slug'] = [$presence, 'nullable', 'string', 'max:'.Slug::MAX_LENGTH, 'regex:/^[\p{L}\p{N}]+(-[\p{L}\p{N}]+)*$/u'];
        }

        if ($this->store->supports('categories')) {
            $rules['categories'] = "{$presence}|nullable|array|max:5";
            $rules['categories.*'] = 'string';
        }

        return $rules;
    }
}
