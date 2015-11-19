<?php

namespace Duplitron\Providers;

use Illuminate\Support\ServiceProvider;
use Duplitron\Helpers\AudfDockerFingerprinter;

class FingerprinterServiceProvider extends ServiceProvider
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
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('Duplitron\Helpers\Contracts\FingerprinterContract', function(){
            return new AudfDockerFingerprinter($this->app['Duplitron\Helpers\Contracts\LoaderContract']);
        });
    }

     /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['Duplitron\Helpers\Contracts\FingerprinterContract'];
    }
}
