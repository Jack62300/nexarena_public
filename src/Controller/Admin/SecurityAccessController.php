<?php

namespace App\Controller\Admin;

use App\Repository\SettingRepository;
use App\Service\IpSecurityService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/securite/acces', name: 'admin_security_access_')]
#[IsGranted('ROLE_RESPONSABLE')]
class SecurityAccessController extends AbstractController
{
    private const REGION_LABELS = [
        'europe'      => 'Europe',
        'amerique'    => 'Amériques',
        'asie'        => 'Asie',
        'moyen_orient'=> 'Moyen-Orient',
        'afrique'     => 'Afrique',
        'oceanie'     => 'Océanie',
    ];

    private const COUNTRIES = [
        // ── Europe ──
        'AD' => ['name' => 'Andorre',             'region' => 'europe'],
        'AL' => ['name' => 'Albanie',              'region' => 'europe'],
        'AT' => ['name' => 'Autriche',             'region' => 'europe'],
        'BA' => ['name' => 'Bosnie-Herzégovine',   'region' => 'europe'],
        'BE' => ['name' => 'Belgique',             'region' => 'europe'],
        'BG' => ['name' => 'Bulgarie',             'region' => 'europe'],
        'BY' => ['name' => 'Biélorussie',          'region' => 'europe'],
        'CH' => ['name' => 'Suisse',               'region' => 'europe'],
        'CY' => ['name' => 'Chypre',               'region' => 'europe'],
        'CZ' => ['name' => 'Tchéquie',             'region' => 'europe'],
        'DE' => ['name' => 'Allemagne',            'region' => 'europe'],
        'DK' => ['name' => 'Danemark',             'region' => 'europe'],
        'EE' => ['name' => 'Estonie',              'region' => 'europe'],
        'ES' => ['name' => 'Espagne',              'region' => 'europe'],
        'FI' => ['name' => 'Finlande',             'region' => 'europe'],
        'FR' => ['name' => 'France',               'region' => 'europe'],
        'GB' => ['name' => 'Royaume-Uni',          'region' => 'europe'],
        'GR' => ['name' => 'Grèce',               'region' => 'europe'],
        'HR' => ['name' => 'Croatie',              'region' => 'europe'],
        'HU' => ['name' => 'Hongrie',              'region' => 'europe'],
        'IE' => ['name' => 'Irlande',              'region' => 'europe'],
        'IS' => ['name' => 'Islande',              'region' => 'europe'],
        'IT' => ['name' => 'Italie',               'region' => 'europe'],
        'LI' => ['name' => 'Liechtenstein',        'region' => 'europe'],
        'LT' => ['name' => 'Lituanie',             'region' => 'europe'],
        'LU' => ['name' => 'Luxembourg',           'region' => 'europe'],
        'LV' => ['name' => 'Lettonie',             'region' => 'europe'],
        'MC' => ['name' => 'Monaco',               'region' => 'europe'],
        'MD' => ['name' => 'Moldavie',             'region' => 'europe'],
        'ME' => ['name' => 'Monténégro',           'region' => 'europe'],
        'MK' => ['name' => 'Macédoine du Nord',    'region' => 'europe'],
        'MT' => ['name' => 'Malte',                'region' => 'europe'],
        'NL' => ['name' => 'Pays-Bas',             'region' => 'europe'],
        'NO' => ['name' => 'Norvège',              'region' => 'europe'],
        'PL' => ['name' => 'Pologne',              'region' => 'europe'],
        'PT' => ['name' => 'Portugal',             'region' => 'europe'],
        'RO' => ['name' => 'Roumanie',             'region' => 'europe'],
        'RS' => ['name' => 'Serbie',               'region' => 'europe'],
        'RU' => ['name' => 'Russie',               'region' => 'europe'],
        'SE' => ['name' => 'Suède',               'region' => 'europe'],
        'SI' => ['name' => 'Slovénie',             'region' => 'europe'],
        'SK' => ['name' => 'Slovaquie',            'region' => 'europe'],
        'SM' => ['name' => 'Saint-Marin',          'region' => 'europe'],
        'UA' => ['name' => 'Ukraine',              'region' => 'europe'],
        'VA' => ['name' => 'Vatican',              'region' => 'europe'],
        'XK' => ['name' => 'Kosovo',               'region' => 'europe'],

        // ── Amériques ──
        'AG' => ['name' => 'Antigua-et-Barbuda',   'region' => 'amerique'],
        'AR' => ['name' => 'Argentine',            'region' => 'amerique'],
        'BB' => ['name' => 'Barbade',              'region' => 'amerique'],
        'BO' => ['name' => 'Bolivie',              'region' => 'amerique'],
        'BR' => ['name' => 'Brésil',              'region' => 'amerique'],
        'BS' => ['name' => 'Bahamas',              'region' => 'amerique'],
        'BZ' => ['name' => 'Belize',               'region' => 'amerique'],
        'CA' => ['name' => 'Canada',               'region' => 'amerique'],
        'CL' => ['name' => 'Chili',               'region' => 'amerique'],
        'CO' => ['name' => 'Colombie',             'region' => 'amerique'],
        'CR' => ['name' => 'Costa Rica',           'region' => 'amerique'],
        'CU' => ['name' => 'Cuba',                 'region' => 'amerique'],
        'DM' => ['name' => 'Dominique',            'region' => 'amerique'],
        'DO' => ['name' => 'Rép. dominicaine',     'region' => 'amerique'],
        'EC' => ['name' => 'Équateur',            'region' => 'amerique'],
        'GD' => ['name' => 'Grenade',              'region' => 'amerique'],
        'GT' => ['name' => 'Guatemala',            'region' => 'amerique'],
        'GY' => ['name' => 'Guyana',               'region' => 'amerique'],
        'HN' => ['name' => 'Honduras',             'region' => 'amerique'],
        'HT' => ['name' => 'Haïti',              'region' => 'amerique'],
        'JM' => ['name' => 'Jamaïque',            'region' => 'amerique'],
        'KN' => ['name' => 'Saint-Kitts-et-Nevis', 'region' => 'amerique'],
        'LC' => ['name' => 'Sainte-Lucie',         'region' => 'amerique'],
        'MX' => ['name' => 'Mexique',              'region' => 'amerique'],
        'NI' => ['name' => 'Nicaragua',            'region' => 'amerique'],
        'PA' => ['name' => 'Panama',               'region' => 'amerique'],
        'PE' => ['name' => 'Pérou',               'region' => 'amerique'],
        'PY' => ['name' => 'Paraguay',             'region' => 'amerique'],
        'SR' => ['name' => 'Suriname',             'region' => 'amerique'],
        'SV' => ['name' => 'Salvador',             'region' => 'amerique'],
        'TT' => ['name' => 'Trinité-et-Tobago',   'region' => 'amerique'],
        'US' => ['name' => 'États-Unis',          'region' => 'amerique'],
        'UY' => ['name' => 'Uruguay',              'region' => 'amerique'],
        'VC' => ['name' => 'Saint-Vincent',        'region' => 'amerique'],
        'VE' => ['name' => 'Venezuela',            'region' => 'amerique'],

        // ── Asie ──
        'AF' => ['name' => 'Afghanistan',          'region' => 'asie'],
        'AM' => ['name' => 'Arménie',             'region' => 'asie'],
        'AZ' => ['name' => 'Azerbaïdjan',         'region' => 'asie'],
        'BD' => ['name' => 'Bangladesh',           'region' => 'asie'],
        'BN' => ['name' => 'Brunei',               'region' => 'asie'],
        'BT' => ['name' => 'Bhoutan',              'region' => 'asie'],
        'CN' => ['name' => 'Chine',               'region' => 'asie'],
        'GE' => ['name' => 'Géorgie',             'region' => 'asie'],
        'HK' => ['name' => 'Hong Kong',            'region' => 'asie'],
        'ID' => ['name' => 'Indonésie',           'region' => 'asie'],
        'IN' => ['name' => 'Inde',                 'region' => 'asie'],
        'JP' => ['name' => 'Japon',               'region' => 'asie'],
        'KG' => ['name' => 'Kirghizistan',         'region' => 'asie'],
        'KH' => ['name' => 'Cambodge',             'region' => 'asie'],
        'KP' => ['name' => 'Corée du Nord',        'region' => 'asie'],
        'KR' => ['name' => 'Corée du Sud',         'region' => 'asie'],
        'KZ' => ['name' => 'Kazakhstan',           'region' => 'asie'],
        'LA' => ['name' => 'Laos',                 'region' => 'asie'],
        'LK' => ['name' => 'Sri Lanka',            'region' => 'asie'],
        'MM' => ['name' => 'Myanmar',              'region' => 'asie'],
        'MN' => ['name' => 'Mongolie',             'region' => 'asie'],
        'MO' => ['name' => 'Macao',               'region' => 'asie'],
        'MV' => ['name' => 'Maldives',             'region' => 'asie'],
        'MY' => ['name' => 'Malaisie',             'region' => 'asie'],
        'NP' => ['name' => 'Népal',               'region' => 'asie'],
        'PH' => ['name' => 'Philippines',          'region' => 'asie'],
        'PK' => ['name' => 'Pakistan',             'region' => 'asie'],
        'SG' => ['name' => 'Singapour',            'region' => 'asie'],
        'TH' => ['name' => 'Thaïlande',           'region' => 'asie'],
        'TJ' => ['name' => 'Tadjikistan',          'region' => 'asie'],
        'TL' => ['name' => 'Timor-Leste',          'region' => 'asie'],
        'TM' => ['name' => 'Turkménistan',         'region' => 'asie'],
        'TW' => ['name' => 'Taïwan',              'region' => 'asie'],
        'UZ' => ['name' => 'Ouzbékistan',          'region' => 'asie'],
        'VN' => ['name' => 'Viêt Nam',            'region' => 'asie'],

        // ── Moyen-Orient ──
        'AE' => ['name' => 'Émirats arabes unis', 'region' => 'moyen_orient'],
        'BH' => ['name' => 'Bahreïn',             'region' => 'moyen_orient'],
        'IL' => ['name' => 'Israël',              'region' => 'moyen_orient'],
        'IQ' => ['name' => 'Irak',                'region' => 'moyen_orient'],
        'IR' => ['name' => 'Iran',                 'region' => 'moyen_orient'],
        'JO' => ['name' => 'Jordanie',             'region' => 'moyen_orient'],
        'KW' => ['name' => 'Koweït',              'region' => 'moyen_orient'],
        'LB' => ['name' => 'Liban',               'region' => 'moyen_orient'],
        'OM' => ['name' => 'Oman',                 'region' => 'moyen_orient'],
        'PS' => ['name' => 'Palestine',            'region' => 'moyen_orient'],
        'QA' => ['name' => 'Qatar',               'region' => 'moyen_orient'],
        'SA' => ['name' => 'Arabie saoudite',      'region' => 'moyen_orient'],
        'SY' => ['name' => 'Syrie',               'region' => 'moyen_orient'],
        'TR' => ['name' => 'Turquie',              'region' => 'moyen_orient'],
        'YE' => ['name' => 'Yémen',              'region' => 'moyen_orient'],

        // ── Afrique ──
        'AO' => ['name' => 'Angola',              'region' => 'afrique'],
        'BF' => ['name' => 'Burkina Faso',        'region' => 'afrique'],
        'BJ' => ['name' => 'Bénin',              'region' => 'afrique'],
        'BW' => ['name' => 'Botswana',            'region' => 'afrique'],
        'CD' => ['name' => 'Congo (Rép. dém.)',   'region' => 'afrique'],
        'CF' => ['name' => 'Rép. centrafricaine', 'region' => 'afrique'],
        'CG' => ['name' => 'Congo',               'region' => 'afrique'],
        'CI' => ['name' => "Côte d'Ivoire",       'region' => 'afrique'],
        'CM' => ['name' => 'Cameroun',            'region' => 'afrique'],
        'CV' => ['name' => 'Cap-Vert',            'region' => 'afrique'],
        'DJ' => ['name' => 'Djibouti',            'region' => 'afrique'],
        'DZ' => ['name' => 'Algérie',            'region' => 'afrique'],
        'EG' => ['name' => 'Égypte',             'region' => 'afrique'],
        'ER' => ['name' => 'Érythrée',           'region' => 'afrique'],
        'ET' => ['name' => 'Éthiopie',           'region' => 'afrique'],
        'GA' => ['name' => 'Gabon',               'region' => 'afrique'],
        'GH' => ['name' => 'Ghana',               'region' => 'afrique'],
        'GM' => ['name' => 'Gambie',              'region' => 'afrique'],
        'GN' => ['name' => 'Guinée',             'region' => 'afrique'],
        'GW' => ['name' => 'Guinée-Bissau',       'region' => 'afrique'],
        'KE' => ['name' => 'Kenya',               'region' => 'afrique'],
        'KM' => ['name' => 'Comores',             'region' => 'afrique'],
        'LR' => ['name' => 'Liberia',             'region' => 'afrique'],
        'LS' => ['name' => 'Lesotho',             'region' => 'afrique'],
        'LY' => ['name' => 'Libye',              'region' => 'afrique'],
        'MA' => ['name' => 'Maroc',               'region' => 'afrique'],
        'MG' => ['name' => 'Madagascar',          'region' => 'afrique'],
        'ML' => ['name' => 'Mali',                'region' => 'afrique'],
        'MR' => ['name' => 'Mauritanie',          'region' => 'afrique'],
        'MU' => ['name' => 'Maurice',             'region' => 'afrique'],
        'MZ' => ['name' => 'Mozambique',          'region' => 'afrique'],
        'NA' => ['name' => 'Namibie',             'region' => 'afrique'],
        'NE' => ['name' => 'Niger',               'region' => 'afrique'],
        'NG' => ['name' => 'Nigeria',             'region' => 'afrique'],
        'RW' => ['name' => 'Rwanda',              'region' => 'afrique'],
        'SC' => ['name' => 'Seychelles',          'region' => 'afrique'],
        'SD' => ['name' => 'Soudan',              'region' => 'afrique'],
        'SL' => ['name' => 'Sierra Leone',        'region' => 'afrique'],
        'SN' => ['name' => 'Sénégal',            'region' => 'afrique'],
        'SO' => ['name' => 'Somalie',             'region' => 'afrique'],
        'SS' => ['name' => 'Soudan du Sud',       'region' => 'afrique'],
        'ST' => ['name' => 'Sao Tomé-et-Principe','region' => 'afrique'],
        'SZ' => ['name' => 'Eswatini',            'region' => 'afrique'],
        'TD' => ['name' => 'Tchad',               'region' => 'afrique'],
        'TG' => ['name' => 'Togo',                'region' => 'afrique'],
        'TN' => ['name' => 'Tunisie',             'region' => 'afrique'],
        'TZ' => ['name' => 'Tanzanie',            'region' => 'afrique'],
        'UG' => ['name' => 'Ouganda',             'region' => 'afrique'],
        'ZA' => ['name' => 'Afrique du Sud',      'region' => 'afrique'],
        'ZM' => ['name' => 'Zambie',              'region' => 'afrique'],
        'ZW' => ['name' => 'Zimbabwe',            'region' => 'afrique'],

        // ── Océanie ──
        'AU' => ['name' => 'Australie',           'region' => 'oceanie'],
        'CK' => ['name' => 'Îles Cook',           'region' => 'oceanie'],
        'FJ' => ['name' => 'Fidji',               'region' => 'oceanie'],
        'FM' => ['name' => 'Micronésie',          'region' => 'oceanie'],
        'KI' => ['name' => 'Kiribati',            'region' => 'oceanie'],
        'MH' => ['name' => 'Îles Marshall',       'region' => 'oceanie'],
        'NR' => ['name' => 'Nauru',               'region' => 'oceanie'],
        'NZ' => ['name' => 'Nouvelle-Zélande',    'region' => 'oceanie'],
        'PG' => ['name' => 'Papouasie-Nvl-Guinée','region' => 'oceanie'],
        'PW' => ['name' => 'Palaos',              'region' => 'oceanie'],
        'SB' => ['name' => 'Îles Salomon',        'region' => 'oceanie'],
        'TO' => ['name' => 'Tonga',               'region' => 'oceanie'],
        'TV' => ['name' => 'Tuvalu',              'region' => 'oceanie'],
        'VU' => ['name' => 'Vanuatu',             'region' => 'oceanie'],
        'WS' => ['name' => 'Samoa',               'region' => 'oceanie'],
    ];

