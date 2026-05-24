@php
    $eft = config('payments.eft');
    $hasBankDetails = ! empty($eft['bank_name']) && ! empty($eft['account_number']);
@endphp

<div class="eft-instructions">
    <strong>EFT Payment Instructions</strong>
    <p class="mb-1 mt-2">Please use order number <strong>{{ $order->order_number }}</strong> as your payment reference.</p>
    <p class="mb-2">Amount due: <strong>R {{ number_format($order->total, 2) }}</strong></p>

    @if($hasBankDetails)
        <table class="table table-sm table-borderless mb-2 small">
            <tr><td class="text-muted pe-2">Bank</td><td><strong>{{ $eft['bank_name'] }}</strong></td></tr>
            <tr><td class="text-muted pe-2">Account name</td><td><strong>{{ $eft['account_name'] }}</strong></td></tr>
            <tr><td class="text-muted pe-2">Account number</td><td><strong>{{ $eft['account_number'] }}</strong></td></tr>
            @if(!empty($eft['branch_code']))
                <tr><td class="text-muted pe-2">Branch code</td><td><strong>{{ $eft['branch_code'] }}</strong></td></tr>
            @endif
            @if(!empty($eft['account_type']))
                <tr><td class="text-muted pe-2">Account type</td><td><strong>{{ $eft['account_type'] }}</strong></td></tr>
            @endif
            <tr><td class="text-muted pe-2">Reference</td><td><strong>{{ $order->order_number }}</strong></td></tr>
        </table>
        <p class="small text-muted mb-0">Please send proof of payment to <a href="mailto:{{ config('app.email') }}">{{ config('app.email') }}</a> if required.</p>
    @else
        <p class="small text-muted mb-0">Bank details are not configured yet. Contact <a href="mailto:{{ config('app.email') }}">{{ config('app.email') }}</a> or call {{ config('app.phone') }} for payment instructions.</p>
    @endif
</div>
