<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Product;
use App\Services\Marketing\MakeWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a product or blog event to Make.com off the request lifecycle, so the
 * webhook HTTP call never blocks an admin save. With QUEUE_CONNECTION=database
 * this is processed by the queue worker; with QUEUE_CONNECTION=sync it runs
 * inline (useful for local testing).
 */
class DispatchMakeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Single attempt — failures are logged and retried manually from the dashboard. */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public Model $model,
        public string $event,
    ) {}

    public function handle(MakeWebhookService $make): void
    {
        if ($this->model instanceof Product) {
            $make->dispatchProduct($this->model, $this->event);

            return;
        }

        if ($this->model instanceof Article) {
            $make->dispatchArticle($this->model, $this->event);
        }
    }
}
