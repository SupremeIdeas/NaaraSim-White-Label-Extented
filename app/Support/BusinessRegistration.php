<?php

namespace App\Support;

/**
 * Data-driven, global business-registration catalogue (BUILD-4 §2.1).
 *
 * Replaces the old hardcoded 4-country / 2-registration-type merchant KYB form
 * (Nigeria/Ghana/Kenya/South Africa + CAC/TIN) with a full ISO country list and
 * sensible per-country business-registration-identifier defaults. Countries
 * without a specific mapping fall back to a generic set (a business registration
 * number + a tax ID), so the picker works worldwide without inventing a
 * country-specific scheme it can't back up.
 *
 * The identifiers here are the *type of number a business provides* (e.g. the
 * UK's Companies House CRN, the US EIN). Which identity/KYB provider actually
 * verifies them is routed separately, by country, through KycProviderInterface
 * (§2.2) — mirroring how PayoutAccountService routes bank resolvers by country.
 */
class BusinessRegistration
{
    /** Generic fallback for any country without a specific mapping below. */
    public const DEFAULT_TYPES = [
        ['code' => 'BRN', 'label' => 'Business registration number'],
        ['code' => 'TIN', 'label' => 'Tax ID (TIN)'],
    ];

    /**
     * Per-country business-registration identifier types (ISO2 => list).
     * Curated for the platform's key markets; everything else uses DEFAULT_TYPES.
     *
     * @var array<string, list<array{code: string, label: string}>>
     */
    public const TYPES = [
        // Africa
        'NG' => [['code' => 'CAC', 'label' => 'CAC / RC number'], ['code' => 'TIN', 'label' => 'Tax ID (TIN)']],
        'GH' => [['code' => 'RGD', 'label' => 'Registrar-General (RGD) number'], ['code' => 'TIN', 'label' => 'TIN']],
        'KE' => [['code' => 'BRS', 'label' => 'Business Registration (BRS) number'], ['code' => 'KRA', 'label' => 'KRA PIN']],
        'ZA' => [['code' => 'CIPC', 'label' => 'CIPC registration number'], ['code' => 'TAX', 'label' => 'Tax reference number']],
        'EG' => [['code' => 'CR', 'label' => 'Commercial registration number'], ['code' => 'TIN', 'label' => 'Tax ID']],
        'TZ' => [['code' => 'BRELA', 'label' => 'BRELA registration number'], ['code' => 'TIN', 'label' => 'TIN']],
        'UG' => [['code' => 'URSB', 'label' => 'URSB registration number'], ['code' => 'TIN', 'label' => 'TIN']],
        'RW' => [['code' => 'RDB', 'label' => 'RDB registration number'], ['code' => 'TIN', 'label' => 'TIN']],
        // Americas
        'US' => [['code' => 'EIN', 'label' => 'EIN (Employer ID Number)'], ['code' => 'STATE', 'label' => 'State registration number']],
        'CA' => [['code' => 'BN', 'label' => 'Business Number (BN)'], ['code' => 'CORP', 'label' => 'Corporation number']],
        'BR' => [['code' => 'CNPJ', 'label' => 'CNPJ'], ['code' => 'TIN', 'label' => 'Tax ID']],
        'MX' => [['code' => 'RFC', 'label' => 'RFC'], ['code' => 'TIN', 'label' => 'Tax ID']],
        // Europe
        'GB' => [['code' => 'CRN', 'label' => 'Companies House number (CRN)'], ['code' => 'UTR', 'label' => 'UTR (tax)'], ['code' => 'VAT', 'label' => 'VAT number']],
        'IE' => [['code' => 'CRO', 'label' => 'CRO number'], ['code' => 'VAT', 'label' => 'VAT number']],
        'DE' => [['code' => 'HRB', 'label' => 'Handelsregister (HRB) number'], ['code' => 'VAT', 'label' => 'USt-IdNr (VAT)']],
        'FR' => [['code' => 'SIREN', 'label' => 'SIREN / SIRET'], ['code' => 'VAT', 'label' => 'VAT number']],
        'ES' => [['code' => 'CIF', 'label' => 'CIF / NIF'], ['code' => 'VAT', 'label' => 'VAT number']],
        'IT' => [['code' => 'PIVA', 'label' => 'Partita IVA'], ['code' => 'CF', 'label' => 'Codice Fiscale']],
        'NL' => [['code' => 'KVK', 'label' => 'KvK number'], ['code' => 'VAT', 'label' => 'VAT number']],
        // Middle East & Asia-Pacific
        'AE' => [['code' => 'TRN', 'label' => 'Trade licence number'], ['code' => 'TAX', 'label' => 'Tax registration (TRN)']],
        'SA' => [['code' => 'CR', 'label' => 'Commercial registration (CR)'], ['code' => 'VAT', 'label' => 'VAT number']],
        'IN' => [['code' => 'CIN', 'label' => 'CIN (company)'], ['code' => 'GSTIN', 'label' => 'GSTIN'], ['code' => 'PAN', 'label' => 'PAN']],
        'SG' => [['code' => 'UEN', 'label' => 'UEN'], ['code' => 'GST', 'label' => 'GST number']],
        'AU' => [['code' => 'ABN', 'label' => 'ABN'], ['code' => 'ACN', 'label' => 'ACN']],
        'NZ' => [['code' => 'NZBN', 'label' => 'NZBN'], ['code' => 'GST', 'label' => 'GST number']],
    ];

