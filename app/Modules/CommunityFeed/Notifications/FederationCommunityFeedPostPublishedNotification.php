<?php

namespace App\Modules\CommunityFeed\Notifications;

use App\Modules\Shared\Models\Announcement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FederationCommunityFeedPostPublishedNotification extends Notification
{
    public function __construct(
        public readonly Announcement $post,
        public readonly string $recipientName,
        public readonly string $postUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = trim((string) ($this->post->title ?? ''));
        $preview = Str::limit(trim(strip_tags((string) $this->post->body)), 220, '…');
        $postedAt = optional($this->post->created_at)
            ?->timezone(config('app.timezone', 'Asia/Manila'))
            ->format('M j, Y g:i A');

        $message = (new MailMessage)
            ->subject('New Community Feed post from SK Federation')
            ->greeting('Hello, '.$this->recipientName.'!')
            ->line('Community Feed notification')
            ->line('SK Federation published a new Community Feed post.')
            ->line('Post type: '.ucfirst((string) $this->post->type));

        if ($title !== '') {
            $message->line('Title: '.$title);
        }

        if ($preview !== '') {
            $message->line('Preview: '.$preview);
        }

        if ($postedAt) {
            $message->line('Posted: '.$postedAt);
        }

        return $message
            ->action('Open Community Feed post', $this->postUrl)
            ->line('You are receiving this because you have an active SK OnePortal account.');
    }
}
