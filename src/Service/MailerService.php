<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private SettingsService $settings,
    ) {}

    public function sendEmailVerification(User $user): void
    {
        $siteName  = $this->settings->get('site_name', 'Nexarena') ?? 'Nexarena';
        $siteEmail = $this->settings->get('site_email', 'noreply@nexarena.com') ?? 'noreply@nexarena.com';

        $verifyUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $user->getEmailVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->renderEmailVerificationTemplate($user, $verifyUrl, $siteName);

        $email = (new Email())
            ->from(sprintf('%s <%s>', $siteName, $siteEmail))
            ->to($user->getEmail() ?? '')
            ->subject('Vérifiez votre adresse email — ' . $siteName)
            ->html($html);

        $this->mailer->send($email);
    }

    private function renderEmailVerificationTemplate(User $user, string $verifyUrl, string $siteName): string
    {
        $username = htmlspecialchars($user->getUsername() ?? '', ENT_QUOTES);
        $verifyUrl = htmlspecialchars($verifyUrl, ENT_QUOTES);
        $siteName = htmlspecialchars($siteName, ENT_QUOTES);
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vérifiez votre email — {$siteName}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #080e17; font-family: 'Segoe UI', Arial, sans-serif; color: #c8d6e8; }
  .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px; }
  .card { background: #0d1b2d; border: 1px solid rgba(69,248,130,0.15); border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #0d1b2d 0%, #0a2015 100%); padding: 40px 40px 32px; text-align: center; border-bottom: 1px solid rgba(69,248,130,0.12); }
  .logo { font-size: 28px; font-weight: 800; color: #45f882; letter-spacing: -0.5px; margin-bottom: 6px; }
  .logo span { color: #fff; }
  .tagline { font-size: 12px; color: rgba(200,214,232,0.5); text-transform: uppercase; letter-spacing: 2px; }
  .body { padding: 40px; }
  .greeting { font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 16px; }
  .text { font-size: 15px; line-height: 1.7; color: rgba(200,214,232,0.75); margin-bottom: 24px; }
  .btn-wrap { text-align: center; margin: 32px 0; }
  .btn { display: inline-block; background: #45f882; color: #050d18; font-size: 16px; font-weight: 700; padding: 15px 40px; border-radius: 10px; text-decoration: none; letter-spacing: 0.3px; }
  .divider { height: 1px; background: rgba(69,248,130,0.1); margin: 28px 0; }
  .small { font-size: 12px; color: rgba(200,214,232,0.4); line-height: 1.6; }
  .url-box { background: rgba(69,248,130,0.05); border: 1px solid rgba(69,248,130,0.1); border-radius: 8px; padding: 12px 16px; font-size: 11px; color: rgba(200,214,232,0.5); word-break: break-all; margin-top: 12px; }
  .footer { background: rgba(0,0,0,0.2); padding: 20px 40px; text-align: center; font-size: 12px; color: rgba(200,214,232,0.35); border-top: 1px solid rgba(69,248,130,0.08); }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo">Nex<span>arena</span></div>
      <div class="tagline">Plateforme de serveurs de jeux</div>
    </div>
    <div class="body">
      <div class="greeting">Bonjour {$username} 👋</div>
      <p class="text">Merci de vous être inscrit sur <strong>{$siteName}</strong> ! Pour activer votre compte et accéder à toutes les fonctionnalités, vous devez vérifier votre adresse email.</p>
      <p class="text">Cliquez sur le bouton ci-dessous pour confirmer votre adresse :</p>
      <div class="btn-wrap">
        <a href="{$verifyUrl}" class="btn">✉ Vérifier mon email</a>
      </div>
      <div class="divider"></div>
      <p class="small">Ce lien est valable <strong>24 heures</strong>. Si vous n'avez pas créé de compte sur {$siteName}, ignorez cet email.</p>
      <p class="small">Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :</p>
      <div class="url-box">{$verifyUrl}</div>
    </div>
    <div class="footer">© {$year} {$siteName} — Cet email a été envoyé automatiquement, merci de ne pas y répondre.</div>
  </div>
</div>
</body>
</html>
HTML;
    }

    public function sendContactNotification(
        string $prenom,
        string $nom,
        int $age,
        string $email,
        ?string $discordId,
        string $raison,
        string $message,
    ): void {
        $siteName     = $this->settings->get('site_name', 'Nexarena') ?? 'Nexarena';
        $siteEmail    = $this->settings->get('site_email', 'noreply@nexarena.com') ?? 'noreply@nexarena.com';
        $contactEmail = $this->settings->get('contact_email', 'contact@nexarena.fr') ?? 'contact@nexarena.fr';

        $html = $this->renderContactNotificationTemplate(
            $prenom, $nom, $age, $email, $discordId, $raison, $message, $siteName
        );

        $mail = (new Email())
            ->from(sprintf('%s <%s>', $siteName, $siteEmail))
            ->to($contactEmail)
            ->replyTo($email)
            ->subject("[Contact] {$raison} — {$prenom} {$nom}")
            ->html($html);

        $this->mailer->send($mail);
    }

    public function sendContactConfirmation(string $prenom, string $email, string $raison): void
    {
        $siteName  = $this->settings->get('site_name', 'Nexarena') ?? 'Nexarena';
        $siteEmail = $this->settings->get('site_email', 'noreply@nexarena.com') ?? 'noreply@nexarena.com';

        $html = $this->renderContactConfirmationTemplate($prenom, $raison, $siteName);

        $mail = (new Email())
            ->from(sprintf('%s <%s>', $siteName, $siteEmail))
            ->to($email)
            ->subject('Votre message a bien été reçu — ' . $siteName)
            ->html($html);

        $this->mailer->send($mail);
    }

    private function renderContactNotificationTemplate(
        string $prenom,
        string $nom,
        int $age,
        string $email,
        ?string $discordId,
        string $raison,
        string $message,
        string $siteName,
    ): string {
        $safePrenom    = htmlspecialchars($prenom, ENT_QUOTES);
        $safeNom       = htmlspecialchars($nom, ENT_QUOTES);
        $safeEmail     = htmlspecialchars($email, ENT_QUOTES);
        $safeDiscord   = $discordId ? htmlspecialchars($discordId, ENT_QUOTES) : '—';
        $safeRaison    = htmlspecialchars($raison, ENT_QUOTES);
        $safeMessage   = nl2br(htmlspecialchars($message, ENT_QUOTES));
        $safeSiteName  = htmlspecialchars($siteName, ENT_QUOTES);
        $year          = date('Y');
        $date          = (new \DateTime())->format('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nouveau contact — {$safeSiteName}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #080e17; font-family: 'Segoe UI', Arial, sans-serif; color: #c8d6e8; }
  .wrapper { max-width: 600px; margin: 40px auto; padding: 0 16px; }
  .card { background: #0d1b2d; border: 1px solid rgba(69,248,130,0.15); border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #0d1b2d 0%, #0a2015 100%); padding: 32px 40px; text-align: center; border-bottom: 1px solid rgba(69,248,130,0.12); }
  .logo { font-size: 26px; font-weight: 800; color: #45f882; letter-spacing: -0.5px; margin-bottom: 4px; }
  .logo span { color: #fff; }
  .subtitle { font-size: 13px; color: rgba(200,214,232,0.5); }
  .body { padding: 36px 40px; }
  .title { font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 24px; }
  .badge { display: inline-block; background: rgba(69,248,130,0.12); border: 1px solid rgba(69,248,130,0.25); color: #45f882; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; margin-bottom: 20px; }
  .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 20px; margin-bottom: 24px; }
  .info-label { font-size: 13px; color: rgba(200,214,232,0.5); }
  .info-value { font-size: 13px; color: #fff; font-weight: 600; }
  .msg-label { font-size: 13px; color: rgba(200,214,232,0.5); margin-bottom: 10px; }
  .msg-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 18px 20px; font-size: 14px; color: #c8d6e8; line-height: 1.7; }
  .footer { background: rgba(0,0,0,0.2); padding: 16px 40px; text-align: center; font-size: 11px; color: rgba(200,214,232,0.3); border-top: 1px solid rgba(69,248,130,0.08); }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo">Nex<span>arena</span></div>
      <div class="subtitle">Nouveau message reçu via le formulaire de contact</div>
    </div>
    <div class="body">
      <div class="title">📬 Nouveau message de contact</div>
      <div class="badge">{$safeRaison}</div>
      <div class="info-grid">
        <span class="info-label">Prénom</span><span class="info-value">{$safePrenom}</span>
        <span class="info-label">Nom</span><span class="info-value">{$safeNom}</span>
        <span class="info-label">Âge</span><span class="info-value">{$age} ans</span>
        <span class="info-label">Email</span><span class="info-value">{$safeEmail}</span>
        <span class="info-label">Discord ID</span><span class="info-value">{$safeDiscord}</span>
        <span class="info-label">Reçu le</span><span class="info-value">{$date}</span>
      </div>
      <div class="msg-label">Message :</div>
      <div class="msg-box">{$safeMessage}</div>
    </div>
    <div class="footer">© {$year} {$safeSiteName} — Message reçu via le formulaire de contact public.</div>
  </div>
</div>
</body>
</html>
HTML;
    }

    private function renderContactConfirmationTemplate(string $prenom, string $raison, string $siteName): string
    {
        $safePrenom   = htmlspecialchars($prenom, ENT_QUOTES);
        $safeRaison   = htmlspecialchars($raison, ENT_QUOTES);
        $safeSiteName = htmlspecialchars($siteName, ENT_QUOTES);
        $year         = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Message reçu — {$safeSiteName}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #080e17; font-family: 'Segoe UI', Arial, sans-serif; color: #c8d6e8; }
  .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px; }
  .card { background: #0d1b2d; border: 1px solid rgba(69,248,130,0.15); border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #0d1b2d 0%, #0a2015 100%); padding: 40px 40px 32px; text-align: center; border-bottom: 1px solid rgba(69,248,130,0.12); }
  .logo { font-size: 28px; font-weight: 800; color: #45f882; letter-spacing: -0.5px; margin-bottom: 6px; }
  .logo span { color: #fff; }
  .check { font-size: 48px; margin-bottom: 10px; }
  .alert-title { font-size: 18px; font-weight: 700; color: #45f882; }
  .body { padding: 40px; }
  .greeting { font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 16px; }
  .text { font-size: 15px; line-height: 1.7; color: rgba(200,214,232,0.75); margin-bottom: 20px; }
  .badge { display: inline-block; background: rgba(69,248,130,0.1); border: 1px solid rgba(69,248,130,0.2); color: #45f882; padding: 5px 14px; border-radius: 99px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
  .divider { height: 1px; background: rgba(69,248,130,0.1); margin: 24px 0; }
  .small { font-size: 12px; color: rgba(200,214,232,0.4); line-height: 1.6; }
  .footer { background: rgba(0,0,0,0.2); padding: 20px 40px; text-align: center; font-size: 12px; color: rgba(200,214,232,0.35); border-top: 1px solid rgba(69,248,130,0.08); }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo">Nex<span>arena</span></div>
      <div class="check">✅</div>
      <div class="alert-title">Message bien reçu !</div>
    </div>
    <div class="body">
      <div class="greeting">Bonjour {$safePrenom} 👋</div>
      <p class="text">Nous avons bien reçu votre message concernant :</p>
      <div class="badge">{$safeRaison}</div>
      <p class="text">Notre équipe vous répondra dans les meilleurs délais, généralement sous <strong>2 à 5 jours ouvrés</strong>.</p>
      <div class="divider"></div>
      <p class="small">Merci de votre confiance. Si vous avez d'autres questions, vous pouvez envoyer un nouveau message depuis notre page de contact.</p>
    </div>
    <div class="footer">© {$year} {$safeSiteName} — Cet email a été envoyé automatiquement, merci de ne pas y répondre.</div>
  </div>
</div>
</body>
</html>
HTML;
    }

}
