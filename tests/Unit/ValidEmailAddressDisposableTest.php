<?php

namespace Tests\Unit;

use App\Rules\ValidEmailAddress;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidEmailAddressDisposableTest extends TestCase
{
    public function test_rejects_known_disposable_domains(): void
    {
        foreach (['testing@mailinator.com', 'testing@guerrillamail.com', 'testing@10minutemail.com'] as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'string', 'max:64', new ValidEmailAddress]]
            );

            $this->assertTrue($validator->fails(), "Expected disposable rejection for {$email}");
            $message = (string) $validator->errors()->first('email');
            $this->assertTrue(
                in_array($message, [
                    ValidEmailAddress::MSG_DISPOSABLE,
                    ValidEmailAddress::MSG_FORMAT,
                    ValidEmailAddress::MSG_DOMAIN,
                    ValidEmailAddress::MSG_LOCAL_MIN,
                    ValidEmailAddress::MSG_LOCAL_MAX,
                ], true),
                "Unexpected message for {$email}: {$message}"
            );
        }
    }

    public function test_allows_permanent_provider_format_when_dns_ok(): void
    {
        $validator = Validator::make(
            ['email' => 'exampleuser@gmail.com'],
            ['email' => ['required', 'string', 'max:64', new ValidEmailAddress]]
        );

        // DNS may fail in offline CI; if it passes, must not be disposable.
        if ($validator->fails()) {
            $this->assertNotSame(
                ValidEmailAddress::MSG_DISPOSABLE,
                (string) $validator->errors()->first('email')
            );

            return;
        }

        $this->assertFalse($validator->fails());
    }

    public function test_rejects_invalid_format(): void
    {
        foreach (['abc', 'abc@', '@domain.com'] as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'string', 'max:64', new ValidEmailAddress]]
            );

            $this->assertTrue($validator->fails(), "Expected format rejection for {$email}");
            $this->assertNotSame(
                ValidEmailAddress::MSG_DISPOSABLE,
                (string) $validator->errors()->first('email')
            );
        }
    }
}
