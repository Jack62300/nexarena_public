<?php

namespace App\Controller;

use App\Service\MailerService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    private const REASONS = [
        'contact'      => 'Contact général',
        'recrutement'  => 'Recrutement',
        'partenariat'  => 'Partenariat',
        'abus'         => 'Signalement d\'abus',
        'rgpd'         => 'Demande RGPD',
        'bug'          => 'Signalement de bug',
        'candidature'  => 'Candidature spontanée',
        'autre'        => 'Autre',
    ];

    /** Délai minimum entre deux envois (en secondes) */
    private const RATE_LIMIT_SECONDS = 300;

    public function __construct(
        private MailerService $mailer,
        private SettingsService $settings,
    ) {}

    #[Route('/contact', name: 'page_contact', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $success = false;
        $errors  = [];
        $old     = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_form', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('page_contact');
            }

            // Anti-spam : délai minimum via session
            $session = $request->getSession();
            $lastSent = $session->get('contact_last_sent', 0);
            if (time() - $lastSent < self::RATE_LIMIT_SECONDS) {
                $remaining = self::RATE_LIMIT_SECONDS - (time() - $lastSent);
                $this->addFlash('error', sprintf(
                    'Veuillez patienter %d minute(s) entre chaque envoi.',
                    (int) ceil($remaining / 60)
                ));
                return $this->redirectToRoute('page_contact');
            }

            // Récupération et nettoyage des champs
            $old = [
                'prenom'     => trim((string) $request->request->get('prenom', '')),
                'nom'        => trim((string) $request->request->get('nom', '')),
                'age'        => trim((string) $request->request->get('age', '')),
                'email'      => trim((string) $request->request->get('email', '')),
                'discord_id' => trim((string) $request->request->get('discord_id', '')),
                'raison'     => $request->request->get('raison', ''),
                'message'    => trim((string) $request->request->get('message', '')),
            ];

            // Validation
            if ($old['prenom'] === '' || mb_strlen($old['prenom']) > 50) {
                $errors['prenom'] = 'Le prénom est requis (50 caractères max).';
            }
            if ($old['nom'] === '' || mb_strlen($old['nom']) > 50) {
                $errors['nom'] = 'Le nom est requis (50 caractères max).';
            }
            $age = (int) $old['age'];
            if ($old['age'] === '' || $age < 13 || $age > 120) {
                $errors['age'] = 'L\'âge doit être compris entre 13 et 120 ans.';
            }
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 180) {
                $errors['email'] = 'L\'adresse email est invalide.';
            }
            if ($old['discord_id'] !== '' && !preg_match('/^\d{17,20}$/', $old['discord_id'])) {
                $errors['discord_id'] = 'L\'ID Discord doit contenir entre 17 et 20 chiffres.';
            }
            if (!array_key_exists($old['raison'], self::REASONS)) {
                $errors['raison'] = 'Veuillez sélectionner une raison valide.';
            }
            if ($old['message'] === '' || mb_strlen($old['message']) < 20) {
                $errors['message'] = 'Le message doit contenir au moins 20 caractères.';
            }
            if (mb_strlen($old['message']) > 3000) {
                $errors['message'] = 'Le message ne peut pas dépasser 3000 caractères.';
            }

            // Honeypot anti-bot
            if ($request->request->get('website', '') !== '') {
                // Bot détecté, on simule le succès sans rien faire
                $success = true;
            }

            if (empty($errors) && !$success) {
                try {
                    $this->mailer->sendContactNotification(
                        $old['prenom'],
                        $old['nom'],
                        $age,
                        $old['email'],
                        $old['discord_id'] ?: null,
                        self::REASONS[$old['raison']],
                        $old['message'],
                    );
                    $this->mailer->sendContactConfirmation(
                        $old['prenom'],
                        $old['email'],
                        self::REASONS[$old['raison']],
                    );
                } catch (\Throwable) {
                    // On laisse le succès apparent pour ne pas exposer les erreurs mailer
                }

                $session->set('contact_last_sent', time());
                $success = true;
                $old = [];
            }
        }

        return $this->render('pages/contact.html.twig', [
            'reasons' => self::REASONS,
            'success' => $success,
            'errors'  => $errors,
            'old'     => $old,
        ]);
    }
}
