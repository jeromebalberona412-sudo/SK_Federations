<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ProhibitedWordsService
{
    public const MESSAGE = 'This content contains prohibited language. Please remove swear words and try again.';

    /** @var list<array{normalized: string, label: string}>|null */
    private ?array $normalizedWords = null;

    /**
     * @return list<string>
     */
    public function words(): array
    {
        $words = config('prohibited_words', []);

        return is_array($words) ? array_values(array_filter(array_map('strval', $words))) : [];
    }

    /**
     * Letters-only normalize (default). Digit-containing bans use normalizeAlnum().
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        $leet = [
            '@' => 'a',
            '4' => 'a',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            '3' => 'e',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            '1' => 'i',
            'í' => 'i',
            'ì' => 'i',
            '0' => 'o',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            '5' => 's',
            '$' => 's',
            '7' => 't',
            '+' => 't',
        ];
        $text = strtr($text, $leet);

        // Keep letters only so "p u t a", "p-u-t-a", "p.u.t.a" collapse to "puta".
        $text = preg_replace('/[^\p{L}]+/u', '', $text) ?? '';

        // "fuuuuck" / "gaaago" → single repeated letters.
        $text = preg_replace('/(.)\1+/u', '$1', $text) ?? '';

        return $text;
    }

    /**
     * Keep letters + digits so numeric bans like "8080" still match.
     */
    public function normalizeAlnum(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '', $text) ?? '';
        $text = preg_replace('/(.)\1+/u', '$1', $text) ?? '';

        return $text;
    }

    /**
     * @return list<string> Display labels of matched prohibited words (longest-first, no nested duplicates).
     */
    public function matches(string $text): array
    {
        $haystack = $this->normalize($text);
        $haystackAlnum = $this->normalizeAlnum($text);
        if ($haystack === '' && $haystackAlnum === '') {
            return [];
        }

        $hits = [];
        foreach ($this->normalizedWordList() as $entry) {
            $word = (string) ($entry['normalized'] ?? '');
            if ($word === '') {
                continue;
            }

            $needleHaystack = ! empty($entry['alnum']) ? $haystackAlnum : $haystack;
            if ($needleHaystack !== '' && str_contains($needleHaystack, $word)) {
                $hits[] = $entry;
            }
        }

        if ($hits === []) {
            return [];
        }

        usort($hits, fn (array $a, array $b): int => mb_strlen($b['normalized']) <=> mb_strlen($a['normalized']));

        $kept = [];
        foreach ($hits as $hit) {
            foreach ($kept as $longer) {
                if (str_contains($longer['normalized'], $hit['normalized'])) {
                    continue 2;
                }
            }
            $kept[] = $hit;
        }

        return array_values(array_unique(array_map(fn (array $hit): string => $hit['label'], $kept)));
    }

    public function contains(string $text): bool
    {
        return $this->matches($text) !== [];
    }

    public function messageFor(string $text): string
    {
        $matches = $this->matches($text);
        if ($matches === []) {
            return self::MESSAGE;
        }

        $quoted = array_map(
            static fn (string $word): string => '"'.$word.'"',
            $matches
        );

        return 'Prohibited word(s) found: '.implode(', ', $quoted).'. Please remove or change them and try again.';
    }

    /**
     * @throws ValidationException
     */
    public function assertClean(string $text, string $field = 'body'): void
    {
        $matches = $this->matches($text);
        if ($matches !== []) {
            throw ValidationException::withMessages([
                $field => $this->messageFor($text),
            ]);
        }
    }

    /**
     * @return list<array{normalized: string, label: string, alnum: bool}>
     */
    private function normalizedWordList(): array
    {
        if ($this->normalizedWords !== null) {
            return $this->normalizedWords;
        }

        $seen = [];
        $out = [];
        foreach ($this->words() as $word) {
            $letterNorm = $this->normalize($word);
            $alnumNorm = $this->normalizeAlnum($word);
            $useAlnum = $letterNorm === '' || mb_strlen($letterNorm) < 3;
            $normalized = $useAlnum ? $alnumNorm : $letterNorm;

            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }

            $label = trim(mb_strtolower($word, 'UTF-8'));
            $seenKey = ($useAlnum ? 'a:' : 'l:').$normalized;
            if (isset($seen[$seenKey])) {
                $index = $seen[$seenKey];
                $existing = $out[$index]['label'];
                if (str_contains($existing, ' ') && ! str_contains($label, ' ')) {
                    $out[$index]['label'] = $label;
                }

                continue;
            }

            $seen[$seenKey] = count($out);
            $out[] = [
                'normalized' => $normalized,
                'label' => $label,
                'alnum' => (bool) $useAlnum,
            ];
        }

        return $this->normalizedWords = $out;
    }
}
