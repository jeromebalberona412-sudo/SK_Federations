<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CheckEmailStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:status {--lines=50 : Number of log lines to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check email sending status and display recent email logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('SK Federation Portal - Email Status Report');
        $this->newLine();

        // Display mail configuration
        $this->displayMailConfiguration();
        $this->newLine();

        // Display email notification classes
        $this->displayNotificationClasses();
        $this->newLine();

        // Display recent email logs
        $lines = (int) $this->option('lines');
        $this->displayRecentEmailLogs($lines);
        $this->newLine();

        return self::SUCCESS;
    }

    protected function displayMailConfiguration(): void
    {
        $this->info('📧 Mail Configuration:');
        $this->line('  ├─ Default Mailer: ' . config('mail.default'));
        $this->line('  ├─ SMTP Host: ' . config('mail.mailers.smtp.host'));
        $this->line('  ├─ SMTP Port: ' . config('mail.mailers.smtp.port'));
        $this->line('  ├─ Encryption: ' . config('mail.mailers.smtp.encryption'));
        $this->line('  ├─ Username: ' . config('mail.mailers.smtp.username'));
        $this->line('  ├─ From Address: ' . config('mail.from.address'));
        $this->line('  └─ From Name: ' . config('mail.from.name'));
    }

    protected function displayNotificationClasses(): void
    {
        $this->info('📬 Email Notification Classes:');
        
        $notifications = [
            '1. Email Verification' => 'Laravel\\Notifications\\Messages\\VerifyEmail (customized in User model)',
            '2. Password Reset' => 'App\\Modules\\Authentication\\Notifications\\SkFedResetPasswordNotification',
            '3. New Location Login' => 'App\\Modules\\Authentication\\Notifications\\NewLocationLoginNotification',
            '4. Turnover Account Setup' => 'App\\Modules\\Turnover\\Notifications\\TurnoverAccountSetupNotification',
            '5. Turnover Forgot Password' => 'App\\Modules\\Turnover\\Notifications\\TurnoverForgotPasswordSetupNotification',
            '6. Turnover Completed' => 'App\\Modules\\Turnover\\Notifications\\TurnoverCompletedNotification',
        ];

        foreach ($notifications as $name => $class) {
            $this->line("  ├─ {$name}");
            $this->line("  │  └─ {$class}");
        }
    }

    protected function displayRecentEmailLogs(int $lines): void
    {
        $this->info("📝 Recent Email Logs (last {$lines} entries):");
        
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            $this->warn('  └─ No log file found at: ' . $logFile);
            return;
        }

        try {
            // Read last N lines from log file
            $logLines = $this->tailFile($logFile, $lines * 5); // Get more lines to ensure we capture email-related logs
            
            // Filter for email-related logs
            $emailLogs = array_filter($logLines, function($line) {
                return strpos($line, 'email') !== false 
                    || strpos($line, 'notification') !== false
                    || strpos($line, 'mail') !== false
                    || strpos($line, 'verification') !== false
                    || strpos($line, 'password') !== false;
            });

            if (empty($emailLogs)) {
                $this->warn('  └─ No email-related logs found in recent entries');
                $this->line('  └─ Try sending a test email with: php artisan email:test');
                return;
            }

            $count = 0;
            foreach ($emailLogs as $log) {
                if ($count >= $lines) {
                    break;
                }
                $this->line('  ' . trim($log));
                $count++;
            }
            
            $this->newLine();
            $this->info("Total email-related log entries: " . count($emailLogs));
            
        } catch (\Throwable $e) {
            $this->error('  └─ Error reading log file: ' . $e->getMessage());
        }
    }

    /**
     * Read last N lines from a file
     *
     * @param string $file
     * @param int $lines
     * @return array
     */
    protected function tailFile(string $file, int $lines): array
    {
        $handle = fopen($file, 'r');
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];

        while ($linecounter > 0) {
            $t = ' ';
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[] = fgets($handle);
            if ($beginning) {
                break;
            }
        }
        fclose($handle);

        return array_reverse($text);
    }
}
