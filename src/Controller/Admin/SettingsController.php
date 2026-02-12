<?php

namespace App\Controller\Admin;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings', name: 'admin_settings_')]
#[IsGranted('ROLE_RESPONSABLE')]
class SettingsController extends AbstractController
{
    private const CATEGORY_LABELS = [
        'general' => 'General',
        'banner' => 'Banniere & Accueil',
        'seo' => 'SEO & Referencement',
        'social' => 'Reseaux sociaux',
        'footer' => 'Footer',
        'registration' => 'Inscription',
        'articles' => 'Articles',
        'api' => 'API',
        'api_keys' => 'Cles API',
        'votes' => 'Votes',
        'servers' => 'Serveurs',
        'webhooks' => 'Webhooks',
        'plugins' => 'Plugins',
        'securite' => 'Securite',
        'paiement' => 'Paiement',
        'premium' => 'Premium',
        'discord' => 'Discord',
        'legal' => 'Pages legales',
    ];

    private const CATEGORY_ICONS = [
        'general' => 'fas fa-cog',
        'banner' => 'fas fa-home',
        'seo' => 'fas fa-search',
        'social' => 'fas fa-share-alt',
        'footer' => 'fas fa-columns',
        'registration' => 'fas fa-user-plus',
        'articles' => 'fas fa-newspaper',
        'api' => 'fas fa-code',
        'api_keys' => 'fas fa-key',
        'votes' => 'fas fa-vote-yea',
        'servers' => 'fas fa-server',
        'webhooks' => 'fas fa-plug',
        'plugins' => 'fas fa-puzzle-piece',
        'securite' => 'fas fa-shield-alt',
        'paiement' => 'fab fa-paypal',
        'premium' => 'fas fa-crown',
        'discord' => 'fab fa-discord',
        'legal' => 'fas fa-gavel',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SettingRepository $settingRepo,
        private EncryptionService $encryptionService,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $activeTab = $request->query->get('tab', 'general');

        $bannerSlides = [];
        if ($activeTab === 'banner') {
            $setting = $this->settingRepo->findOneBy(['key' => 'banner_slides']);
            if ($setting) {
                $bannerSlides = json_decode($setting->getValue() ?: '[]', true) ?: [];
            }
        }

        return $this->render('admin/settings/index.html.twig', [
            'settings_by_category' => $this->settingRepo->findAllGroupedByCategory(),
            'category_labels' => self::CATEGORY_LABELS,
            'category_icons' => self::CATEGORY_ICONS,
            'active_tab' => $activeTab,
            'banner_slides' => $bannerSlides,
        ]);
    }

    #[Route('/banner-slides/upload', name: 'banner_slide_upload', methods: ['POST'])]
    #[IsGranted('settings.edit')]
    public function bannerSlideUpload(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('banner_slide', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
        }

        $file = $request->files->get('slide_image');
        if (!$file || !$file->isValid()) {
            $this->addFlash('error', 'Fichier invalide.');
            return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            $this->addFlash('error', 'Format d\'image non supporte (JPG, PNG, WebP, GIF).');
            return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
        }

        $filename = uniqid() . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/banners';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $file->move($uploadDir, $filename);

        $setting = $this->getOrCreateBannerSlidesSetting();
        $slides = json_decode($setting->getValue() ?: '[]', true) ?: [];
        $slides[] = $filename;
        $setting->setValue(json_encode($slides));
        $this->em->flush();

        $this->addFlash('success', 'Slide ajoutee.');
        return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
    }

    #[Route('/banner-slides/delete', name: 'banner_slide_delete', methods: ['POST'])]
    #[IsGranted('settings.edit')]
    public function bannerSlideDelete(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('banner_slide_delete', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
        }

        $filename = $request->request->get('filename');
        if (!$filename) {
            return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/banners/' . basename($filename);
        if (file_exists($path)) {
            unlink($path);
        }

        $setting = $this->getOrCreateBannerSlidesSetting();
        $slides = json_decode($setting->getValue() ?: '[]', true) ?: [];
        $slides = array_values(array_filter($slides, fn($s) => $s !== $filename));
        $setting->setValue(json_encode($slides));
        $this->em->flush();

        $this->addFlash('success', 'Slide supprimee.');
        return $this->redirectToRoute('admin_settings_index', ['tab' => 'banner']);
    }

    private function getOrCreateBannerSlidesSetting(): Setting
    {
        $setting = $this->settingRepo->findOneBy(['key' => 'banner_slides']);
        if (!$setting) {
            $setting = new Setting();
            $setting->setKey('banner_slides');
            $setting->setValue('[]');
            $setting->setType(Setting::TYPE_TEXT);
            $setting->setLabel('Slides de la banniere');
            $setting->setCategory('banner');
            $setting->setPosition(3);
            $this->em->persist($setting);
        }
        return $setting;
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    #[IsGranted('settings.edit')]
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('settings_save', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_settings_index');
        }

        $category = $request->request->get('_category', 'general');
        $settings = $this->settingRepo->findBy(['category' => $category], ['position' => 'ASC']);

        foreach ($settings as $setting) {
            $key = $setting->getKey();

            // Skip banner_slides — managed by dedicated routes
            if ($key === 'banner_slides') {
                continue;
            }

            if ($setting->getType() === Setting::TYPE_SECRET) {
                $value = $request->request->get('setting_' . $key);
                // Don't save masked placeholder
                if ($value !== null && $value !== '' && $value !== '********') {
                    $setting->setValue($this->encryptionService->encrypt($value));
                }
                continue;
            }

            if ($setting->getType() === Setting::TYPE_IMAGE) {
                /** @var UploadedFile|null $file */
                $file = $request->files->get('setting_' . $key);
                if ($file) {
                    $filename = 'settings/' . $key . '.' . $file->guessExtension();
                    $file->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/settings',
                        $key . '.' . $file->guessExtension(),
                    );
                    $setting->setValue('uploads/' . $filename);
                }
                // If "remove image" checkbox is checked
                if ($request->request->get('remove_' . $key)) {
                    if ($setting->getValue() && str_starts_with($setting->getValue(), 'uploads/')) {
                        $path = $this->getParameter('kernel.project_dir') . '/public/' . $setting->getValue();
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }
                    $setting->setValue('');
                }
            } elseif ($setting->getType() === Setting::TYPE_BOOLEAN) {
                $setting->setValue($request->request->has('setting_' . $key) ? '1' : '0');
            } else {
                $value = $request->request->get('setting_' . $key);
                if ($value !== null) {
                    $setting->setValue($value);
                }
            }
        }

        $this->em->flush();

        $this->addFlash('success', 'Parametres "' . (self::CATEGORY_LABELS[$category] ?? $category) . '" enregistres.');
        return $this->redirectToRoute('admin_settings_index', ['tab' => $category]);
    }
}
