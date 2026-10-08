<?php

namespace AbdullahDheir\BlogMcp\Tests\Feature;

use AbdullahDheir\BlogMcp\Tests\Fixtures\Post;
use AbdullahDheir\BlogMcp\Tests\TestCase;

class PostToolsTest extends TestCase
{
    public function test_a_new_post_is_a_draft_with_a_title_slug_and_url(): void
    {
        $result = $this->tool('create_post', ['body_markdown' => "# Hello World\n\nFirst post."]);

        $this->assertFalse($result['isError']);
        $this->assertSame('draft', $result['data']['status']);
        $this->assertFalse($result['data']['live']);
        $this->assertSame('Hello World', $result['data']['title']);
        $this->assertSame('hello-world', $result['data']['slug']);
        $this->assertSame('/blog/hello-world', $result['data']['url_path']);
        $this->assertSame('First post.', $result['data']['excerpt']);
        $this->assertSame('internal', $result['data']['type']);
    }

    public function test_publishing_without_a_date_goes_live_now(): void
    {
        $data = $this->tool('create_post', ['body_markdown' => '# Live', 'status' => 'published'])['data'];

        $this->assertTrue($data['live']);
        $this->assertNotNull($data['published_at']);
    }

    public function test_scheduling_needs_a_date_and_is_live_only_when_it_has_passed(): void
    {
        $missing = $this->tool('create_post', ['body_markdown' => '# Later', 'status' => 'scheduled']);
        $this->assertTrue($missing['isError']);
        $this->assertStringContainsString('published_at', $missing['text']);

        $future = $this->tool('create_post', ['body_markdown' => '# Future', 'status' => 'scheduled', 'published_at' => now()->addDay()->toIso8601String()])['data'];
        $this->assertFalse($future['live']);

        $past = $this->tool('create_post', ['body_markdown' => '# Past', 'status' => 'scheduled', 'published_at' => now()->subHour()->toIso8601String()])['data'];
        $this->assertTrue($past['live']);
    }

    public function test_slugs_are_unique_never_numeric_and_keep_arabic(): void
    {
        $this->assertSame('same', $this->tool('create_post', ['body_markdown' => '# Same'])['data']['slug']);
        $this->assertSame('same-2', $this->tool('create_post', ['body_markdown' => '# Same'])['data']['slug']);
        $this->assertSame('post-2026', $this->tool('create_post', ['body_markdown' => '# 2026'])['data']['slug']);
        $this->assertSame('كيف-تراقب-لارافيل', $this->tool('create_post', ['body_markdown' => '# كيف تراقب لارافيل'])['data']['slug']);
    }

    public function test_the_author_can_choose_the_slug_and_a_bad_one_is_rejected(): void
    {
        $this->assertSame('laravel-news', $this->tool('create_post', ['body_markdown' => '# X', 'slug' => 'laravel-news'])['data']['slug']);
        $this->assertSame('laravel-news-2', $this->tool('create_post', ['body_markdown' => '# Y', 'slug' => 'laravel-news'])['data']['slug']);
        $this->assertTrue($this->tool('create_post', ['body_markdown' => '# Z', 'slug' => 'Bad Slug!'])['isError']);
    }

    public function test_the_slug_does_not_change_when_the_title_does(): void
    {
        $id = $this->tool('create_post', ['body_markdown' => '# First Title'])['data']['id'];

        $updated = $this->tool('update_post', ['id' => $id, 'body_markdown' => '# A Different Title'])['data'];

        $this->assertSame('A Different Title', $updated['title']);
        $this->assertSame('first-title', $updated['slug']);
    }

    public function test_quotes_in_titles_are_plain_characters(): void
    {
        $data = $this->tool('create_post', ['body_markdown' => "# \"Quoted\" & more\n\nBody"])['data'];

        $this->assertSame('"Quoted" & more', $data['title']);
        $this->assertSame('quoted-more', $data['slug']);
    }

    public function test_update_changes_only_what_is_passed(): void
    {
        $id = $this->tool('create_post', ['body_markdown' => '# Keep', 'status' => 'published', 'published_at' => '2026-01-01T10:00:00+00:00'])['data']['id'];

        $this->tool('update_post', ['id' => $id, 'body_markdown' => '# Keep'.PHP_EOL.PHP_EOL.'edited']);
        $post = $this->tool('get_post', ['id' => $id])['data'];

        $this->assertSame('published', $post['status']);
        $this->assertStringContainsString('edited', $post['body_markdown']);
        $this->assertStringStartsWith('2026-01-01T10:00:00', $post['published_at']);
    }

    public function test_publishing_a_draft_with_update_sets_the_date(): void
    {
        $id = $this->tool('create_post', ['body_markdown' => '# Draft'])['data']['id'];

        $published = $this->tool('update_post', ['id' => $id, 'status' => 'published'])['data'];

        $this->assertTrue($published['live']);
        $this->assertNotNull($published['published_at']);
    }

