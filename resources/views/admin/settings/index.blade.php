@extends('layouts.admin')

@section('page_title', 'Settings')

@section('content')
<ul class="nav nav-tabs mb-4">
    @foreach(['company' => 'Company', 'vat' => 'VAT', 'shipping' => 'Shipping', 'payments' => 'Payments', 'email' => 'Email', 'security' => 'Security'] as $key => $label)
        <li class="nav-item">
            <a class="nav-link @if($tab === $key) active @endif" href="{{ route('admin.settings.index', ['tab' => $key]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf @method('PUT')
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="card admin-card">
        <div class="card-body">
            @if($tab === 'company')
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Company name</label><input type="text" name="company_name" class="form-control" value="{{ old('company_name', $settings['company_name']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Website</label><input type="url" name="company_website" class="form-control" value="{{ old('company_website', $settings['company_website']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="company_email" class="form-control" value="{{ old('company_email', $settings['company_email']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="company_phone" class="form-control" value="{{ old('company_phone', $settings['company_phone']) }}"></div>
                    <div class="col-12"><label class="form-label">Business hours</label><input type="text" name="company_hours" class="form-control" value="{{ old('company_hours', $settings['company_hours']) }}"></div>
                </div>
            @elseif($tab === 'vat')
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">VAT rate (%)</label><input type="number" step="0.01" name="vat_rate" class="form-control" value="{{ old('vat_rate', $settings['vat_rate']) }}"></div>
                    <div class="col-md-4"><label class="form-label">VAT number</label><input type="text" name="business_vat_number" class="form-control" value="{{ old('business_vat_number', $settings['business_vat_number']) }}"><small class="text-muted">Shown on paid invoices only.</small></div>
                    <div class="col-md-4"><label class="form-label">Company registration</label><input type="text" name="business_company_reg" class="form-control" value="{{ old('business_company_reg', $settings['business_company_reg']) }}"></div>
                    <div class="col-12 form-check">
                        <input type="hidden" name="prices_include_vat" value="0">
                        <input type="checkbox" class="form-check-input" name="prices_include_vat" value="1" id="prices_include_vat" @checked(old('prices_include_vat', $settings['prices_include_vat']) === '1')>
                        <label class="form-check-label" for="prices_include_vat">Prices include VAT</label>
                    </div>
                </div>
            @elseif($tab === 'shipping')
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Free shipping threshold (ZAR)</label><input type="number" step="0.01" name="free_shipping_threshold" class="form-control" value="{{ old('free_shipping_threshold', $settings['free_shipping_threshold']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Default shipping cost</label><input type="number" step="0.01" name="default_shipping_cost" class="form-control" value="{{ old('default_shipping_cost', $settings['default_shipping_cost']) }}"></div>
                    <div class="col-12"><label class="form-label">Shipping note</label><textarea name="shipping_note" class="form-control" rows="3">{{ old('shipping_note', $settings['shipping_note']) }}</textarea></div>
                </div>
            @elseif($tab === 'payments')
                <div class="row g-3">
                    <div class="col-12 form-check"><input type="hidden" name="paystack_enabled" value="0"><input type="checkbox" class="form-check-input" name="paystack_enabled" value="1" id="paystack_enabled" @checked(old('paystack_enabled', $settings['paystack_enabled']) === '1')><label class="form-check-label" for="paystack_enabled">Enable Paystack</label></div>
                    <div class="col-12 form-check"><input type="hidden" name="eft_enabled" value="0"><input type="checkbox" class="form-check-input" name="eft_enabled" value="1" id="eft_enabled" @checked(old('eft_enabled', $settings['eft_enabled']) === '1')><label class="form-check-label" for="eft_enabled">Enable EFT payments</label></div>
                    <div class="col-md-6"><label class="form-label">Bank name</label><input type="text" name="eft_bank_name" class="form-control" value="{{ old('eft_bank_name', $settings['eft_bank_name']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Account name</label><input type="text" name="eft_account_name" class="form-control" value="{{ old('eft_account_name', $settings['eft_account_name']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Account number</label><input type="text" name="eft_account_number" class="form-control" value="{{ old('eft_account_number', $settings['eft_account_number']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Branch code</label><input type="text" name="eft_branch_code" class="form-control" value="{{ old('eft_branch_code', $settings['eft_branch_code']) }}"></div>
                </div>
            @elseif($tab === 'email')
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Order confirmation subject</label><input type="text" name="order_confirmation_subject" class="form-control" value="{{ old('order_confirmation_subject', $settings['order_confirmation_subject']) }}"></div>
                    <div class="col-md-6"><label class="form-label">Quote notification email</label><input type="email" name="quote_notification_email" class="form-control" value="{{ old('quote_notification_email', $settings['quote_notification_email']) }}"></div>
                </div>
            @elseif($tab === 'security')
                <div class="form-check mb-3">
                    <input type="hidden" name="two_factor_required" value="0">
                    <input type="checkbox" class="form-check-input" name="two_factor_required" value="1" id="two_factor_required" @checked(old('two_factor_required', $settings['two_factor_required']) === '1') disabled>
                    <label class="form-check-label" for="two_factor_required">Require two-factor authentication (coming soon)</label>
                </div>
                <p class="text-muted small mb-0">Account lockout after failed login attempts is enabled. Optional 2FA fields are stored securely for a future release.</p>
            @endif
        </div>
        @if($tab !== 'security')
        <div class="card-footer bg-white"><button class="btn btn-primary">Save settings</button></div>
        @endif
    </div>
</form>
@endsection
