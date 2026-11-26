<?php

namespace App\Providers;

use App\Apis\WhatsApp\WhatsAppMessageApi;
use App\Interfaces\Apis\MessagingProviderInterface;
use App\Interfaces\Repositories\BusinessRepositoryInterface;
use App\Interfaces\Repositories\Conversations\ConversationRepositoryInterface;
use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Interfaces\Repositories\Staff\StaffRepositoryInterface;
use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Policies\ServicePolicy;
use App\Policies\StaffPolicy;
use App\Repositories\Businesses\BusinessRepository;
use App\Repositories\Conversations\ConversationRepository;
use App\Repositories\Customers\CustomerRepository;
use App\Repositories\Reviews\ReviewRepository;
use App\Repositories\Services\ServiceRepository;
use App\Repositories\Staff\StaffRepository;
use App\Repositories\Visits\VisitRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BusinessRepositoryInterface::class, BusinessRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(StaffRepositoryInterface::class, StaffRepository::class);
        $this->app->bind(VisitRepositoryInterface::class, VisitRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, ReviewRepository::class);
        $this->app->bind(MessagingProviderInterface::class, WhatsAppMessageApi::class);
    }

    public function boot(): void
    {
        Gate::policy(StaffProfile::class, StaffPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
    }
}