    public function test_categories_are_attached_replaced_and_validated(): void
    {
        $this->category('laravel');
        $this->category('ai');

        $created = $this->tool('create_post', ['body_markdown' => '# Cats', 'categories' => ['laravel', 'ai']])['data'];
        $this->assertEqualsCanonicalizing(['laravel', 'ai'], $created['categories']);

        $replaced = $this->tool('update_post', ['id' => $created['id'], 'categories' => ['ai']])['data'];
        $this->assertSame(['ai'], $replaced['categories']);

        $unchanged = $this->tool('update_post', ['id' => $created['id'], 'body_markdown' => '# Cats'])['data'];
        $this->assertSame(['ai'], $unchanged['categories']);

        $bad = $this->tool('create_post', ['body_markdown' => '# Bad', 'categories' => ['nope']]);
        $this->assertTrue($bad['isError']);
        $this->assertStringContainsString('Valid categories: ai, laravel', $bad['text']);
        $this->assertSame(1, Post::count(), 'a rejected category must not leave a half-created post behind');
    }

    public function test_list_categories(): void
    {
        $this->category('laravel', 'Laravel');

        $this->assertSame([['slug' => 'laravel', 'name' => 'Laravel']], $this->tool('list_categories')['data']);
    }

    public function test_list_posts_filters_by_status_and_hides_external_posts(): void
    {
        $this->tool('create_post', ['body_markdown' => '# A draft']);
        $this->tool('create_post', ['body_markdown' => '# Live one', 'status' => 'published']);
        $this->tool('create_external_post', ['source_url' => 'https://www.linkedin.com/posts/x-1', 'body_markdown' => 'ext', 'published_at' => '2026-03-01']);

        $this->assertCount(2, $this->tool('list_posts')['data'], 'external posts are not listed as own posts');
        $this->assertSame(['Live one'], array_column($this->tool('list_posts', ['status' => 'published'])['data'], 'title'));
        $this->assertTrue($this->tool('list_posts', ['limit' => 500])['isError']);
    }

    public function test_get_and_update_report_a_missing_post_as_a_tool_error(): void
    {
        $this->assertStringContainsString('not found', $this->tool('get_post', ['id' => 999])['text']);
        $this->assertTrue($this->tool('update_post', ['id' => 999, 'status' => 'published'])['isError']);
    }

    public function test_notify_subscribers_is_stored(): void
    {
        $this->assertFalse($this->tool('create_post', ['body_markdown' => '# Quiet', 'notify_subscribers' => false])['data']['notify_subscribers']);
        $this->assertTrue($this->tool('create_post', ['body_markdown' => '# Loud'])['data']['notify_subscribers']);
    }

    public function test_external_posts_are_added_once_published_and_never_notified(): void
    {
        $args = [
            'source_url' => 'https://ae.linkedin.com/posts/me_laravel-activity-1-abcd?utm_source=share',
            'title' => 'External headline',
            'body_markdown' => 'The text of the post',
            'published_at' => '2026-03-25T00:00:00+00:00',
        ];

        $first = $this->tool('create_external_post', $args)['data'];

        $this->assertFalse($first['already_exists']);
        $this->assertSame('external', $first['type']);
        $this->assertTrue($first['live']);
        $this->assertFalse($first['notify_subscribers']);
        $this->assertSame('https://www.linkedin.com/posts/me_laravel-activity-1-abcd', $first['source_url']);

        $post = Post::findOrFail($first['id']);
        $this->assertSame('linkedin', $post->source_platform);
        $this->assertSame('syndicated', $post->type);

        $again = $this->tool('create_external_post', $args)['data'];
        $this->assertTrue($again['already_exists']);
        $this->assertSame($first['id'], $again['id']);
        $this->assertSame(1, Post::where('source_url', '!=', null)->count());
    }

    public function test_external_posts_need_a_known_platform(): void
    {
        $result = $this->tool('create_external_post', ['source_url' => 'https://example.com/post/1', 'body_markdown' => 'x', 'published_at' => '2026-03-25']);
        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('platform', $result['text']);

        $ok = $this->tool('create_external_post', ['source_url' => 'https://example.com/post/1', 'body_markdown' => 'x', 'published_at' => '2026-03-25', 'platform' => 'medium']);
        $this->assertFalse($ok['isError']);
        $this->assertSame('medium', Post::findOrFail($ok['data']['id'])->source_platform);
    }

    public function test_the_url_path_template_can_be_changed_or_removed(): void
    {
        config(['blog-mcp.url_path' => '/articles/{id}/{slug}']);
        $data = $this->tool('create_post', ['body_markdown' => '# Tpl'])['data'];
        $this->assertSame("/articles/{$data['id']}/tpl", $data['url_path']);

        config(['blog-mcp.url_path' => null]);
        $this->assertArrayNotHasKey('url_path', $this->tool('create_post', ['body_markdown' => '# None'])['data']);
    }
}
