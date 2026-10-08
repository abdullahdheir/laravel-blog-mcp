<?php

namespace AbdullahDheir\BlogMcp\Support;

use AbdullahDheir\BlogMcp\Contracts\PostStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps the PostStore contract onto your own Eloquent models, driven by the
 * `eloquent` section of config/blog-mcp.php (model classes, column names, status values).
 *
 * Models are filled with forceFill(), so you do not need to touch $fillable.
 */
class EloquentPostStore implements PostStore
{
    /** @param  array<string, mixed>  $config  config('blog-mcp.eloquent') */
    public function __construct(private readonly array $config) {}

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'slug' => $this->column('slug') !== null,
            'notify_subscribers' => $this->column('notify_subscribers') !== null,
            'categories' => $this->categoryModel() !== null,
            'external' => $this->column('source_url') !== null && $this->column('type') !== null,
            default => false,
        };
    }

    public function categories(): array
    {
        $model = $this->categoryModel();

        if ($model === null) {
            return [];
        }

        $slugColumn = $this->config['category_columns']['slug'] ?? 'slug';
        $nameColumn = $this->config['category_columns']['name'] ?? null;

        return $model::query()->orderBy($slugColumn)->get()->map(fn (Model $c) => [
            'slug' => (string) $c->getAttribute($slugColumn),
            'name' => (string) ($nameColumn ? $c->getAttribute($nameColumn) : $c->getAttribute($slugColumn)),
        ])->values()->all();
    }

    public function list(?string $status, int $limit): array
    {
        return $this->ownPosts()
            ->when($status, fn (Builder $q, string $s) => $q->where($this->column('status'), $this->statusValue($s)))
            ->latest($this->newPost()->getCreatedAtColumn())
            ->limit($limit)
            ->get()
            ->map(fn (Model $post) => $this->present($post))
            ->all();
    }

    public function find(int|string $id): ?array
    {
        $post = $this->ownPosts()->find($id);

        return $post ? $this->present($post, withBody: true) : null;
    }

    public function create(array $attributes, ?array $categories = null): array
    {
        $categoryIds = $this->categoryIds($categories);

        $post = $this->newPost();
        $post->forceFill($this->toColumns($attributes, $post));

        if ($this->column('type') !== null) {
            $post->forceFill([$this->column('type') => $this->config['type_values']['internal'] ?? 'internal']);
        }

        $post->save();
        $this->syncCategories($post, $categoryIds);

        return $this->present($post->refresh());
    }

    public function update(int|string $id, array $attributes, ?array $categories = null): array
    {
        $post = $this->ownPosts()->find($id);

        if (! $post) {
            throw new \InvalidArgumentException("Post {$id} not found.");
        }

        $categoryIds = $this->categoryIds($categories);

        $post->forceFill($this->toColumns($attributes, $post));
        $post->save();
        $this->syncCategories($post, $categoryIds);

        return $this->present($post->refresh());
    }

    public function findBySourceUrl(string $url): ?array
    {
        $column = $this->column('source_url');

        if ($column === null) {
            return null;
        }

        $post = $this->postQuery()->where($column, $url)->first();

        return $post ? $this->present($post) : null;
    }

    public function createExternal(array $attributes, ?array $categories = null): array
    {
        if (! $this->supports('external')) {
            throw new \InvalidArgumentException('External posts are not enabled: set the `source_url` and `type` columns in config/blog-mcp.php.');
        }

        $categoryIds = $this->categoryIds($categories);

        $post = $this->newPost();
        $post->forceFill($this->toColumns([
            'body_markdown' => $attributes['body_markdown'],
            'status' => 'published',
            'published_at' => $attributes['published_at'],
            'notify_subscribers' => false,
        ], $post));
        $post->forceFill([
            $this->column('type') => $this->config['type_values']['external'] ?? 'syndicated',
            $this->column('source_url') => $attributes['source_url'],
        ]);

        if ($platformColumn = $this->column('source_platform')) {
            $post->forceFill([$platformColumn => $attributes['platform']]);
        }

        $post->save();
        $this->syncCategories($post, $categoryIds);

        return $this->present($post->refresh());
    }

    // ── Mapping ─────────────────────────────────────────────────────────

    /**
     * Tool-level attribute names -> database columns (only the keys that are present).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function toColumns(array $attributes, Model $post): array
    {
        $columns = [];

        if (array_key_exists('body_markdown', $attributes)) {
            $columns[$this->column('body')] = $attributes['body_markdown'];
        }

        if (array_key_exists('status', $attributes)) {
            $columns[$this->column('status')] = $this->statusValue($attributes['status']);
        }

        if (array_key_exists('published_at', $attributes)) {
            $columns[$this->column('published_at')] = $attributes['published_at'];
        }

        if (($column = $this->column('notify_subscribers')) && array_key_exists('notify_subscribers', $attributes) && $attributes['notify_subscribers'] !== null) {
            $columns[$column] = $attributes['notify_subscribers'];
        }

        if ($column = $this->column('slug')) {
            $slug = $this->resolveSlug($attributes, $post, $column);

            if ($slug !== null) {
                $columns[$column] = $slug;
            }
        }

        if (($column = $this->column('title')) && array_key_exists('body_markdown', $attributes)) {
            $columns[$column] = Title::of($attributes['body_markdown']);
        }

        return $columns;
    }

    /** The slug to store: the one asked for (made unique), a generated one for a new post, or null (leave as is). */
    private function resolveSlug(array $attributes, Model $post, string $column): ?string
    {
        $exists = fn (string $candidate): bool => $this->postQuery()
            ->where($column, $candidate)
            ->when($post->exists, fn (Builder $q) => $q->whereKeyNot($post->getKey()))
            ->exists();

        if (filled($attributes['slug'] ?? null)) {
            return Slug::unique($attributes['slug'], $exists);
        }

        $needsOne = ! $post->exists || blank($post->getAttribute($column));

        if ($needsOne && ($this->config['generate_slug'] ?? true)) {
            $body = $attributes['body_markdown'] ?? (string) $post->getAttribute($this->column('body'));

            return Slug::unique(Title::of($body), $exists);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function present(Model $post, bool $withBody = false): array
    {
        $body = (string) $post->getAttribute($this->column('body'));
        $status = array_search($post->getAttribute($this->column('status')), $this->statusValues(), true) ?: 'draft';
        $publishedAt = $post->getAttribute($this->column('published_at'));

        $isExternal = $this->column('type') !== null
            && $post->getAttribute($this->column('type')) === ($this->config['type_values']['external'] ?? 'syndicated');

        $slug = $this->column('slug') ? $post->getAttribute($this->column('slug')) : null;

        $data = [
            'id' => $post->getKey(),
            'slug' => $slug,
            'title' => ($titleColumn = $this->column('title')) ? (string) $post->getAttribute($titleColumn) : Title::of($body),
            'type' => $isExternal ? 'external' : 'internal',
            'status' => $status,
            'published_at' => $publishedAt?->toIso8601String(),
            'live' => $status === 'published' || ($status === 'scheduled' && $publishedAt !== null && $publishedAt->isPast()),
            'categories' => $this->categorySlugs($post),
            'excerpt' => Title::excerpt($body),
        ];

        if ($this->supports('notify_subscribers')) {
            $data['notify_subscribers'] = (bool) $post->getAttribute($this->column('notify_subscribers'));
        }

        if ($isExternal && ($column = $this->column('source_url'))) {
            $data['source_url'] = $post->getAttribute($column);
        }

        return $withBody ? $data + ['body_markdown' => $body] : $data;
    }

    // ── Categories ──────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function categorySlugs(Model $post): array
    {
        $relation = $this->config['category_relation'] ?? 'categories';

        if ($this->categoryModel() === null || ! method_exists($post, $relation)) {
            return [];
        }

        $slugColumn = $this->config['category_columns']['slug'] ?? 'slug';

        return $post->loadMissing($relation)->getRelation($relation)->pluck($slugColumn)->values()->all();
    }

    /**
     * Resolve category slugs to ids (null = leave categories alone). Throws on an unknown slug.
     *
     * @param  array<int, string>|null  $slugs
     * @return array<int, int|string>|null
     */
    private function categoryIds(?array $slugs): ?array
    {
        if ($slugs === null) {
            return null;
        }

        $model = $this->categoryModel();

        if ($model === null) {
            throw new \InvalidArgumentException('This blog has no categories.');
        }

        $slugColumn = $this->config['category_columns']['slug'] ?? 'slug';
        $keyName = (new $model)->getKeyName();
        $found = $model::query()->whereIn($slugColumn, $slugs)->pluck($keyName, $slugColumn);

        $unknown = array_values(array_diff($slugs, $found->keys()->all()));

        if ($unknown !== []) {
            $valid = $model::query()->orderBy($slugColumn)->pluck($slugColumn)->implode(', ');

            throw new \InvalidArgumentException('Unknown category: '.implode(', ', $unknown).". Valid categories: {$valid}");
        }

        return $found->values()->all();
    }

    /** @param  array<int, int|string>|null  $ids */
    private function syncCategories(Model $post, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }

        $post->{$this->config['category_relation'] ?? 'categories'}()->sync($ids);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function column(string $name): ?string
    {
        return $this->config['columns'][$name] ?? null;
    }

    /** @return array<string, string> */
    private function statusValues(): array
    {
        return $this->config['status_values'] ?? ['draft' => 'draft', 'scheduled' => 'scheduled', 'published' => 'published'];
    }

    private function statusValue(string $status): string
    {
        return $this->statusValues()[$status] ?? $status;
    }

    /** @return class-string<Model> */
    private function postModel(): string
    {
        return $this->config['post_model'];
    }

    /** @return class-string<Model>|null */
    private function categoryModel(): ?string
    {
        $model = $this->config['category_model'] ?? null;

        return $model && class_exists($model) ? $model : null;
    }

    private function newPost(): Model
    {
        $class = $this->postModel();

        return new $class;
    }

    /** All posts, own and external. */
    private function postQuery(): Builder
    {
        return $this->postModel()::query();
    }

    /** Only the posts written here (external ones excluded when a `type` column exists). */
    private function ownPosts(): Builder
    {
        $query = $this->postQuery();

        if ($typeColumn = $this->column('type')) {
            $query->where($typeColumn, $this->config['type_values']['internal'] ?? 'internal');
        }

        return $query;
    }
}
