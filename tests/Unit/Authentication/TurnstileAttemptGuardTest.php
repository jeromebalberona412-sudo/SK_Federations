<?php

namespace Tests\Unit\Authentication;

use App\Modules\Authentication\Services\TurnstileAttemptGuard;
use App\Modules\Authentication\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class TurnstileAttemptGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initial_request_requires_turnstile(): void
    {
        Config::set('services.turnstile.failed_attempts', 3);

        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(true);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest();

        $this->assertTrue($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
    }

    public function test_successful_verification_clears_requirement_until_threshold(): void
    {
        Config::set('services.turnstile.failed_attempts', 3);
        Cache::flush();

        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(true);
        $turnstile->shouldReceive('verify')->once()->with('fresh-token', '127.0.0.1')->andReturn(true);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest(['cf-turnstile-response' => 'fresh-token']);

        $this->assertNull($guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertFalse($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));

        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $this->assertFalse($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));

        $third = $guard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
        $this->assertTrue($third['threshold_reached']);
        $this->assertTrue($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertNotNull($third['message']);
    }

    public function test_disabled_turnstile_never_required(): void
    {
        $turnstile = Mockery::mock(TurnstileService::class);
        $turnstile->shouldReceive('isEnabled')->andReturn(false);

        $guard = new TurnstileAttemptGuard($turnstile);
        $request = $this->makeRequest();

        $this->assertFalse($guard->isRequired(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
        $this->assertNull($guard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function makeRequest(array $input = []): Request
    {
        $request = Request::create('/login', 'POST', $input);
        $request->setLaravelSession($this->app['session']->driver());
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        return $request;
    }
}
