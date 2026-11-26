<?php

use Fagus\Engine;

it('confirms the pdf engine can render on this machine', function () {
    $this->artisan('pdf:check')
        ->expectsOutputToContain('PDF engine works (Fagus')
        ->assertSuccessful();
});

it('fails when the pdf engine binary cannot be started', function () {
    config(['fagus.binary' => '/nonexistent/fagus']);
    app()->forgetInstance('fagus');
    app()->forgetInstance(Engine::class);
    Fagus\Laravel\Facades\Fagus::clearResolvedInstances();

    $this->artisan('pdf:check')
        ->expectsOutputToContain('could not be started')
        ->assertFailed();
});
