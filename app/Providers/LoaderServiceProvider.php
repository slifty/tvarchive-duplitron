<?php

namespace Duplitron\Providers;

use Illuminate\Support\ServiceProvider;
use Duplitron\Helpers\BasicLoader;

class LoaderServiceProvider extends ServiceProvider
{

    // Set it so this class will only be loaded when necessary
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('Duplitron\Helpers\Contracts\LoaderContract', function(){
            return new BasicLoader();
        });
    }

     /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['Duplitron\Helpers\Contracts\LoaderContract'];
    }
}
