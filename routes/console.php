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

// Weekly AI draft from the top discovered topic — only when OpenAI is configured.
// Generated articles are saved as drafts for review before publishing.
Schedule::command('blog:generate-article')
    ->weeklyOn(1, '07:00')
    ->when(fn () => (bool) config('blog_automation.openai.enabled') && config('blog_automation.openai.api_key'))
    ->withoutOverlapping();
