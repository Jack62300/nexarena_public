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
        $siteName = $this->settings->get('site_name', 'Nexarena');
        $siteEmail = $this->settings->get('site_email', 'noreply@nexarena.com');

        $verifyUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $user->getEmailVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->renderEmailVerificationTemplate($user, $verifyUrl, $siteName);

        $email = (new Email())
            ->from(sprintf('%s <%s>', $siteName, $siteEmail))
            ->to($user->getEmail())
            ->subject('Vérifiez votre adresse email — ' . $siteName)
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendDeviceVerification(User $user, string $newIp): void
    {
        $siteName = $this->settings->get('site_name', 'Nexarena');
        $siteEmail = $this->settings->get('site_email', 'noreply@nexarena.com');

        $verifyUrl = $this->urlGenerator->generate(
            'app_verify_device',
            ['token' => $user->getDeviceVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->renderDeviceVerificationTemplate($user, $verifyUrl, $newIp, $siteName);

        $email = (new Email())
            ->from(sprintf('%s <%s>', $siteName, $siteEmail))
            ->to($user->getEmail())
            ->subject('Nouvelle connexion détectée — ' . $siteName)
            ->html($html);

        $this->mailer->send($email);
    }

    private function renderEmailVerificationTemplate(User $user, string $verifyUrl, string $siteName): string
    {
        $username = htmlspecialchars($user->getUsername(), ENT_QUOTES);
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

    private function renderDeviceVerificationTemplate(User $user, string $verifyUrl, string $newIp, string $siteName): string
    {
        $username = htmlspecialchars($user->getUsername(), ENT_QUOTES);
        $verifyUrl = htmlspecialchars($verifyUrl, ENT_QUOTES);
        $newIp = htmlspecialchars($newIp, ENT_QUOTES);
        $siteName = htmlspecialchars($siteName, ENT_QUOTES);
        $year = date('Y');
        $date = (new \DateTime())->format('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nouvelle connexion — {$siteName}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #080e17; font-family: 'Segoe UI', Arial, sans-serif; color: #c8d6e8; }
  .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px; }
  .card { background: #0d1b2d; border: 1px solid rgba(255,107,53,0.2); border-radius: 16px; overflow: hidden; }
  .header { background: linear-gradient(135deg, #1a0f08 0%, #0d1b2d 100%); padding: 40px 40px 32px; text-align: center; border-bottom: 1px solid rgba(255,107,53,0.15); }
  .logo { font-size: 28px; font-weight: 800; color: #45f882; letter-spacing: -0.5px; margin-bottom: 6px; }
  .logo span { color: #fff; }
  .alert-icon { font-size: 48px; margin-bottom: 12px; }
  .alert-title { font-size: 20px; font-weight: 700; color: #ff6b35; }
  .body { padding: 40px; }
  .greeting { font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 16px; }
  .text { font-size: 15px; line-height: 1.7; color: rgba(200,214,232,0.75); margin-bottom: 24px; }
  .info-box { background: rgba(255,107,53,0.06); border: 1px solid rgba(255,107,53,0.2); border-radius: 10px; padding: 16px 20px; margin-bottom: 28px; }
  .info-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; border-bottom: 1px solid rgba(255,107,53,0.08); }
  .info-row:last-child { border-bottom: none; }
  .info-label { color: rgba(200,214,232,0.5); }
  .info-value { color: #fff; font-weight: 600; }
  .btn-wrap { text-align: center; margin: 32px 0; }
  .btn { display: inline-block; background: #45f882; color: #050d18; font-size: 16px; font-weight: 700; padding: 15px 40px; border-radius: 10px; text-decoration: none; letter-spacing: 0.3px; }
  .divider { height: 1px; background: rgba(255,107,53,0.1); margin: 28px 0; }
  .small { font-size: 12px; color: rgba(200,214,232,0.4); line-height: 1.6; }
  .url-box { background: rgba(69,248,130,0.05); border: 1px solid rgba(69,248,130,0.1); border-radius: 8px; padding: 12px 16px; font-size: 11px; color: rgba(200,214,232,0.5); word-break: break-all; margin-top: 12px; }
  .warn { color: #ff6b35; font-weight: 600; }
  .footer { background: rgba(0,0,0,0.2); padding: 20px 40px; text-align: center; font-size: 12px; color: rgba(200,214,232,0.35); border-top: 1px solid rgba(255,107,53,0.08); }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo">Nex<span>arena</span></div>
      <div class="alert-icon">🔐</div>
      <div class="alert-title">Nouvelle connexion détectée</div>
    </div>
    <div class="body">
      <div class="greeting">Bonjour {$username},</div>
      <p class="text">Une connexion a été tentée sur votre compte depuis une <strong>adresse IP inconnue</strong>. Pour protéger votre compte, vous devez valider ce nouvel appareil avant de pouvoir vous connecter.</p>
      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Adresse IP</span>
          <span class="info-value">{$newIp}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Date et heure</span>
          <span class="info-value">{$date}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Compte</span>
          <span class="info-value">{$username}</span>
        </div>
      </div>
      <p class="text">Si c'est bien vous, cliquez sur le bouton ci-dessous pour valider cet appareil :</p>
      <div class="btn-wrap">
        <a href="{$verifyUrl}" class="btn">✅ Valider cet appareil</a>
      </div>
      <div class="divider"></div>
      <p class="small"><span class="warn">⚠ Ce n'est pas vous ?</span> Ignorez cet email et changez immédiatement votre mot de passe. Ce lien est valable <strong>1 heure</strong>.</p>
      <p class="small" style="margin-top:10px">Si le bouton ne fonctionne pas, copiez et collez ce lien :</p>
      <div class="url-box">{$verifyUrl}</div>
    </div>
    <div class="footer">© {$year} {$siteName} — Cet email a été envoyé automatiquement, merci de ne pas y répondre.</div>
  </div>
</div>
</body>
</html>
HTML;
    }
}
