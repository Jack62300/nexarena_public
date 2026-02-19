<?php

namespace App\Controller\Admin;

use App\Service\IpSecurityService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/securite/pays', name: 'admin_country_access_')]
#[IsGranted('ROLE_RESPONSABLE')]
class CountryAccessController extends AbstractController
{
    // ─────────────────────────────────────────────────────────────────────
    // Liste exhaustive des pays par région (ISO 3166-1 alpha-2, noms FR)
    // ─────────────────────────────────────────────────────────────────────
    private const COUNTRIES = [
        // ── Europe ──
        'AD' => ['name' => 'Andorre',               'region' => 'europe'],
        'AL' => ['name' => 'Albanie',               'region' => 'europe'],
        'AT' => ['name' => 'Autriche',              'region' => 'europe'],
        'BA' => ['name' => 'Bosnie-Herzégovine',    'region' => 'europe'],
        'BE' => ['name' => 'Belgique',              'region' => 'europe'],
        'BG' => ['name' => 'Bulgarie',              'region' => 'europe'],
        'BY' => ['name' => 'Biélorussie',           'region' => 'europe'],
        'CH' => ['name' => 'Suisse',                'region' => 'europe'],
        'CY' => ['name' => 'Chypre',                'region' => 'europe'],
        'CZ' => ['name' => 'République tchèque',    'region' => 'europe'],
        'DE' => ['name' => 'Allemagne',             'region' => 'europe'],
        'DK' => ['name' => 'Danemark',              'region' => 'europe'],
        'EE' => ['name' => 'Estonie',               'region' => 'europe'],
        'ES' => ['name' => 'Espagne',               'region' => 'europe'],
        'FI' => ['name' => 'Finlande',              'region' => 'europe'],
        'FR' => ['name' => 'France',                'region' => 'europe'],
        'GB' => ['name' => 'Royaume-Uni',           'region' => 'europe'],
        'GR' => ['name' => 'Grèce',                 'region' => 'europe'],
        'HR' => ['name' => 'Croatie',               'region' => 'europe'],
        'HU' => ['name' => 'Hongrie',               'region' => 'europe'],
        'IE' => ['name' => 'Irlande',               'region' => 'europe'],
        'IS' => ['name' => 'Islande',               'region' => 'europe'],
        'IT' => ['name' => 'Italie',                'region' => 'europe'],
        'LI' => ['name' => 'Liechtenstein',         'region' => 'europe'],
        'LT' => ['name' => 'Lituanie',              'region' => 'europe'],
        'LU' => ['name' => 'Luxembourg',            'region' => 'europe'],
        'LV' => ['name' => 'Lettonie',              'region' => 'europe'],
        'MC' => ['name' => 'Monaco',                'region' => 'europe'],
        'MD' => ['name' => 'Moldavie',              'region' => 'europe'],
        'ME' => ['name' => 'Monténégro',            'region' => 'europe'],
        'MK' => ['name' => 'Macédoine du Nord',     'region' => 'europe'],
        'MT' => ['name' => 'Malte',                 'region' => 'europe'],
        'NL' => ['name' => 'Pays-Bas',              'region' => 'europe'],
        'NO' => ['name' => 'Norvège',               'region' => 'europe'],
        'PL' => ['name' => 'Pologne',               'region' => 'europe'],
        'PT' => ['name' => 'Portugal',              'region' => 'europe'],
        'RO' => ['name' => 'Roumanie',              'region' => 'europe'],
        'RS' => ['name' => 'Serbie',                'region' => 'europe'],
        'RU' => ['name' => 'Russie',                'region' => 'europe'],
        'SE' => ['name' => 'Suède',                 'region' => 'europe'],
        'SI' => ['name' => 'Slovénie',              'region' => 'europe'],
        'SK' => ['name' => 'Slovaquie',             'region' => 'europe'],
        'SM' => ['name' => 'Saint-Marin',           'region' => 'europe'],
        'UA' => ['name' => 'Ukraine',               'region' => 'europe'],
        'VA' => ['name' => 'Vatican',               'region' => 'europe'],
        'XK' => ['name' => 'Kosovo',                'region' => 'europe'],

        // ── Amérique du Nord ──
        'CA' => ['name' => 'Canada',                'region' => 'amerique'],
        'MX' => ['name' => 'Mexique',               'region' => 'amerique'],
        'US' => ['name' => 'États-Unis',            'region' => 'amerique'],
        'GT' => ['name' => 'Guatemala',             'region' => 'amerique'],
        'BZ' => ['name' => 'Belize',                'region' => 'amerique'],
        'HN' => ['name' => 'Honduras',              'region' => 'amerique'],
        'SV' => ['name' => 'Salvador',              'region' => 'amerique'],
        'NI' => ['name' => 'Nicaragua',             'region' => 'amerique'],
        'CR' => ['name' => 'Costa Rica',            'region' => 'amerique'],
        'PA' => ['name' => 'Panama',                'region' => 'amerique'],
        'CU' => ['name' => 'Cuba',                  'region' => 'amerique'],
        'JM' => ['name' => 'Jamaïque',              'region' => 'amerique'],
        'HT' => ['name' => 'Haïti',                 'region' => 'amerique'],
        'DO' => ['name' => 'Rép. dominicaine',      'region' => 'amerique'],
        'TT' => ['name' => 'Trinité-et-Tobago',     'region' => 'amerique'],
        'BB' => ['name' => 'Barbade',               'region' => 'amerique'],

        // ── Amérique du Sud ──
        'AR' => ['name' => 'Argentine',             'region' => 'amerique'],
        'BO' => ['name' => 'Bolivie',               'region' => 'amerique'],
        'BR' => ['name' => 'Brésil',                'region' => 'amerique'],
        'CL' => ['name' => 'Chili',                 'region' => 'amerique'],
        'CO' => ['name' => 'Colombie',              'region' => 'amerique'],
        'EC' => ['name' => 'Équateur',              'region' => 'amerique'],
        'GY' => ['name' => 'Guyana',                'region' => 'amerique'],
        'PE' => ['name' => 'Pérou',                 'region' => 'amerique'],
        'PY' => ['name' => 'Paraguay',              'region' => 'amerique'],
        'SR' => ['name' => 'Suriname',              'region' => 'amerique'],
        'UY' => ['name' => 'Uruguay',               'region' => 'amerique'],
        'VE' => ['name' => 'Venezuela',             'region' => 'amerique'],

        // ── Asie ──
        'AF' => ['name' => 'Afghanistan',           'region' => 'asie'],
        'AM' => ['name' => 'Arménie',               'region' => 'asie'],
        'AZ' => ['name' => 'Azerbaïdjan',           'region' => 'asie'],
        'BD' => ['name' => 'Bangladesh',            'region' => 'asie'],
        'BN' => ['name' => 'Brunei',                'region' => 'asie'],
        'BT' => ['name' => 'Bhoutan',               'region' => 'asie'],
        'CN' => ['name' => 'Chine',                 'region' => 'asie'],
        'GE' => ['name' => 'Géorgie',               'region' => 'asie'],
        'HK' => ['name' => 'Hong Kong',             'region' => 'asie'],
        'ID' => ['name' => 'Indonésie',             'region' => 'asie'],
        'IL' => ['name' => 'Israël',                'region' => 'asie'],
        'IN' => ['name' => 'Inde',                  'region' => 'asie'],
        'IQ' => ['name' => 'Irak',                  'region' => 'asie'],
        'IR' => ['name' => 'Iran',                  'region' => 'asie'],
        'JP' => ['name' => 'Japon',                 'region' => 'asie'],
        'KG' => ['name' => 'Kirghizistan',          'region' => 'asie'],
        'KH' => ['name' => 'Cambodge',              'region' => 'asie'],
        'KP' => ['name' => 'Corée du Nord',         'region' => 'asie'],
        'KR' => ['name' => 'Corée du Sud',          'region' => 'asie'],
        'KZ' => ['name' => 'Kazakhstan',            'region' => 'asie'],
        'LA' => ['name' => 'Laos',                  'region' => 'asie'],
        'LB' => ['name' => 'Liban',                 'region' => 'asie'],
        'LK' => ['name' => 'Sri Lanka',             'region' => 'asie'],
        'MM' => ['name' => 'Myanmar',               'region' => 'asie'],
        'MN' => ['name' => 'Mongolie',              'region' => 'asie'],
        'MY' => ['name' => 'Malaisie',              'region' => 'asie'],
        'NP' => ['name' => 'Népal',                 'region' => 'asie'],
        'PH' => ['name' => 'Philippines',           'region' => 'asie'],
        'PK' => ['name' => 'Pakistan',              'region' => 'asie'],
        'SG' => ['name' => 'Singapour',             'region' => 'asie'],
        'SY' => ['name' => 'Syrie',                 'region' => 'asie'],
        'TH' => ['name' => 'Thaïlande',             'region' => 'asie'],
        'TJ' => ['name' => 'Tadjikistan',           'region' => 'asie'],
        'TM' => ['name' => 'Turkménistan',          'region' => 'asie'],
        'TR' => ['name' => 'Turquie',               'region' => 'asie'],
        'TW' => ['name' => 'Taïwan',                'region' => 'asie'],
        'UZ' => ['name' => 'Ouzbékistan',           'region' => 'asie'],
        'VN' => ['name' => 'Viêt Nam',              'region' => 'asie'],

        // ── Moyen-Orient ──
        'AE' => ['name' => 'Émirats arabes unis',   'region' => 'moyen-orient'],
        'BH' => ['name' => 'Bahreïn',               'region' => 'moyen-orient'],
        'JO' => ['name' => 'Jordanie',              'region' => 'moyen-orient'],
        'KW' => ['name' => 'Koweït',                'region' => 'moyen-orient'],
        'OM' => ['name' => 'Oman',                  'region' => 'moyen-orient'],
        'PS' => ['name' => 'Palestine',             'region' => 'moyen-orient'],
        'QA' => ['name' => 'Qatar',                 'region' => 'moyen-orient'],
        'SA' => ['name' => 'Arabie saoudite',       'region' => 'moyen-orient'],
        'YE' => ['name' => 'Yémen',                 'region' => 'moyen-orient'],

        // ── Afrique ──
        'AO' => ['name' => 'Angola',                'region' => 'afrique'],
        'BF' => ['name' => 'Burkina Faso',          'region' => 'afrique'],
        'BI' => ['name' => 'Burundi',               'region' => 'afrique'],
        'BJ' => ['name' => 'Bénin',                 'region' => 'afrique'],
        'BW' => ['name' => 'Botswana',              'region' => 'afrique'],
        'CD' => ['name' => 'RD Congo',              'region' => 'afrique'],
        'CF' => ['name' => 'Centrafrique',          'region' => 'afrique'],
        'CG' => ['name' => 'Congo',                 'region' => 'afrique'],
        'CI' => ['name' => "Côte d'Ivoire",         'region' => 'afrique'],
        'CM' => ['name' => 'Cameroun',              'region' => 'afrique'],
        'CV' => ['name' => 'Cap-Vert',              'region' => 'afrique'],
        'DJ' => ['name' => 'Djibouti',              'region' => 'afrique'],
        'DZ' => ['name' => 'Algérie',               'region' => 'afrique'],
        'EG' => ['name' => 'Égypte',                'region' => 'afrique'],
        'ER' => ['name' => 'Érythrée',              'region' => 'afrique'],
        'ET' => ['name' => 'Éthiopie',              'region' => 'afrique'],
        'GA' => ['name' => 'Gabon',                 'region' => 'afrique'],
        'GH' => ['name' => 'Ghana',                 'region' => 'afrique'],
        'GM' => ['name' => 'Gambie',                'region' => 'afrique'],
        'GN' => ['name' => 'Guinée',                'region' => 'afrique'],
        'GQ' => ['name' => 'Guinée équatoriale',    'region' => 'afrique'],
        'GW' => ['name' => 'Guinée-Bissau',         'region' => 'afrique'],
        'KE' => ['name' => 'Kenya',                 'region' => 'afrique'],
        'KM' => ['name' => 'Comores',               'region' => 'afrique'],
        'LR' => ['name' => 'Libéria',               'region' => 'afrique'],
        'LS' => ['name' => 'Lesotho',               'region' => 'afrique'],
        'LY' => ['name' => 'Libye',                 'region' => 'afrique'],
        'MA' => ['name' => 'Maroc',                 'region' => 'afrique'],
        'MG' => ['name' => 'Madagascar',            'region' => 'afrique'],
        'ML' => ['name' => 'Mali',                  'region' => 'afrique'],
        'MR' => ['name' => 'Mauritanie',            'region' => 'afrique'],
        'MU' => ['name' => 'Maurice',               'region' => 'afrique'],
        'MW' => ['name' => 'Malawi',                'region' => 'afrique'],
        'MZ' => ['name' => 'Mozambique',            'region' => 'afrique'],
        'NA' => ['name' => 'Namibie',               'region' => 'afrique'],
        'NE' => ['name' => 'Niger',                 'region' => 'afrique'],
        'NG' => ['name' => 'Nigeria',               'region' => 'afrique'],
        'RW' => ['name' => 'Rwanda',                'region' => 'afrique'],
        'SC' => ['name' => 'Seychelles',            'region' => 'afrique'],
        'SD' => ['name' => 'Soudan',                'region' => 'afrique'],
        'SL' => ['name' => 'Sierra Leone',          'region' => 'afrique'],
        'SN' => ['name' => 'Sénégal',               'region' => 'afrique'],
        'SO' => ['name' => 'Somalie',               'region' => 'afrique'],
        'SS' => ['name' => 'Soudan du Sud',         'region' => 'afrique'],
        'ST' => ['name' => 'Sao Tomé-et-Principe',  'region' => 'afrique'],
        'SZ' => ['name' => 'Eswatini',              'region' => 'afrique'],
        'TD' => ['name' => 'Tchad',                 'region' => 'afrique'],
        'TG' => ['name' => 'Togo',                  'region' => 'afrique'],
        'TN' => ['name' => 'Tunisie',               'region' => 'afrique'],
        'TZ' => ['name' => 'Tanzanie',              'region' => 'afrique'],
        'UG' => ['name' => 'Ouganda',               'region' => 'afrique'],
        'ZA' => ['name' => 'Afrique du Sud',        'region' => 'afrique'],
        'ZM' => ['name' => 'Zambie',                'region' => 'afrique'],
        'ZW' => ['name' => 'Zimbabwe',              'region' => 'afrique'],

        // ── Océanie ──
        'AU' => ['name' => 'Australie',             'region' => 'oceanie'],
        'FJ' => ['name' => 'Fidji',                 'region' => 'oceanie'],
        'FM' => ['name' => 'Micronésie',            'region' => 'oceanie'],
        'KI' => ['name' => 'Kiribati',              'region' => 'oceanie'],
        'MH' => ['name' => 'Îles Marshall',         'region' => 'oceanie'],
        'NR' => ['name' => 'Nauru',                 'region' => 'oceanie'],
        'NZ' => ['name' => 'Nouvelle-Zélande',      'region' => 'oceanie'],
        'PG' => ['name' => 'Papouasie-Nvl.-Guinée', 'region' => 'oceanie'],
        'PW' => ['name' => 'Palaos',                'region' => 'oceanie'],
        'SB' => ['name' => 'Îles Salomon',          'region' => 'oceanie'],
        'TO' => ['name' => 'Tonga',                 'region' => 'oceanie'],
        'TV' => ['name' => 'Tuvalu',                'region' => 'oceanie'],
        'VU' => ['name' => 'Vanuatu',               'region' => 'oceanie'],
        'WS' => ['name' => 'Samoa',                 'region' => 'oceanie'],
    ];

