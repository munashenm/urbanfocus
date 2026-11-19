<?php

namespace App\Services\Chat;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ChatAvailability
{
    public function isOpen(?CarbonInterface $moment = null): bool
    {
        $now = $this->local($moment);

        if (! in_array($now->dayOfWeekIso, config('chat.open_days', [1, 2, 3, 4, 5]), true)) {
            return false;
        }

        $opens = $now->copy()->setTimeFromTimeString((string) config('chat.opens_at', '08:00'));
        $closes = $now->copy()->setTimeFromTimeString((string) config('chat.closes_at', '17:00'));

        return $now->greaterThanOrEqualTo($opens) && $now->lessThan($closes);
    }

    public function nextOpenAt(?CarbonInterface $moment = null): Carbon
    {
        $now = $this->local($moment);
        $cursor = $now->copy();

        for ($i = 0; $i < 8; $i++) {
            if (in_array($cursor->dayOfWeekIso, config('chat.open_days', [1, 2, 3, 4, 5]), true)) {
                $opens = $cursor->copy()->setTimeFromTimeString((string) config('chat.opens_at', '08:00'));
                if ($now->lt($opens)) {
                    return $opens;
                }
            }

            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        return $now->copy()->next(Carbon::MONDAY)->setTimeFromTimeString((string) config('chat.opens_at', '08:00'));
    }

    public function nextOpenLabel(?CarbonInterface $moment = null): string
    {
        return $this->nextOpenAt($moment)->format('l H:i');
    }

    /**
     * @return array{open: bool, label: string, message: string, hours: string, closes: string, next_open: string, phone: string, phone_tel: string, email: string, contact_url: string}
     */
    public function snapshot(?CarbonInterface $moment = null): array
    {
        $open = $this->isOpen($moment);
        $hours = (string) config('business.hours');
        $closes = (string) config('chat.closes_at', '17:00');
        $nextOpen = $this->nextOpenLabel($moment);

        return [
            'open' => $open,
            'label' => $open ? 'Open' : 'Offline',
            'message' => $open ? 'Open until '.$closes : 'Back '.$nextOpen,
            'hours' => $hours,
            'closes' => $closes,
            'next_open' => $nextOpen,
            'phone' => (string) config('business.phone'),
            'phone_tel' => (string) config('business.phone_tel'),
            'email' => (string) config('business.email'),
            'contact_url' => route('contact'),
        ];
    }

    protected function local(?CarbonInterface $moment): Carbon
    {
        $timezone = (string) config('chat.timezone', 'Africa/Johannesburg');

        return ($moment ? Carbon::parse($moment) : now())->timezone($timezone);
    }
}
