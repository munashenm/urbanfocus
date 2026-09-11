<?php

namespace Tests\Unit;

use App\Services\InternalPricingCopySanitizer;
use Tests\TestCase;

class InternalPricingCopySanitizerTest extends TestCase
{
    protected InternalPricingCopySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = app(InternalPricingCopySanitizer::class);
    }

    public function test_strips_paystack_fee_buffer_language_and_keeps_product_facts(): void
    {
        $html = '<p>Ceiling-mount Wi-Fi 7 access point for enterprise campuses.</p>'
            .'<h3>Why buy from Urban Focus</h3>'
            .'<p>Prices on this page already include a buffer for Paystack card fees and a catalogue markup so we do not undercut our price.</p>';

        $clean = $this->sanitizer->sanitizeHtml($html);

        $this->assertStringContainsString('Ceiling-mount Wi-Fi 7 access point', $clean);
        $this->assertStringNotContainsString('Paystack', $clean);
        $this->assertStringNotContainsString('catalogue markup', $clean);
        $this->assertStringNotContainsString('undercut', $clean);
        $this->assertStringNotContainsString('Why buy from Urban Focus', $clean);
    }

    public function test_strips_competitor_and_card_fee_sentences_from_plain_text(): void
    {
        $text = 'Industrial 5G dual-SIM router. Priced between FirstShop (R9,999) and Amazon (R10,920) to cover card fees.';

        $this->assertSame(
            'Industrial 5G dual-SIM router.',
            $this->sanitizer->sanitizePlain($text)
        );
    }

    public function test_leaves_unrelated_hardware_copy_alone(): void
    {
        $text = 'Confirm PoE class and mounting. Plan switch PoE budget before you hang the AP.';

        $this->assertSame($text, $this->sanitizer->sanitizePlain($text));
        $this->assertFalse($this->sanitizer->containsLeak($text));
    }

    public function test_strips_staff_and_do_not_show_customer_notes(): void
    {
        $text = 'Dual-radio Wi-Fi 7 AP. Internal note: do not show customer our cost price.';

        $this->assertSame(
            'Dual-radio Wi-Fi 7 AP.',
            $this->sanitizer->sanitizePlain($text)
        );
    }

    public function test_does_not_treat_packet_buffer_as_a_pricing_leak(): void
    {
        $text = 'The switch includes a large packet buffer for bursty traffic.';

        $this->assertSame($text, $this->sanitizer->sanitizePlain($text));
        $this->assertFalse($this->sanitizer->containsLeak($text));
    }
}
