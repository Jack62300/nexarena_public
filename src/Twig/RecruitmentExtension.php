<?php

namespace App\Twig;

use App\Repository\RecruitmentListingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RecruitmentExtension extends AbstractExtension
{
    public function __construct(
        private RecruitmentListingRepository $recruitmentRepo,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_recruitments_count', [$this, 'getPendingRecruitmentsCount']),
        ];
    }

    public function getPendingRecruitmentsCount(): int
    {
        return $this->recruitmentRepo->countPending();
    }
}
