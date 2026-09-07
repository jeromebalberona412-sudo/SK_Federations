<?php

namespace App\Support;

class ViteManifest
{
    /**
     * Keep only Vite entry paths that exist in the current build manifest.
     *
     * @param  list<string>  $entries
     * @return list<string>
     */
    public static function existing(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        if (is_file(public_path('hot'))) {
            return array_values($entries);
        }

        $manifestPath = public_path('build/manifest.json');
        if (! is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return [];
        }

        return array_values(array_filter(
            $entries,
            static fn (string $entry): bool => isset($manifest[$entry])
        ));
    }
}
