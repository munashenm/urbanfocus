<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'company');

        return view('admin.settings.index', [
            'tab' => $tab,
            'settings' => $this->loadSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tab = $request->get('tab', 'company');

        $rules = match ($tab) {
            'company' => [
                'company_name' => 'required|string|max:150',
                'company_website' => 'nullable|url|max:255',
                'company_email' => 'required|email|max:255',
                'company_phone' => 'required|string|max:30',
                'company_hours' => 'nullable|string|max:255',
            ],
            'vat' => [
                'vat_rate' => 'required|numeric|min:0|max:100',
                'prices_include_vat' => 'boolean',
                'business_vat_number' => 'nullable|string|max:50',
                'business_company_reg' => 'nullable|string|max:50',
            ],
            'shipping' => [
                'free_shipping_threshold' => 'nullable|numeric|min:0',
                'default_shipping_cost' => 'nullable|numeric|min:0',
                'shipping_note' => 'nullable|string|max:1000',
            ],
            'payments' => [
                'paystack_enabled' => 'boolean',
                'eft_enabled' => 'boolean',
                'eft_bank_name' => 'nullable|string|max:100',
                'eft_account_name' => 'nullable|string|max:150',
                'eft_account_number' => 'nullable|string|max:50',
                'eft_branch_code' => 'nullable|string|max:20',
            ],
            'email' => [
                'order_confirmation_subject' => 'nullable|string|max:255',
                'quote_notification_email' => 'nullable|email|max:255',
            ],
            'security' => [
                'two_factor_required' => 'boolean',
            ],
            default => [],
        };

        $validated = $request->validate($rules);
        $group = $tab;

        foreach ($validated as $key => $value) {
            if (is_bool($value) || in_array($key, ['paystack_enabled', 'eft_enabled', 'prices_include_vat', 'two_factor_required'], true)) {
                $value = $request->boolean($key) ? '1' : '0';
            }
            Setting::set($key, (string) $value, $group);
        }

        $this->audit('settings.update', null, ['tab' => $tab]);

        return redirect()->route('admin.settings.index', ['tab' => $tab])->with('success', 'Settings saved.');
    }

    protected function loadSettings(): array
    {
        $defaults = [
            'company_name' => config('app.name', 'Urban Focus'),
            'company_website' => config('business.website', 'https://www.urbanfocus.co.za'),
            'company_email' => config('business.email', 'sales@urbanfocus.co.za'),
            'company_phone' => config('business.phone', '087 550 1813'),
            'company_hours' => config('business.hours', ''),
            'vat_rate' => (string) config('app.vat_rate', 15),
            'prices_include_vat' => config('app.prices_include_vat', true) ? '1' : '0',
            'business_vat_number' => config('business.vat_number', ''),
            'business_company_reg' => config('business.company_reg', ''),
            'free_shipping_threshold' => Setting::get('free_shipping_threshold', ''),
            'default_shipping_cost' => Setting::get('default_shipping_cost', ''),
            'shipping_note' => Setting::get('shipping_note', ''),
            'paystack_enabled' => Setting::get('paystack_enabled', '1'),
            'eft_enabled' => Setting::get('eft_enabled', '1'),
            'eft_bank_name' => config('payments.eft.bank_name', ''),
            'eft_account_name' => config('payments.eft.account_name', ''),
            'eft_account_number' => config('payments.eft.account_number', ''),
            'eft_branch_code' => config('payments.eft.branch_code', ''),
            'order_confirmation_subject' => Setting::get('order_confirmation_subject', 'Order Confirmation'),
            'quote_notification_email' => Setting::get('quote_notification_email', config('business.email')),
            'two_factor_required' => Setting::get('two_factor_required', '0'),
        ];

        foreach ($defaults as $key => $default) {
            $defaults[$key] = Setting::get($key, $default);
        }

        return $defaults;
    }
}
