<?php

namespace App\Service;

class SlugService
{
    public function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return preg_replace('/-+/', '-', $text);
    }
}
