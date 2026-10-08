<?php

namespace AbdullahDheir\BlogMcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

/**
 * Lists a post that lives on LinkedIn, X or another platform on the blog as an external link.
 * Idempotent: the same source URL returns the existing post.
 */
class CreateExternalPost extends StoreTool
{
    public function name(): string
    {
        return 'create_external_post';
    }

    public function description(): string
    {
        return 'Add a post published on LinkedIn or X (or another platform) to the blog as an external link, published immediately and never emailed to subscribers. Idempotent: the same source_url returns the existing post with already_exists=true.';
    }

    public function inputSchema(): array
    {
        $properties = [
            'source_url' => ['type' => 'string', 'description' => 'URL of the post on the other platform.'],
            'body_markdown' => ['type' => 'string', 'description' => 'The text of the post (Markdown / plain text).'],
            'title' => ['type' => 'string', 'description' => 'Optional short headline shown as the title; when omitted the first line of the text is used.'],
            'published_at' => ['type' => 'string', 'description' => 'ISO 8601 date-time the post was published on the platform.'],
            'platform' => ['type' => 'string', 'description' => 'Platform key, e.g. "linkedin" or "x". Detected from the URL for LinkedIn and X; required for any other site.'],
        ];

        if ($this->store->supports('categories')) {
            $properties['categories'] = $this->categoriesProperty();
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => ['source_url', 'body_markdown', 'published_at']];
    }

    public function handle(array $arguments): mixed
    {
        if (! $this->store->supports('external')) {
            throw new \InvalidArgumentException('External posts are not enabled on this blog.');
        }

        $rules = [
            'source_url' => 'required|url|max:1000',
            'body_markdown' => 'required|string|min:1',
            'title' => 'nullable|string|max:200',
            'published_at' => 'required|date',
            'platform' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
        ];

        if ($this->store->supports('categories')) {
            $rules['categories'] = 'nullable|array|max:5';
            $rules['categories.*'] = 'string';
        }

        $v = Validator::make($arguments, $rules)->validate();

        $url = $this->canonicalUrl($v['source_url']);
        $platform = $v['platform'] ?? $this->detectPlatform($url);

        if ($platform === null) {
            throw new \InvalidArgumentException('Could not tell the platform from the URL: pass `platform` (e.g. "linkedin", "x").');
        }

        if ($existing = $this->store->findBySourceUrl($url)) {
            return $this->withUrl($existing) + ['already_exists' => true];
        }

        $body = trim($v['body_markdown']);

        if (filled($v['title'] ?? null)) {
            $body = '# '.trim($v['title'])."\n\n".$body;
        }

        $post = $this->store->createExternal([
            'source_url' => $url,
            'platform' => $platform,
            'body_markdown' => $body,
            'published_at' => CarbonImmutable::parse($v['published_at']),
        ], $v['categories'] ?? null);

        return $this->withUrl($post) + ['already_exists' => false];
    }

    /** Drop the query string and fragment; use the main LinkedIn host (ae.linkedin.com -> www). */
    private function canonicalUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com')) {
            $host = 'www.linkedin.com';
        }

        return ($parts['scheme'] ?? 'https').'://'.$host.($parts['path'] ?? '');
    }

    private function detectPlatform(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            $host === 'linkedin.com', str_ends_with($host, '.linkedin.com') => 'linkedin',
            in_array($host, ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com', 'mobile.twitter.com'], true) => 'x',
            default => null,
        };
    }
}