    public function __construct(
        private SettingsService $settings,
        private IpSecurityService $ipSecurity,
        private SettingRepository $settingRepo,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('security_access_save', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_security_access_index');
            }

            // Boolean toggles
            foreach (['admin_vpn_block_enabled', 'vpn_block_enabled', 'country_block_enabled'] as $key) {
                $this->settings->set($key, $request->request->has('setting_' . $key) ? '1' : '0');
            }

            // Allowed countries: CSV from JS hidden input
            $rawCountries = $request->request->get('allowed_countries', '');
            $sanitized = $this->sanitizeCountryCsv($rawCountries);
            $this->settings->set('allowed_countries', $sanitized);

            // Trusted IPs: textarea value
            $trustedIps = $request->request->get('trusted_ips', '');
            $this->settings->set('trusted_ips', trim($trustedIps));

            $this->addFlash('success', 'Règles de sécurité et accès géographique enregistrées.');
            return $this->redirectToRoute('admin_security_access_index');
        }

        $currentSettings = [
            'admin_vpn_block_enabled' => $this->settings->getBool('admin_vpn_block_enabled'),
            'vpn_block_enabled'       => $this->settings->getBool('vpn_block_enabled'),
            'country_block_enabled'   => $this->settings->getBool('country_block_enabled'),
            'allowed_countries'       => $this->settings->get('allowed_countries', ''),
            'trusted_ips'             => $this->settings->get('trusted_ips', ''),
            'ipqs_api_key'            => $this->settings->get('ipqs_api_key', ''),
        ];

