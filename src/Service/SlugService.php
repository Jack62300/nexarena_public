<?php

namespace App\Service;

class SlugService
{
    public function slugify(string $text): string
    {
        $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return (string) preg_replace('/-+/', '-', $text);
    }
}
