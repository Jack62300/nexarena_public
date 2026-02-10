<?php

namespace App\Twig;

use App\Repository\CommentRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CommentExtension extends AbstractExtension
{
    public function __construct(
        private CommentRepository $commentRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('flagged_comments_count', [$this, 'getFlaggedCommentsCount']),
        ];
    }

    public function getFlaggedCommentsCount(): int
    {
        return $this->commentRepository->countFlagged();
    }
}
