<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

final class OpaqueUrlId
{
    public static function encode(int|string $id): string
    {
        return rtrim(strtr(Crypt::encryptString((string) $id), '+/', '-_'), '=');
    }

    public static function decode(string $token): int
    {
        $ciphertext = strtr($token, '-_', '+/');
        $ciphertext .= str_repeat('=', (4 - strlen($ciphertext) % 4) % 4);

        try {
            $id = Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            abort(404);
        }

        abort_unless(ctype_digit($id) && (int) $id > 0, 404);

        return (int) $id;
    }
}
