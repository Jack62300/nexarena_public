<?php

namespace App\Service;

class SlugService
{
    public function slugify(string $text): string
    {
        $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        $text = (string) preg_replace('/-+/', '-', $text);

        return $text !== '' ? $text : 'item';
    }

    /**
     * Returns a unique slug, appending -2, -3, etc. until the slug is not taken.
     *
     * @param callable $exists fn(string $slug): bool — returns true if the slug is already used
     */
    public function uniqueSlugify(string $text, callable $exists): string
    {
        $base = $this->slugify($text);
        $slug = $base;
        $i    = 2;

        while ($exists($slug)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
