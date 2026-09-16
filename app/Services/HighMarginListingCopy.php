<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Customer-facing listing copy for the high-margin technology collection.
 *
 * Never include Google Shopping, Merchant Center, schema, AI-search or
 * image-alt implementation notes in descriptions, SEO fields or FAQs.
 */
class HighMarginListingCopy
{
    public function __construct(protected InternalPricingCopySanitizer $copySanitizer) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function shortDescription(array $item): string
    {
        $raw = trim((string) ($item['short_description'] ?? $item['intro'] ?? ''));
        $clean = $this->copySanitizer->sanitizePlain($raw, $raw);

        return Str::limit($clean, 320, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaTitle(array $item): string
    {
        $custom = trim((string) ($item['meta_title'] ?? ''));
        if ($custom !== '') {
            return $this->mysqlVarchar($custom, 70);
        }

        $name = trim((string) ($item['name'] ?? 'IT product'));

        return $this->mysqlVarchar($name.' | Urban Focus', 70);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaDescription(array $item): string
    {
        $custom = trim((string) ($item['meta_description'] ?? ''));
        if ($custom !== '') {
            return $this->mysqlVarchar($custom, 160);
        }

        return $this->mysqlVarchar(
            $this->shortDescription($item).' Price includes 15% VAT. Available on order from Urban Focus, South Africa.',
            160
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaKeywords(array $item): string
    {
        $parts = array_filter(array_merge([
            (string) ($item['brand'] ?? ''),
            (string) ($item['name'] ?? ''),
            (string) ($item['sku'] ?? ''),
            (string) ($item['mpn'] ?? ''),
        ], array_map('strval', $item['match_terms'] ?? []), [
            'South Africa',
            'Urban Focus',
        ]));

        return $this->mysqlVarchar(
            implode(', ', array_unique(array_filter($parts))),
            Product::META_KEYWORDS_MAX_LENGTH,
            cutAtComma: true
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public function specifications(array $item): array
    {
        $specs = [];
        foreach ($item['specs'] ?? [] as $label => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $specs[(string) $label] = trim((string) $value);
            }
        }

        $specs['Brand'] = (string) ($item['brand'] ?? ($specs['Brand'] ?? 'Urban Focus'));
        $specs['Model'] = (string) ($item['mpn'] ?? $item['sku'] ?? ($specs['Model'] ?? ''));
        $specs['SKU'] = (string) ($item['sku'] ?? '');
        $specs['Warranty'] = $specs['Warranty'] ?? $this->warrantyLabel($item);
        $specs['Availability'] = $this->availabilityLabel($item);
        $specs['Availability key'] = (string) ($item['availability'] ?? 'available_on_order');
        $specs['Country'] = 'South Africa supply';
        $specs['Urban Focus range'] = HighMarginCatalogService::CATALOG_RANGE_SPEC_VALUE;

        if (! empty($item['related_skus']) && is_array($item['related_skus'])) {
            $specs['Related SKUs'] = implode(', ', array_values(array_filter(array_map('strval', $item['related_skus']))));
        }
        if (! empty($item['icasa_note'])) {
            $specs['ICASA note'] = (string) $item['icasa_note'];
        }
        if (! empty($item['supplier_image_approval'])) {
            $specs['Supplier image approval'] = (string) $item['supplier_image_approval'];
        }
        if (! empty($item['internal_notes'])) {
            $specs['Internal notes'] = (string) $item['internal_notes'];
        }

        foreach ($this->faqs($item) as $i => $faq) {
            $n = $i + 1;
            $specs["FAQ {$n} question"] = $faq['question'];
            $specs["FAQ {$n} answer"] = $faq['answer'];
        }

        return array_filter($specs, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function descriptionHtml(array $item): string
    {
        $features = '';
        foreach ($this->keyFeatures($item) as $line) {
            $features .= '<li>'.e($line).'</li>';
        }

        $suitable = '';
        foreach ($this->suitableFor($item) as $line) {
            $suitable .= '<li>'.e($line).'</li>';
        }

        $keys = '';
        foreach ($item['specs'] ?? [] as $label => $value) {
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }
            $keys .= '<li><strong>'.e((string) $label).':</strong> '.e(trim((string) $value)).'</li>';
        }

        $included = '';
        foreach ($this->includedWith($item) as $line) {
            $included .= '<li>'.e($line).'</li>';
        }

        $name = (string) ($item['name'] ?? 'This product');

        $html = implode("\n", array_filter([
            $this->p($this->intro($item)),
            $features !== '' ? '<h3>Key features</h3>' : '',
            $features !== '' ? '<ul>'.$features.'</ul>' : '',
            $suitable !== '' ? '<h3>Ideal applications</h3>' : '',
            $suitable !== '' ? '<ul>'.$suitable.'</ul>' : '',
            $keys !== '' ? '<h3>Technical specifications</h3>' : '',
            $keys !== '' ? '<ul>'.$keys.'</ul>' : '',
            $included !== '' ? '<h3>What\'s included</h3>' : '',
            $included !== '' ? '<ul>'.$included.'</ul>' : '',
            '<h3>Warranty</h3>',
            $this->p($this->warrantyLabel($item).' through Urban Focus, with local support if you need help during the cover period.'),
            '<h3>Delivery information</h3>',
            $this->p("Urban Focus supplies the {$name} to companies, schools, installers and government buyers across South Africa with VAT invoices, courier delivery and local technical support, including Johannesburg, Cape Town, Durban and nationwide dispatch. ".$this->leadTimeCopy($item)),
        ]));

        return $this->copySanitizer->sanitizeHtml($html);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function warrantyMonths(array $item): int
    {
        return max(1, (int) ($item['warranty_months'] ?? 12));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function warrantyLabel(array $item): string
    {
        $months = $this->warrantyMonths($item);

        return $months >= 12
            ? ((int) ($months / 12)).' year manufacturer warranty'
            : $months.' month manufacturer warranty';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function availabilityLabel(array $item): string
    {
        $key = (string) ($item['availability'] ?? 'available_on_order');

        return (string) (config("specialist.availability.{$key}.label") ?: 'AVAILABLE ON ORDER');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function googleProductCategory(array $item): string
    {
        return (string) ($item['google_product_category'] ?? '5032');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function preferredSlug(array $item): string
    {
        $custom = trim((string) ($item['slug'] ?? ''));
        if ($custom !== '') {
            return Str::slug($custom);
        }

        return Str::slug((string) ($item['name'] ?? 'product'));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{question: string, answer: string}>
     */
    public function faqs(array $item): array
    {
        $faqs = [];
        foreach ($item['faqs'] ?? [] as $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $faqs[] = ['question' => $question, 'answer' => $answer];
        }

        if ($faqs === []) {
            $faqs = [
                [
                    'question' => 'Is this item in Johannesburg stock?',
                    'answer' => $this->availabilityLabel($item).'. '.$this->leadTimeCopy($item),
                ],
                [
                    'question' => 'Does the price include VAT?',
                    'answer' => 'Yes. The published price includes 15% VAT and is invoiced by Urban Focus in South Africa.',
                ],
            ];
        }

        return $this->copySanitizer->sanitizeFaqs($faqs);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function intro(array $item): string
    {
        return trim((string) ($item['intro'] ?? $this->shortDescription($item)));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function keyFeatures(array $item): array
    {
        $features = $item['key_features'] ?? [];
        if (! is_array($features)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($line) => trim((string) $line), $features)));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function suitableFor(array $item): array
    {
        $lines = $item['suitable_for'] ?? [];
        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($line) => trim((string) $line), $lines)));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function includedWith(array $item): array
    {
        $lines = $item['included'] ?? [];
        if (! is_array($lines) || $lines === []) {
            return ['The specified product', 'Manufacturer documentation'];
        }

        return array_values(array_filter(array_map(fn ($line) => trim((string) $line), $lines)));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function leadTimeCopy(array $item): string
    {
        $custom = trim((string) ($item['lead_time_copy'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        return 'This product is available on order. Urban Focus confirms supplier allocation and dispatch timing before the order is finalised. Listings are not Johannesburg walk-in stock unless stated otherwise.';
    }

    protected function p(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }

    protected function mysqlVarchar(string $value, int $limit, bool $cutAtComma = false): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '' || mb_strlen($value) <= $limit) {
            return $value;
        }

        $cut = rtrim(mb_substr($value, 0, $limit), " \t,;");
        if ($cutAtComma && str_contains($cut, ',')) {
            $cut = rtrim((string) preg_replace('/,[^,]*$/', '', $cut), " \t,;");
        }

        return $cut;
    }
}
