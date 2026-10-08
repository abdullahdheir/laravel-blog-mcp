<?php

namespace AbdullahDheir\BlogMcp\Support;

use Illuminate\Support\Str;

/**
 * URL slugs. Unlike Str::slug() this keeps non-Latin letters (Arabic stays Arabic instead of
 * being transliterated into unreadable Latin) and returns only letters, numbers and single hyphens.
 */
class Slug
{
    public const MAX_LENGTH = 80;

    public static function make(string $text): string
    {
        $text = Str::lower($text);
        // Arabic diacritics (harakat), tatweel and the dagger alef carry no meaning in a URL.
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        $text = trim($text, '-');

        if (mb_strlen($text) > self::MAX_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_LENGTH);
            // Cut at a word boundary when there is one.
            $text = preg_replace('/-[^-]*$/u', '', $text) ?: $text;
        }

        return trim($text, '-');
    }

    /** True for text that is already a clean slug. */
    public static function isValid(string $slug): bool
    {
        return preg_match('/^[\p{L}\p{N}]+(-[\p{L}\p{N}]+)*$/u', $slug) === 1 && mb_strlen($slug) <= self::MAX_LENGTH;
    }

    /**
     * A slug that $exists() does not report as taken (a numeric suffix is added on collision).
     * A purely numeric slug is avoided: it would be mistaken for an id.
     *
     * @param  callable(string): bool  $exists
     */
    public static function unique(string $base, callable $exists): string
    {
        $base = self::make($base);

        if ($base === '' || ctype_digit($base)) {
            $base = trim('post-'.$base, '-');
        }

        $slug = $base;
        $n = 2;

        while ($exists($slug)) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