    /**
     * Canonical ISO-3166 alpha-2 list for the country picker. Names are resolved
     * for display via CountryNames (intl), so this stays a bare code list.
     *
     * @var list<string>
     */
    public const ISO = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AR', 'AT', 'AU', 'AW', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BN', 'BO', 'BR', 'BS', 'BT', 'BW', 'BY', 'BZ',
        'CA', 'CD', 'CG', 'CH', 'CI', 'CL', 'CM', 'CN', 'CO', 'CR', 'CV', 'CY', 'CZ',
        'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ',
        'EC', 'EE', 'EG', 'ER', 'ES', 'ET',
        'FI', 'FJ', 'FR',
        'GA', 'GB', 'GD', 'GE', 'GH', 'GM', 'GN', 'GQ', 'GR', 'GT', 'GW', 'GY',
        'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IN', 'IQ', 'IS', 'IT',
        'JM', 'JO', 'JP',
        'KE', 'KG', 'KH', 'KM', 'KN', 'KR', 'KW', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY',
        'MA', 'MC', 'MD', 'ME', 'MG', 'MK', 'ML', 'MM', 'MN', 'MR', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
        'NA', 'NE', 'NG', 'NI', 'NL', 'NO', 'NP', 'NZ',
        'OM',
        'PA', 'PE', 'PG', 'PH', 'PK', 'PL', 'PT', 'PY',
        'QA',
        'RO', 'RS', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SI', 'SK', 'SL', 'SN', 'SO', 'SR', 'SS', 'SV', 'SZ',
        'TD', 'TG', 'TH', 'TJ', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TW', 'TZ',
        'UA', 'UG', 'US', 'UY', 'UZ',
        'VC', 'VE', 'VN', 'VU',
        'WS',
        'YE',
        'ZA', 'ZM', 'ZW',
    ];

    /**
     * ISO2 => display name, sorted by name — the merchant KYB country picker.
     *
     * @return array<string, string>
     */
    public static function countries(): array
    {
        $rows = [];
        foreach (self::ISO as $code) {
            $rows[$code] = CountryNames::name($code) ?: $code;
        }
        asort($rows, SORT_NATURAL | SORT_FLAG_CASE);

        return $rows;
    }

    /**
     * Registration-type options for a country (its specific set, or the generic
     * fallback). Never empty.
     *
     * @return list<array{code: string, label: string}>
     */
    public static function typesFor(?string $iso): array
    {
        $iso = strtoupper(trim((string) $iso));

        return self::TYPES[$iso] ?? self::DEFAULT_TYPES;
    }

    /** Whether a registration-type code is valid for a country (defensive). */
    public static function isValidType(?string $iso, ?string $code): bool
    {
        $codes = array_column(self::typesFor($iso), 'code');

        return in_array(strtoupper(trim((string) $code)), $codes, true);
    }
}
