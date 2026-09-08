<?php

namespace App\Modules\Communications\Providers;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Policies\ConversationPolicy;
use App\Modules\Communications\Services\ConversationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class CommunicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../../config/communications.php', 'communications');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'communications');

        Route::middleware('web')
            ->group(__DIR__.'/../Routes/communications.php');

        Gate::policy(Conversation::class, ConversationPolicy::class);

        View::composer('layout::header', function ($view): void {
            $user = Auth::user();
            $count = 0;
            $headerConversations = [];
            if ($user) {
                try {
                    $service = app(ConversationService::class);
                    $count = $service->unreadTotalForUser($user);
                    $headerConversations = $service->headerInboxForUser($user)->values()->all();
                } catch (\Throwable) {
                    $count = 0;
                    $headerConversations = [];
                }
            }

            $view->with([
                'unreadMessagesCount' => $count,
                'headerConversations' => $headerConversations,
            ]);
        });
    }
}
