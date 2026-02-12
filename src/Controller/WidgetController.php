<?php

namespace App\Controller;

use App\Repository\ServerRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WidgetController extends AbstractController
{
    public function __construct(
        private ServerRepository $serverRepository,
        private VoteRepository $voteRepository,
    ) {}

    #[Route('/widget/{slug}/card', name: 'widget_card')]
    public function card(string $slug, Request $request): Response
    {
        $server = $this->serverRepository->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            throw $this->createNotFoundException();
        }

        $params = $this->extractWidgetParams($request);

        $response = $this->render('widget/card.html.twig', [
            'server' => $server,
            'params' => $params,
        ]);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

        return $response;
    }

    #[Route('/widget/{slug}/voters', name: 'widget_voters')]
    public function voters(string $slug, Request $request): Response
    {
        $server = $this->serverRepository->findOneBy(['slug' => $slug, 'isActive' => true, 'isApproved' => true]);
        if (!$server) {
            throw $this->createNotFoundException();
        }

        $params = $this->extractWidgetParams($request);
        $voters = $this->voteRepository->getTopVotersByServer($server, 10);

        $response = $this->render('widget/voters.html.twig', [
            'server' => $server,
            'params' => $params,
            'voters' => $voters,
        ]);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

        return $response;
    }

    private function extractWidgetParams(Request $request): array
    {
        $mode = $request->query->get('mode', 'dark');
        if (!in_array($mode, ['dark', 'light'], true)) {
            $mode = 'dark';
        }

        // Defaults per mode
        $defaults = $mode === 'light' ? [
            'accent' => '45f882',
            'bg' => 'ffffff',
            'text' => '1a1a2e',
            'textSec' => '6b7280',
            'border' => 'd1d5db',
        ] : [
            'accent' => '45f882',
            'bg' => 'transparent',
            'text' => 'ffffff',
            'textSec' => '8b949e',
            'border' => '2d3748',
        ];

        // Extract and validate hex colors
        $accent = $this->validateHex($request->query->get('accent'), $defaults['accent']);
        $bg = $request->query->get('bg', $defaults['bg']);
        if ($bg !== 'transparent') {
            $bg = $this->validateHex($bg, $defaults['bg']);
        }
        $text = $this->validateHex($request->query->get('text'), $defaults['text']);
        $textSec = $this->validateHex($request->query->get('textSec'), $defaults['textSec']);
        $border = $this->validateHex($request->query->get('border'), $defaults['border']);

        // Radius: clamp 0-30
        $radius = (int) $request->query->get('radius', '12');
        $radius = max(0, min(30, $radius));

        // Boolean flags
        $hideFooter = (bool) $request->query->get('hideFooter', '0');
        $hideDesc = (bool) $request->query->get('hideDesc', '0');

        // Compute accent RGB for rgba() usage
        $accentRgb = $this->hexToRgb($accent);

        return [
            'mode' => $mode,
            'accent' => '#' . $accent,
            'accentRgb' => $accentRgb,
            'bg' => $bg === 'transparent' ? 'transparent' : '#' . $bg,
            'text' => '#' . $text,
            'textSec' => '#' . $textSec,
            'border' => '#' . $border,
            'borderRgb' => $this->hexToRgb($border),
            'radius' => $radius,
            'hideFooter' => $hideFooter,
            'hideDesc' => $hideDesc,
        ];
    }

    private function validateHex(?string $value, string $default): string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        // Strip leading # if provided
        $value = ltrim($value, '#');
        if (preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
            // Expand 3-char hex to 6-char
            if (strlen($value) === 3) {
                $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
            }
            return strtolower($value);
        }
        return $default;
    }

    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return implode(',', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }
}
