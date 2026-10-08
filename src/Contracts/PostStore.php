<?php

namespace AbdullahDheir\BlogMcp\Contracts;

use Carbon\CarbonImmutable;

/**
 * Where the blog tools read and write posts. The default implementation
 * (Support\EloquentPostStore) maps your own models; bind your own class in
 * config('blog-mcp.store') for any other storage.
 *
 * A "post" is always returned as a plain array:
 *
 *   id, slug (nullable), title, type ("internal"|"external"), status ("draft"|"scheduled"|"published"),
 *   published_at (ISO 8601 or null), live (bool), categories (list of slugs), excerpt,
 *   emailed_to_subscribers (bool, only when notify is supported)
 *
 * and `body_markdown` is added by find().
 */
interface PostStore
{
    /** Optional features this store supports: "slug", "notify_subscribers", "categories", "external". */
    public function supports(string $feature): bool;

    /** @return array<int, array{slug: string, name: string}> */
    public function categories(): array;

    /** @return array<int, array<string, mixed>> Own posts, newest first. */
    public function list(?string $status, int $limit): array;

    /** @return array<string, mixed>|null The post with its `body_markdown`, or null. */
    public function find(int|string $id): ?array;

    /**
     * @param  array{body_markdown: string, status: string, published_at: ?CarbonImmutable, slug?: ?string, notify_subscribers?: ?bool}  $attributes
     * @param  array<int, string>|null  $categories  Category slugs; null = leave as is.
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException For an unknown category slug.
     */
    public function create(array $attributes, ?array $categories = null): array;

    /**
     * Only the keys present in $attributes change.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>|null  $categories
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException When the post does not exist or a category slug is unknown.
     */
    public function update(int|string $id, array $attributes, ?array $categories = null): array;

    /** @return array<string, mixed>|null A post (own or external) with this source URL. */
    public function findBySourceUrl(string $url): ?array;

    /**
     * Add a post that lives on another platform (published immediately, never emailed).
     *
     * @param  array{source_url: string, platform: string, body_markdown: string, published_at: CarbonImmutable}  $attributes
     * @param  array<int, string>|null  $categories
     * @return array<string, mixed>
     */
    public function createExternal(array $attributes, ?array $categories = null): array;
}
