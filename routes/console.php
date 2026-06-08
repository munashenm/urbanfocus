<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('news:sync')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('blog:discover-topics')->dailyAt('05:30')->withoutOverlapping();
Schedule::command('blog:sync-search-console')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('social:publish')->hourly()->withoutOverlapping();
