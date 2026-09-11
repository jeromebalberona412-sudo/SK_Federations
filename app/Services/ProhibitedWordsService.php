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
     * Collapse bypass tricks: case, leetspeak, spaces/punctuation, repeated letters.
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

        $text = preg_replace('/[^\p{L}]+/u', '', $text) ?? '';

        $text = preg_replace('/(.)\1+/u', '$1', $text) ?? '';

        return $text;
    }

    /**
     * @return list<string> Display labels of matched prohibited words (longest-first, no nested duplicates).
     */
    public function matches(string $text): array
    {
        $haystack = $this->normalize($text);
        if ($haystack === '') {
            return [];
        }

        $hits = [];
        foreach ($this->normalizedWordList() as $entry) {
            $word = $entry['normalized'];
            if ($word !== '' && str_contains($haystack, $word)) {
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
     * @return list<array{normalized: string, label: string}>
     */
    private function normalizedWordList(): array
    {
        if ($this->normalizedWords !== null) {
            return $this->normalizedWords;
        }

        $seen = [];
        $out = [];
        foreach ($this->words() as $word) {
            $normalized = $this->normalize($word);
            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }

            $label = trim(mb_strtolower($word, 'UTF-8'));
            if (isset($seen[$normalized])) {
                $index = $seen[$normalized];
                $existing = $out[$index]['label'];
                if (str_contains($existing, ' ') && ! str_contains($label, ' ')) {
                    $out[$index]['label'] = $label;
                }

                continue;
            }

            $seen[$normalized] = count($out);
            $out[] = [
                'normalized' => $normalized,
                'label' => $label,
            ];
        }

        return $this->normalizedWords = $out;
    }
}
