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

    /**
     * @return array{open: bool, label: string, message: string, hours: string, phone: string, phone_tel: string, email: string, contact_url: string}
     */
    public function snapshot(?CarbonInterface $moment = null): array
    {
        $open = $this->isOpen($moment);
        $hours = (string) config('business.hours');

        return [
            'open' => $open,
            'label' => $open ? 'Hours open' : 'Offline',
            'message' => $open
                ? 'Our team can reply during published hours ('.$hours.', South Africa). A person reads this inbox. The product assistant is not a member of staff.'
                : 'Our team is offline. Published hours are '.$hours.' (South Africa). Leave a message and we will read it when we are back, or contact us directly.',
            'hours' => $hours,
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
