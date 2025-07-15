<?php

namespace App\Providers;

use App\Policies\UnidadOrganizPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Http\Controllers\Auth\PepaUserProvider;
use Illuminate\Support\Facades\Auth;
use App\UnidadesOrganiz;
//use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        //UnidadesOrganiz::class => UnidadOrganizPolicy::class,
        //'App\UnidadesOrganiz' => UnidadOrganizPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        $this->registerCustomPolicies();

        //
        Auth::provider('pepaauth', function ($app, array $config) {
            // Return an instance of Illuminate\Contracts\Auth\UserProvider...
            return new PepaUserProvider();
        });
    }

    public function registerCustomPolicies()
    {
      /*
        Gate::define('store-ou', function ($user) {
            return isset($user['obj_permisos']['store-ou']);
        });
     *
     */
    }

}
