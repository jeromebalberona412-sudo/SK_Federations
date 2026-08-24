<?php

namespace App\Console\Commands;

use App\Modules\Shared\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email? : The email address to send test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? config('mail.from.address');

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address provided.');
            return self::FAILURE;
        }

        $this->info("Testing email configuration...");
        $this->info("Sending test email to: {$email}");
        $this->newLine();

        // Display current mail configuration
        $this->info("Mail Configuration:");
        $this->line("  Mailer: " . config('mail.default'));
        $this->line("  Host: " . config('mail.mailers.smtp.host'));
        $this->line("  Port: " . config('mail.mailers.smtp.port'));
        $this->line("  Encryption: " . config('mail.mailers.smtp.encryption'));
        $this->line("  Username: " . config('mail.mailers.smtp.username'));
        $this->line("  From: " . config('mail.from.address'));
        $this->newLine();

        try {
            // Log the attempt
            Log::info('Testing email functionality', [
                'recipient' => $email,
                'mailer' => config('mail.default'),
                'type' => 'test_email',
            ]);

            // Send test email
            Mail::raw(
                "This is a test email from SK Federation Portal.\n\n" .
                "If you received this email, your email configuration is working correctly.\n\n" .
                "Sent at: " . now()->format('Y-m-d H:i:s') . "\n" .
                "Environment: " . config('app.env'),
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('SK Federation Portal - Test Email');
                }
            );

            Log::info('Test email sent successfully', [
                'recipient' => $email,
            ]);

            $this->info("✓ Test email sent successfully!");
            $this->info("Please check the inbox for: {$email}");
            $this->newLine();
            
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Test email failed', [
                'recipient' => $email,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            $this->error("✗ Failed to send test email");
            $this->error("Error: " . $e->getMessage());
            $this->newLine();
            $this->error("Check the log file for more details: storage/logs/laravel.log");
            
            return self::FAILURE;
        }
    }
}
