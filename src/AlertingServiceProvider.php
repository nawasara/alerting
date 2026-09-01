<?php

namespace Nawasara\Alerting;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Nawasara\Alerting\Jobs\EscalateStaleAlertsJob;
use Nawasara\Alerting\Listeners\SyncJobFinalFailedListener;
use Nawasara\Alerting\Listeners\SyncJobSucceededListener;
use Nawasara\Alerting\Services\AlerterImpl;
use Nawasara\Alerting\Services\AlertRuleRegistry;
use Nawasara\Sync\Events\SyncJobFinalFailed;
use Nawasara\Sync\Events\SyncJobSucceeded;
use Symfony\Component\Finder\Finder;

class AlertingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-alerting.php', 'nawasara-alerting');

        $this->app->singleton(AlertRuleRegistry::class);
        $this->app->singleton(AlerterImpl::class);
        // Facade accessor 'alerter' → real binding. Order matters: alias
        // the SHORT name to the FQCN, so resolving 'alerter' looks up the
        // class binding — not the other way around (that's an infinite
        // resolution loop and OOMs the container).
        $this->app->alias(AlerterImpl::class, 'alerter');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-alerting');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Anonymous component path — guard with is_dir() per
        // reference_view_cache_missing_path_crash memory: registering a
        // path that doesn't exist crashes view:cache during deploy.
        if (is_dir(__DIR__.'/../resources/views/components')) {
            Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'nawasara-alerting');
        }

        $this->registerLivewire();
        $this->registerListeners();
        $this->registerSchedule();
    }

    /**
     * Auto-discover Livewire components in src/Livewire and alias them as
     * nawasara-alerting.{path.to.class} so routes/blade can reference them
     * without manually listing each one.
     */
    protected function registerLivewire(): void
    {
        $base = __DIR__.'/Livewire';
        if (! is_dir($base)) {
            return;
        }

        $finder = Finder::create()->files()->in($base)->name('*.php');

        foreach ($finder as $file) {
            $rel = str_replace([$base.DIRECTORY_SEPARATOR, '.php'], ['', ''], $file->getRealPath());
            $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
            $alias = 'nawasara-alerting.'.strtolower(str_replace(['\\', '/'], '.', $rel));

            $class = "Nawasara\\Alerting\\Livewire\\{$classPath}";
            if (class_exists($class)) {
                Livewire::component($alias, $class);
            }
        }
    }

    protected function registerListeners(): void
    {
        if (! config('nawasara-alerting.sync_failure.enabled', true)) {
            return;
        }

        Event::listen(SyncJobFinalFailed::class, SyncJobFinalFailedListener::class);

        // Pasangannya: memulihkan alert saat sync berhasil kembali. Tanpa ini
        // alert kegagalan sync menyala selamanya, karena tidak ada satu pun
        // jalur lain yang menutupnya.
        Event::listen(SyncJobSucceeded::class, SyncJobSucceededListener::class);
    }

    /**
     * Schedule EscalateStaleAlertsJob via $schedule->call() per the project
     * rule (see reference_schedule_call_workaround memory): packages
     * registering console commands don't always surface in the Artisan
     * kernel reliably, so $schedule->command() can fail. Calling dispatch
     * from the closure works in every case.
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }
            if (! config('nawasara-alerting.scheduler.enabled', true)) {
                return;
            }
            if (! config('nawasara-alerting.escalation.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $interval = max(1, (int) config('nawasara-alerting.escalation.scan_interval_minutes', 15));

            $schedule->call(fn () => EscalateStaleAlertsJob::dispatch())
                ->name('nawasara-alerting:escalate-stale')
                ->cron("*/{$interval} * * * *")
                ->withoutOverlapping(10);
        });
    }
}
