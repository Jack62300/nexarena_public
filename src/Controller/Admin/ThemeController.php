<?php

namespace App\Controller\Admin;

use App\Service\ThemeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/themes', name: 'admin_themes_')]
#[IsGranted('themes.manage')]
class ThemeController extends AbstractController
{
    private const IMAGE_TYPES = ['bg', 'decor-left', 'decor-right'];
    private const IMAGE_LABELS = [
        'bg' => 'Image de fond',
        'decor-left' => 'Element decoratif gauche',
        'decor-right' => 'Element decoratif droite',
    ];

    public function __construct(
        private ThemeService $themeService,
        private string $projectDir,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $themes = $this->themeService->getAllThemes();
        $themeImages = [];

        foreach ($themes as $key => $theme) {
            $themeImages[$key] = [];
            foreach (self::IMAGE_TYPES as $type) {
                $themeImages[$key][$type] = $this->findExistingImage($key, $type);
            }
        }

        return $this->render('admin/themes/index.html.twig', [
            'themes' => $themes,
            'theme_images' => $themeImages,
            'image_labels' => self::IMAGE_LABELS,
        ]);
    }

    #[Route('/{key}/upload', name: 'upload', methods: ['POST'])]
    public function upload(string $key, Request $request): Response
    {
        if (!$this->themeService->isValidTheme($key)) {
            $this->addFlash('error', 'Theme invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        if (!$this->isCsrfTokenValid('theme_upload_' . $key, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        $type = $request->request->get('image_type');
        if (!in_array($type, self::IMAGE_TYPES, true)) {
            $this->addFlash('error', 'Type d\'image invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('image');
        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Fichier invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        $dir = $this->getThemeDir($key);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Delete existing file for this type
        $this->deleteExistingImage($key, $type);

        // Save new file with fixed name
        $ext = $file->guessExtension() ?: 'png';
        $filename = $type . '.' . $ext;
        $file->move($dir, $filename);

        $theme = $this->themeService->getTheme($key);
        $this->addFlash('success', self::IMAGE_LABELS[$type] . ' du theme "' . $theme['label'] . '" mise a jour.');

        return $this->redirectToRoute('admin_themes_index', ['_fragment' => 'theme-' . $key]);
    }

    #[Route('/{key}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $key, Request $request): Response
    {
        if (!$this->themeService->isValidTheme($key)) {
            $this->addFlash('error', 'Theme invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        if (!$this->isCsrfTokenValid('theme_delete_' . $key, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        $type = $request->request->get('image_type');
        if (!in_array($type, self::IMAGE_TYPES, true)) {
            $this->addFlash('error', 'Type d\'image invalide.');
            return $this->redirectToRoute('admin_themes_index');
        }

        $this->deleteExistingImage($key, $type);

        $theme = $this->themeService->getTheme($key);
        $this->addFlash('success', self::IMAGE_LABELS[$type] . ' du theme "' . $theme['label'] . '" supprimee.');

        return $this->redirectToRoute('admin_themes_index', ['_fragment' => 'theme-' . $key]);
    }

    private function getThemeDir(string $key): string
    {
        return $this->projectDir . '/public/uploads/themes/' . $key;
    }

    private function findExistingImage(string $key, string $type): ?string
    {
        $dir = $this->getThemeDir($key);
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $path = $dir . '/' . $type . '.' . $ext;
            if (file_exists($path)) {
                return 'uploads/themes/' . $key . '/' . $type . '.' . $ext;
            }
        }

        return null;
    }

    private function deleteExistingImage(string $key, string $type): bool
    {
        $dir = $this->getThemeDir($key);
        $deleted = false;
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
            $path = $dir . '/' . $type . '.' . $ext;
            if (file_exists($path)) {
                if (is_writable($path)) {
                    unlink($path);
                    $deleted = true;
                } else {
                    // Try chmod before deleting (in case of permission mismatch)
                    @chmod($path, 0664);
                    if (@unlink($path)) {
                        $deleted = true;
                    }
                }
            }
        }
        return $deleted;
    }
}
