<?php

namespace App\Modules\KabataanMonitoring\Support;

class KabataanQuestionnairePrintPresenter
{
    /**
     * Map a monitoring form payload to the Officials KK print questionnaire vars.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function fromForm(array $form, ?string $barangayLogoUrl = null): array
    {
        $read = static function (string $key, string $fallback = '') use ($form): string {
            $value = $form[$key] ?? null;
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            $value = trim((string) $value);

            if ($value === '' || $value === '—') {
                return $fallback;
            }

            return $value;
        };

        $isChecked = static function (string $field, string $option) use ($read): bool {
            $keys = [$field];
            if ($field === 'education') {
                $keys[] = 'educational_background';
            }

            $optionNorm = strtolower(trim($option));
            $aliases = [
                'there was no kk assembly' => ['there was no kk assembly meeting'],
                'there was no kk assembly meeting' => ['there was no kk assembly'],
                'not interested to attend' => ['not interested to attend'],
                'high school level' => ['high school level'],
                'high school grad' => ['high school grad'],
                'in school youth' => ['in school youth'],
                'youth w/ specific needs' => ['youth with specific needs', 'youth w/ specific needs'],
                'children in conflict w/ law' => ['children in conflict w/ law'],
            ];

            foreach ($keys as $key) {
                $stored = strtolower(trim($read($key)));
                if ($stored === '') {
                    continue;
                }
                if ($stored === $optionNorm) {
                    return true;
                }
                foreach ($aliases[$optionNorm] ?? [] as $alias) {
                    if ($stored === $alias) {
                        return true;
                    }
                }
            }

            return false;
        };

        $suffix = trim($read('suffix'));
        $customSuffix = $read('custom_suffix') ?: $read('suffix_other');
        if ($suffix === '' || strcasecmp($suffix, 'none') === 0 || $suffix === '—' || $suffix === '-') {
            $suffix = '';
        } elseif (strcasecmp($suffix, 'other') === 0 || strcasecmp($suffix, 'others') === 0) {
            $suffix = $customSuffix;
        }

        $signature = $form['signature'] ?? null;
        $hasSignatureImage = is_string($signature) && (
            str_starts_with($signature, 'data:image')
            || str_starts_with($signature, 'http://')
            || str_starts_with($signature, 'https://')
        );

        $nameParts = array_filter([
            $read('first_name'),
            $read('middle_name'),
            $read('last_name'),
            $suffix !== '' ? $suffix : null,
        ], fn ($part) => filled($part));

        return [
            'respondentNumber' => $read('respondent_number', '—'),
            'date' => $read('date'),
            'barangayLogoUrl' => $barangayLogoUrl,
            'lastName' => strtoupper($read('last_name')),
            'firstName' => strtoupper($read('first_name')),
            'middleName' => strtoupper($read('middle_name')),
            'suffix' => $suffix !== '' ? $suffix : 'None',
            'region' => $read('region', 'Region IV-A (CALABARZON)'),
            'province' => $read('province', 'Laguna'),
            'city' => $read('city', 'Santa Cruz'),
            'barangay' => $read('barangay'),
            'purokZone' => $read('purok_zone'),
            'age' => $read('age'),
            'birthday' => self::formatBirthday($read('birthday')),
            'email' => $read('email'),
            'contactNumber' => $read('contact_number'),
            'facebook' => $read('facebook_profile_url') ?: $read('facebook'),
            'signatureImage' => $hasSignatureImage ? $signature : null,
            'signatureName' => $read('signature_name') !== ''
                ? $read('signature_name')
                : implode(' ', $nameParts),
            'isChecked' => $isChecked,
        ];
    }

    private static function formatBirthday(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return $matches[2].'/'.$matches[3].'/'.$matches[1];
        }

        return $value;
    }
}
