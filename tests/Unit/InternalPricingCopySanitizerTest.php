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

    public function test_strips_live_u7_enterprise_pricing_strategy_sentence(): void
    {
        $html = '<h2>Ubiquiti UniFi Enterprise Wi-Fi 7 Access Point in South Africa</h2>'
            .'<p>The Ubiquiti UniFi Enterprise Wi-Fi 7 Access Point is an enterprise wireless access point supplied by Urban Focus for South African businesses, integrators and public-sector buyers. Wi-Fi 7 access points are now a standard line item on office, hospitality and education refreshes. The Ubiquiti UniFi Enterprise Wi-Fi 7 Access Point is a Ubiquiti enterprise AP for controller-based networks (UniFi, Omada, Grandstream or Reyee as applicable). Pricing is set to win against local distributors without giving the product away.</p>'
            .'<h3>Key specifications</h3>'
            .'<ul><li><strong>Wireless:</strong> Wi-Fi 7</li></ul>'
            .'<p>Warranty cover and nationwide delivery are available. Request a formal quotation for volume purchasing and procurement.</p>';

        $clean = $this->sanitizer->sanitizeHtml($html);

        $this->assertStringContainsString('enterprise wireless access point supplied by Urban Focus', $clean);
        $this->assertStringContainsString('office, hospitality and education', $clean);
        $this->assertStringContainsString('Wi-Fi 7', $clean);
        $this->assertStringContainsString('UniFi', $clean);
        $this->assertStringContainsString('Warranty cover and nationwide delivery', $clean);
        $this->assertStringContainsString('formal quotation', $clean);
        $this->assertStringContainsString('Key specifications', $clean);
        $this->assertStringNotContainsString('Pricing is set to win', $clean);
        $this->assertStringNotContainsString('local distributors', $clean);
        $this->assertStringNotContainsString('giving the product away', $clean);
    }

    public function test_strips_fee_margin_and_staff_note_sentences(): void
    {
        $text = 'Dual-radio Wi-Fi 7 AP for warehouses and schools. '
            .'Card/EFT charges and bank receiving charges are built into the prices. '
            .'A catalogue top-up protects sustainable margin. '
            .'Staff note: procurement note, do not show customer our cost.';

        $clean = $this->sanitizer->sanitizePlain($text);

        $this->assertStringContainsString('Dual-radio Wi-Fi 7 AP for warehouses and schools.', $clean);
        $this->assertStringNotContainsString('Card/EFT', $clean);
        $this->assertStringNotContainsString('bank receiving', $clean);
        $this->assertStringNotContainsString('catalogue top-up', $clean);
        $this->assertStringNotContainsString('sustainable margin', $clean);
        $this->assertStringNotContainsString('Staff note', $clean);
        $this->assertStringNotContainsString('our cost', $clean);
    }

    public function test_strips_google_shopping_and_structured_data_commentary(): void
    {
        $html = '<p>Urban Focus supplies the Nitrokey 3C NFC to companies, schools and government buyers across South Africa with VAT invoices, courier delivery and local technical support. Listings are prepared for Google Shopping, Google Images and organic search, including Johannesburg, Cape Town, Durban and nationwide dispatch.</p>'
            .'<h3>Frequently asked questions</h3>'
            .'<h4>Can I buy the Nitrokey 3C NFC in South Africa?</h4>'
            .'<p>Yes. Urban Focus supplies it with a VAT invoice nationwide.</p>'
            .'<h4>Is this listing ready for Google Shopping?</h4>'
            .'<p>Yes. Each specialist product includes a unique title, description, MPN/SKU, brand, image alt text and structured data so Google Merchant Center, Google Images and AI search overviews can index it.</p>';

        $clean = $this->sanitizer->sanitizeHtml($html);

        $this->assertStringContainsString('VAT invoices', $clean);
        $this->assertStringContainsString('Can I buy the Nitrokey 3C NFC in South Africa?', $clean);
        $this->assertStringNotContainsString('Google Shopping', $clean);
        $this->assertStringNotContainsString('Google Merchant Center', $clean);
        $this->assertStringNotContainsString('structured data', $clean);
        $this->assertStringNotContainsString('image alt text', $clean);
        $this->assertStringNotContainsString('AI search', $clean);
        $this->assertStringNotContainsString('Is this listing ready', $clean);
    }

    public function test_regex_strips_google_shopping_faq_heading_and_answer(): void
    {
        $html = '<h3>Frequently asked questions</h3>'
            .'<h4>Can I buy the Urban Focus ZimaBoard CCTV Recording Storage Server in South Africa?</h4>'
            .'<p>Yes. Urban Focus supplies it with a VAT invoice nationwide.</p>'
            .'<h4>How long does delivery take?</h4>'
            .'<p>REQUEST A QUOTE. Lead time is confirmed on the official quote.</p>'
            .'<h4>Is this listing ready for Google Shopping?</h4>'
            .'<p>Yes. Each specialist product includes a unique title, description, MPN/SKU, brand, image alt text and structured data so Google Merchant Center, Google Images and AI search overviews can index it.</p>';

        $clean = $this->sanitizer->stripSeoImplementationHtml($html);

        $this->assertStringContainsString('Can I buy the Urban Focus ZimaBoard', $clean);
        $this->assertStringContainsString('How long does delivery take?', $clean);
        $this->assertStringNotContainsString('Is this listing ready for Google Shopping?', $clean);
        $this->assertStringNotContainsString('Google Merchant Center', $clean);
        $this->assertStringNotContainsString('AI search overviews', $clean);
    }

    public function test_does_not_strip_google_workspace_customer_copy(): void
    {
        $text = 'Hardware-backed FIDO2 authentication for Microsoft 365 and Google Workspace MFA.';

        $this->assertSame($text, $this->sanitizer->sanitizePlain($text));
        $this->assertFalse($this->sanitizer->containsLeak($text));
    }

    public function test_drops_google_shopping_faqs(): void
    {
        $faqs = $this->sanitizer->sanitizeFaqs([
            [
                'question' => 'Can I buy this in South Africa?',
                'answer' => 'Yes. Urban Focus supplies it with a VAT invoice.',
            ],
            [
                'question' => 'Is this listing ready for Google Shopping?',
                'answer' => 'Yes. Each specialist product includes image alt text and structured data so Google Merchant Center can index it.',
            ],
        ]);

        $this->assertCount(1, $faqs);
        $this->assertSame('Can I buy this in South Africa?', $faqs[0]['question']);
    }
}