        // Parse selected country codes
        $selectedCodes = array_filter(array_map(
            'trim',
            explode(',', $currentSettings['allowed_countries'] ?? '')
        ));

        // Group countries by region
        $countriesByRegion = [];
        foreach (self::COUNTRIES as $code => $info) {
            $countriesByRegion[$info['region']][] = [
                'code'   => $code,
                'name'   => $info['name'],
                'region' => $info['region'],
            ];
        }

        return $this->render('admin/security_access/index.html.twig', [
            'current'            => $currentSettings,
            'selected_codes'     => array_values($selectedCodes),
            'countries_by_region'=> $countriesByRegion,
            'region_labels'      => self::REGION_LABELS,
            'total_countries'    => count(self::COUNTRIES),
        ]);
    }

    #[Route('/diagnostic', name: 'diagnostic', methods: ['GET'])]
    public function diagnostic(Request $request): JsonResponse
    {
        $ip = trim($request->query->get('ip', ''));

        if ($ip === '') {
            return new JsonResponse(['error' => 'IP manquante'], 400);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return new JsonResponse(['error' => 'Adresse IP invalide'], 400);
        }

        $raw = $this->ipSecurity->checkIp($ip);

        return new JsonResponse([
            'ip'              => $ip,
            'trusted'         => $this->ipSecurity->isTrustedIp($ip),
            'vpn_or_proxy'    => $this->ipSecurity->isVpnOrProxy($ip),
            'country_allowed' => $this->ipSecurity->isCountryAllowed($ip),
            'country_code'    => $this->ipSecurity->getCountryCode($ip),
            'raw'             => $raw,
        ]);
    }

    /**
     * Sanitizes a comma-separated string of country codes.
     * Only keeps valid 2-letter uppercase codes present in COUNTRIES.
     */
    private function sanitizeCountryCsv(string $raw): string
    {
        $codes = array_filter(
            array_map(
                fn(string $c) => strtoupper(trim($c)),
                explode(',', $raw)
            ),
            fn(string $c) => isset(self::COUNTRIES[$c])
        );

        return implode(',', array_values($codes));
    }
}
