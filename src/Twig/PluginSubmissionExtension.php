<?php

namespace App\Twig;

use App\Repository\PluginSubmissionRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PluginSubmissionExtension extends AbstractExtension
{
    public function __construct(
        private readonly PluginSubmissionRepository $repo,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_plugin_submissions_count', $this->countPending(...)),
        ];
    }

    public function countPending(): int
    {
        return $this->repo->countPending();
    }
}
