<?php

namespace Tests\Unit;

use App\Models\InvalidEmailAddress;
use App\Services\InvalidEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvalidEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvalidEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvalidEmailService::class);
    }

    public function test_normalize_trims_and_lowercases(): void
    {
        $this->assertSame('fakeemail@gmail.com', $this->service->normalize(' FakeEmail@Gmail.COM '));
    }

    public function test_first_failure_sets_24h_cooldown(): void
    {
        $record = $this->service->recordPermanentFailure('fake@example.com', 'address_not_found');

        $this->assertNotNull($record);
        $this->assertSame(InvalidEmailAddress::STATUS_TEMPORARILY_INVALID, $record->status);
        $this->assertSame(1, $record->failure_count);
        $this->assertTrue($record->retry_after?->greaterThan(now()->addHours(23)));
        $this->assertTrue($record->retry_after?->lessThan(now()->addHours(25)));
        $this->assertFalse($this->service->checkBeforeSending('fake@example.com')['allowed']);
    }

    public function test_progressive_cooldowns(): void
    {
        $this->service->recordPermanentFailure('prog@example.com');
        $this->travel(25)->hours();
        $second = $this->service->recordPermanentFailure('prog@example.com');
        $this->assertSame(2, $second?->failure_count);
        $this->assertTrue($second?->retry_after?->greaterThan(now()->addHours(71)));

        $this->travel(73)->hours();
        $third = $this->service->recordPermanentFailure('prog@example.com');
        $this->assertSame(3, $third?->failure_count);
        $this->assertTrue($third?->retry_after?->greaterThan(now()->addHours(167)));

        $this->travel(169)->hours();
        $fourth = $this->service->recordPermanentFailure('prog@example.com');
        $this->assertSame(4, $fourth?->failure_count);
        $this->assertTrue($fourth?->retry_after?->greaterThan(now()->addHours(719)));
    }

    public function test_allows_retry_after_cooldown_expires(): void
    {
        $this->service->recordPermanentFailure('retry@example.com');
        $this->assertFalse($this->service->checkBeforeSending('retry@example.com')['allowed']);

        $this->travel(25)->hours();
        $this->assertTrue($this->service->checkBeforeSending('retry@example.com')['allowed']);
    }

    public function test_successful_verification_resets_invalid_status(): void
    {
        $this->service->recordPermanentFailure('ok@example.com');
        $this->service->markVerified('ok@example.com');

        $record = $this->service->find('ok@example.com');
        $this->assertSame(InvalidEmailAddress::STATUS_VERIFIED, $record?->status);
        $this->assertSame(0, $record?->failure_count);
        $this->assertNull($record?->retry_after);
        $this->assertTrue($this->service->checkBeforeSending('ok@example.com')['allowed']);
    }

    public function test_permanent_block_never_auto_retries(): void
    {
        $this->service->permanentlyBlock('blocked@example.com', 'other');
        $this->travel(40)->days();

        $result = $this->service->checkBeforeSending('blocked@example.com');
        $this->assertFalse($result['allowed']);
        $this->assertSame(InvalidEmailAddress::STATUS_PERMANENTLY_BLOCKED, $result['status']);
    }

    public function test_admin_reset_allows_immediate_use(): void
    {
        $this->service->recordPermanentFailure('reset@example.com');
        $this->service->resetToActive('reset@example.com');

        $this->assertTrue($this->service->checkBeforeSending('reset@example.com')['allowed']);
    }
}
