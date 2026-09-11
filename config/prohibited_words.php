<?php

/**
 * Banned swear / abusive terms for Community Feed posts and comments.
 *
 * Filipino list: npm package `filipino-badwords-list`
 *
 * @see https://github.com/jromest/filipino-badwords-list
 *
 * Matching is case-insensitive and resists common bypasses (spaces, punctuation, leetspeak).
 */
$fromPackage = [];

$packageJs = base_path('node_modules/filipino-badwords-list/lib/array.js');
$packageJson = config_path('data/filipino-badwords-list.json');

if (is_file($packageJs)) {
    $raw = (string) file_get_contents($packageJs);
    if (preg_match('/module\.exports\s*=\s*(\[[\s\S]*\])/', $raw, $matches) === 1) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            $fromPackage = $decoded;
        }
    }
}

if ($fromPackage === [] && is_file($packageJson)) {
    $decoded = json_decode((string) file_get_contents($packageJson), true);
    if (is_array($decoded)) {
        $fromPackage = $decoded;
    }
}

$extra = [
    'fuck',
    'fucker',
    'fucking',
    'fucked',
    'fuckin',
    'motherfucker',
    'motherfuck',
    'shit',
    'shitty',
    'bullshit',
    'bitch',
    'bitches',
    'bastard',
    'asshole',
    'ass',
    'dumbass',
    'jackass',
    'dick',
    'dickhead',
    'cunt',
    'whore',
    'slut',
    'faggot',
    'nigger',
    'nigga',
    'retard',
    'retarded',
    'stfu',
    'wtf',
    'lmfao',
    'putang-ina',
    'tang ina',
    'tang-ina',
    'gagong',
    'tangang',
    'bobong',
    'tarantada',
    'puneta',
    'pakyo',
    'lecheng',
    'hayop',
    'animal',
    'demonyo',
    'buwisit',
    'buwiset',
    'shuta',
    'shuta ka',
    'shett',
    'pota',
    'potangina',
    'ptngina',
    'tnagina',
    'tangnamo',
    'tangina mo',
    'tangina mo rin',
    'peste',
    'pesteng',
    'leche ka',
    'leche mo',
    'tngina',
    'tngnina',
    'tngamo',
    'tngnamo',
    'ptngna',
    'ptangina',
    'p.uta',
    'p.uta.ngina',
    'p*tangina',
    'p*ta',
    't*ngina',
    'g*go',
    'b*bo',
    't*nga',
    'k*pal',
    'p*kyu',
];

$merged = [];
foreach (array_merge($fromPackage, $extra) as $word) {
    if (! is_string($word)) {
        continue;
    }
    $word = trim($word);
    if ($word === '') {
        continue;
    }
    $key = mb_strtolower($word, 'UTF-8');
    $merged[$key] = $key;
}

return array_values($merged);
