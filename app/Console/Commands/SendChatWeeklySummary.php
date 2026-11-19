<?php

namespace App\Console\Commands;

use App\Mail\ChatWeeklySummary;
use App\Models\ChatConversation;
use App\Models\ChatInsight;
use App\Models\ChatMessage;
use App\Models\Quote;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendChatWeeklySummary extends Command
{
    protected $signature = 'chat:weekly-summary';

    protected $description = 'Email the past week of customer chat activity to the business address';

    public function handle(): int
    {
        $email = config('business.email');
        if (! is_string($email) || $email === '') {
            $this->warn('BUSINESS_EMAIL is empty. Weekly chat summary was not sent.');

            return self::SUCCESS;
        }

        $timezone = (string) config('chat.timezone', 'Africa/Johannesburg');
        $until = now()->timezone($timezone);
        $since = $until->copy()->subDays(7);

        $report = $this->report($since, $until);
        Mail::to($email)->send(new ChatWeeklySummary($report));
        $this->info('Weekly chat summary sent to '.$email.'.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(Carbon $since, Carbon $until): array
    {
        $conversations = ChatConversation::query()->where('created_at', '>=', $since)->where('created_at', '<=', $until);
        $topics = (clone $conversations)
            ->selectRaw('topic, COUNT(*) as total')
            ->groupBy('topic')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['topic' => (string) $row->topic, 'total' => (int) $row->total])
            ->all();

        $questions = ChatMessage::query()
            ->where('sender', 'visitor')
            ->where('kind', 'message')
            ->where('created_at', '>=', $since)
            ->where('created_at', '<=', $until)
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('body')
            ->countBy(fn (string $body) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body)))
            ->sortDesc()
            ->take(5);

        return [
            'since' => $since,
            'until' => $until,
            'conversations' => (clone $conversations)->count(),
            'handovers' => ChatMessage::query()
                ->where('kind', 'handover')
                ->where('sender', 'visitor')
                ->where('created_at', '>=', $since)
                ->where('created_at', '<=', $until)
                ->count(),
            'quotes' => Quote::query()
                ->where('created_at', '>=', $since)
                ->where('created_at', '<=', $until)
                ->where('message', 'like', 'Submitted from customer chat%')
                ->count(),
            'topics' => $topics,
            'questions' => $questions->all(),
            'unanswered' => ChatInsight::query()->where('kind', 'unanswered')->where('created_at', '>=', $since)->where('created_at', '<=', $until)->pluck('question')->all(),
            'not_found' => ChatInsight::query()->where('kind', 'not_found')->where('created_at', '>=', $since)->where('created_at', '<=', $until)->pluck('question')->all(),
        ];
    }
}
