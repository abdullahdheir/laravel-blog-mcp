<?php

namespace AbdullahDheir\BlogMcp\Support;

use Illuminate\Support\Str;

/** Title and excerpt of a post that has no title column: they come from its Markdown. */
class Title
{
    /** The text of a leading `# Heading`, else the first line (shortened). */
    public static function of(string $markdown): string
    {
        $body = ltrim($markdown);

        if (preg_match('/\A#[ \t]+(.+?)[ \t]*#*[ \t]*(?:\R|\z)/u', $body, $m)) {
            return self::plain($m[1]);
        }

        $firstLine = trim(strtok($body, "\r\n") ?: '');
        $firstLine = preg_replace('/^(?:#{1,6}[ \t]+|>[ \t]*|[-*+][ \t]+)+/u', '', $firstLine);

        return Str::limit(self::plain($firstLine), 80);
    }

    /** Plain-text summary of the Markdown (without the leading title). */
    public static function excerpt(string $markdown, int $limit = 280): string
    {
        $body = ltrim($markdown);
        $body = preg_replace('/\A#[ \t]+.+?(?:\R|\z)/u', '', $body) ?? $body;
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) Str::markdown($body))) ?? '');

        return Str::limit(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $limit);
    }

    private static function plain(string $markdown): string
    {
        // The Markdown renderer turns quotes into HTML entities; a title is plain text.
        return trim(html_entity_decode(strip_tags(Str::inlineMarkdown($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