    private const REGION_LABELS = [
        'europe'       => ['label' => 'Europe',        'icon' => 'fas fa-landmark'],
        'amerique'     => ['label' => 'Amériques',      'icon' => 'fas fa-globe-americas'],
        'asie'         => ['label' => 'Asie',           'icon' => 'fas fa-globe-asia'],
        'moyen-orient' => ['label' => 'Moyen-Orient',   'icon' => 'fas fa-mosque'],
        'afrique'      => ['label' => 'Afrique',        'icon' => 'fas fa-globe-africa'],
        'oceanie'      => ['label' => 'Océanie',        'icon' => 'fas fa-water'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SettingsService $settings,
        private readonly IpSecurityService $ipSecurity,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/country_access/index.html.twig', [
            'countries'      => self::COUNTRIES,
            'regionLabels'   => self::REGION_LABELS,
            'selected'       => $this->getSelectedCodes(),
            'blockEnabled'   => $this->settings->getBool('country_block_enabled', false),
            'vpnBlockSite'   => $this->settings->getBool('vpn_block_enabled', false),
            'adminVpnBlock'  => $this->settings->getBool('admin_vpn_block_enabled', true),
        ]);
    }

    #[Route('', name: 'save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('country_access_save', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_country_access_index');
        }

        // ── Pays autorisés ──────────────────────────────────────────────
        $submitted = $request->request->all('countries') ?? [];
        $valid = array_filter(
            array_map('strtoupper', array_map('trim', $submitted)),
            fn(string $c) => isset(self::COUNTRIES[$c])
        );
        sort($valid);
        $this->saveSettingValue('allowed_countries', implode(',', $valid));

        // ── Toggles ──────────────────────────────────────────────────────
        $this->saveSettingBool('country_block_enabled', $request->request->has('country_block_enabled'));
        $this->saveSettingBool('vpn_block_enabled',     $request->request->has('vpn_block_enabled'));
        $this->saveSettingBool('admin_vpn_block_enabled', $request->request->has('admin_vpn_block_enabled'));

        $this->em->flush();

        $count = count($valid);
        $this->addFlash('success', "Configuration sauvegardée. $count pays autorisé(s).");
        return $this->redirectToRoute('admin_country_access_index');
    }

    /**
     * Diagnostic : teste l'IP courante (ou une IP fournie) et retourne les résultats en JSON.
     * GET /admin/securite/pays/diagnostic?ip=1.2.3.4&flush=1
     */
    #[Route('/diagnostic', name: 'diagnostic', methods: ['GET'])]
    public function diagnostic(Request $request): JsonResponse
    {
        $ip = $request->query->get('ip') ?: ($request->getClientIp() ?? '0.0.0.0');

        // Vider le cache si demandé
        if ($request->query->getBoolean('flush')) {
            $this->ipSecurity->clearCache($ip);
        }

        $apiKey      = $this->settings->get('ipqs_api_key', '');
        $ipqsData    = $this->ipSecurity->getFullReport($ip);
        $isVpn       = $this->ipSecurity->isVpnOrProxy($ip);
        $country     = $this->ipSecurity->getCountryCode($ip);
        $allowed     = $this->ipSecurity->isCountryAllowed($ip);

        $allowedStr          = $this->settings->get('allowed_countries', '');
        $countryBlockEnabled = $this->settings->getBool('country_block_enabled', false);
        $vpnBlockEnabled     = $this->settings->getBool('vpn_block_enabled', false);
        $adminVpnBlock       = $this->settings->getBool('admin_vpn_block_enabled', true);

        $wouldBeBlockedVpn     = $vpnBlockEnabled && $isVpn;
        $wouldBeBlockedCountry = $countryBlockEnabled && !$allowed;

        return $this->json([
            'ip_tested'              => $ip,
            'ip_from_request'        => $request->getClientIp(),
            'api_key_configured'     => !empty(trim($apiKey)),
            'api_key_preview'        => empty(trim($apiKey)) ? '❌ NON CONFIGURÉE' : '✅ ' . substr($apiKey, 0, 8) . '...',
            'settings' => [
                'vpn_block_enabled'      => $vpnBlockEnabled,
                'country_block_enabled'  => $countryBlockEnabled,
                'admin_vpn_block_enabled'=> $adminVpnBlock,
                'allowed_countries'      => $allowedStr ?: '(vide = tous autorisés)',
            ],
            'ipqs_result' => [
                'success'     => $ipqsData['success'] ?? false,
                'error'       => $ipqsData['error'] ?? false,
                'country'     => $country ?: '(indéterminé)',
                'vpn'         => $ipqsData['vpn'] ?? false,
                'proxy'       => $ipqsData['proxy'] ?? false,
                'tor'         => $ipqsData['tor'] ?? false,
                'active_vpn'  => $ipqsData['active_vpn'] ?? false,
                'active_tor'  => $ipqsData['active_tor'] ?? false,
                'fraud_score' => $ipqsData['fraud_score'] ?? 0,
                'isp'         => $ipqsData['ISP'] ?? '',
                'city'        => ($ipqsData['city'] ?? '') . ', ' . ($ipqsData['region'] ?? ''),
            ],
            'verdict' => [
                'is_vpn_detected'         => $isVpn,
                'country_in_allowed_list' => $allowed,
                'would_be_blocked_vpn'    => $wouldBeBlockedVpn,
                'would_be_blocked_country'=> $wouldBeBlockedCountry,
                'would_be_blocked_total'  => $wouldBeBlockedVpn || $wouldBeBlockedCountry,
                'reason'                  => $wouldBeBlockedVpn ? 'VPN/proxy détecté' : ($wouldBeBlockedCountry ? "Pays $country non autorisé" : 'Aucun blocage actif'),
            ],
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function getSelectedCodes(): array
    {
        $raw = $this->settings->get('allowed_countries', '');
        if (empty(trim($raw))) {
            return [];
        }
        return array_filter(array_map('strtoupper', array_map('trim', explode(',', $raw))));
    }

    private function saveSettingValue(string $key, string $value): void
    {
        $this->settings->set($key, $value);
    }

    private function saveSettingBool(string $key, bool $value): void
    {
        $this->settings->set($key, $value ? '1' : '0');
    }

    /**
     * Exposé au template via Twig pour générer l'emoji drapeau depuis le code ISO.
     */
    public static function flagEmoji(string $code): string
    {
        $regional = '';
        foreach (str_split(strtoupper($code)) as $letter) {
            $regional .= mb_chr(0x1F1E6 + ord($letter) - 65);
        }
        return $regional;
    }
}
