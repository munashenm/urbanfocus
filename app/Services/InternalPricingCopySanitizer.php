<?php

namespace App\Services;

use App\Models\Product;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Strip internal pricing / margin language from customer-facing catalogue copy.
 */
class InternalPricingCopySanitizer
{
    /**
     * Phrases that must never appear on a public product listing.
     *
     * @var list<string>
     */
    protected const LEAK_PATTERNS = [
        '/\bpaystack\s+(card\s+)?fees?\b/iu',
        '/\bbuffer\s+for\s+(paystack|card|fees?|payment)/iu',
        '/\bincludes?\s+a\s+buffer\b/iu',
        '/prices\s+on\s+this\s+page\s+already\s+include/iu',
        '/\bcover\s+card\s+fees?\b/iu',
        '/\bcard\s+fees?\b/iu',
        '/\bcatalogue?\s+(top-?ups?|markups?)\b/iu',
        '/\btop-?up\s+percent/iu',
        '/\b(not\s+)?undercutt?ing\b/iu',
        '/\bundercut\b/iu',
        '/\bunder-quotes?\b/iu',
        '/\bpricing\s+strategy\b/iu',
        '/\bour\s+costs?(?!-)/iu',
        '/\bsupplier\s+prices?\b/iu',
        '/\bstreet[- ]priced\b/iu',
        '/\bstreet\s*(price|priced|~)/iu',
        '/\bour\s+(price\s+)?markups?\b/iu',
        '/\b(gross|profit|high)[- ]margins?\b/iu',
        '/\bour\s+margins?\b/iu',
        '/\bfirstshop\b/iu',
        '/\blanded\s+costs?\b/iu',
        '/\bdistributor\s+costs?\b/iu',
        '/\bcost\s+price\b/iu',
        '/\bpayment\s+fees?\b/iu',
        '/\bbank\s+receiving\b/iu',
        '/\bour\s+(retail|list|selling)\s+prices?\b/iu',
        '/\bto\s+cover\s+(our\s+)?(fees|costs|card)/iu',
        '/\bmarkup\s+of\b/iu',
        '/\b%\s*markup\b/iu',
        '/\bmarkup\s*%/iu',
        '/\bprice[- ]competitive\b/iu',
        '/\bpriced\s+between\b/iu',
        '/\bpriced\s+to\s+cover\b/iu',
        '/\bcompetitor\s+prices?\b/iu',
        '/\bdo\s+not\s+undercut\b/iu',
        '/\bamazon\s*\(\s*r/iu',
    ];

    /**
     * Headings whose following section is internal or duplicated by the storefront block.
     *
     * @var list<string>
     */
    protected const INTERNAL_HEADING_PATTERNS = [
        '/why\s+buy\s+from\s+(urban\s+focus|us)\b/iu',
        '/\bour\s+pricing\b/iu',
        '/\bpricing\s+notes\b/iu',
        '/\bcatalogue?\s+pricing\b/iu',
        '/\bhow\s+we\s+price\b/iu',
        '/\babout\s+our\s+pric/iu',
        '/\bprice\s+strategy\b/iu',
    ];

    public function whyBuyHeading(): string
    {
        return (string) config('trust.why_buy.heading', 'Why buy from Urban Focus');
    }

    public function whyBuyBody(): string
    {
        return (string) config(
            'trust.why_buy.body',
            'Urban Focus supplies genuine enterprise networking equipment with VAT-compliant invoicing, nationwide delivery and procurement support for businesses, ISPs, installers and public-sector organisations.'
        );
    }

    public function containsLeak(string $text): bool
    {
        $haystack = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach (self::LEAK_PATTERNS as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    public function containsInternalHeading(string $text): bool
    {
        $haystack = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach (self::INTERNAL_HEADING_PATTERNS as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    public function sanitizePlain(?string $text, ?string $fallback = null): string
    {
        $text = (string) $text;

        if ($text === '') {
            return $fallback ?? '';
        }

        if (! $this->containsLeak($text) && ! $this->containsInternalHeading($text)) {
            return $text;
        }

        $cleaned = $this->filterSentences($text);
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? '');

        if ($cleaned === '') {
            return $fallback ?? '';
        }

        return $cleaned;
    }

    public function sanitizeHtml(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        if (! $this->containsLeak($html) && ! $this->containsInternalHeading($html)) {
            return $html;
        }

        if (! preg_match('/<[a-z][\s\S]*>/i', $html)) {
            return $this->sanitizePlain($html);
        }

        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><html><body><div id="uf-copy-root">'.$html.'</div></body></html>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('uf-copy-root');
        if (! $root instanceof DOMElement) {
            return $this->sanitizePlain(strip_tags($html));
        }

        $this->removeInternalSections($root);
        $this->scrubElement($root);
        $this->removeEmptyContainers($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faqs
     * @return list<array{question: string, answer: string}>
     */
    public function sanitizeFaqs(array $faqs): array
    {
        $clean = [];

        foreach ($faqs as $faq) {
            $question = $this->sanitizePlain((string) ($faq['question'] ?? ''));
            $answer = $this->sanitizePlain((string) ($faq['answer'] ?? ''));
            if ($question !== '' && $answer !== '') {
                $clean[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>|null  $specs
     * @return array<string, mixed>
     */
    public function sanitizeSpecifications(?array $specs): array
    {
        if (! is_array($specs) || $specs === []) {
            return $specs ?? [];
        }

        $clean = [];
        foreach ($specs as $key => $value) {
            if (is_string($value)) {
                $value = $this->sanitizePlain($value);
                if ($value === '') {
                    continue;
                }
            }
            $clean[$key] = $value;
        }

        for ($i = 1; $i <= 6; $i++) {
            $question = trim((string) ($clean["FAQ {$i} question"] ?? ''));
            $answer = trim((string) ($clean["FAQ {$i} answer"] ?? ''));
            if ($question === '' || $answer === '') {
                unset($clean["FAQ {$i} question"], $clean["FAQ {$i} answer"]);
            }
        }

        return $clean;
    }

    public function needsScrub(Product $product): bool
    {
        $parts = [
            (string) $product->short_description,
            (string) $product->description,
            (string) ($product->getAttributes()['meta_title'] ?? ''),
            (string) ($product->getAttributes()['meta_description'] ?? ''),
            (string) ($product->getAttributes()['meta_keywords'] ?? ''),
        ];

        if (is_array($product->specifications)) {
            foreach ($product->specifications as $value) {
                if (is_string($value) || is_numeric($value)) {
                    $parts[] = (string) $value;
                }
            }
        }

        $haystack = implode("\n", $parts);

        return $this->containsLeak($haystack) || $this->containsInternalHeading($haystack);
    }

    /**
     * @return array<string, mixed>
     */
    public function scrubProduct(Product $product, bool $dryRun = false): array
    {
        if (! $this->needsScrub($product)) {
            return [];
        }

        $updates = [];

        $short = $this->sanitizePlain((string) $product->short_description);
        if ($short !== (string) $product->short_description) {
            $updates['short_description'] = $short;
        }

        $description = $this->sanitizeHtml((string) $product->description);
        if ($description !== (string) $product->description) {
            $updates['description'] = $description;
        }

        foreach (['meta_title', 'meta_description', 'meta_keywords'] as $field) {
            $current = (string) ($product->getAttributes()[$field] ?? '');
            $clean = $this->sanitizePlain($current);
            if ($clean !== $current) {
                $updates[$field] = $clean !== '' ? $clean : null;
            }
        }

        $specs = is_array($product->specifications) ? $product->specifications : [];
        $cleanSpecs = $this->sanitizeSpecifications($specs);
        if ($cleanSpecs !== $specs) {
            $updates['specifications'] = $cleanSpecs;
        }

        if ($updates !== [] && ! $dryRun) {
            $product->update($updates);
        }

        return $updates;
    }

    /**
     * @return array{processed: int, updated: int, samples: list<string>}
     */
    public function scrubCatalog(bool $dryRun = false, ?int $limit = null): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'samples' => [],
        ];

        $query = Product::query()->orderBy('id');
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $query->lazyById(100)->each(function (Product $product) use ($dryRun, &$stats) {
            $stats['processed']++;
            $updates = $this->scrubProduct($product, $dryRun);
            if ($updates === []) {
                return;
            }

            $stats['updated']++;
            if (count($stats['samples']) < 40) {
                $stats['samples'][] = trim(($product->sku ? $product->sku.' ' : '').$product->name);
            }
        });

        return $stats;
    }

    protected function filterSentences(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $kept = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if ($this->containsLeak($part) || $this->containsInternalHeading($part)) {
                continue;
            }
            $kept[] = $part;
        }

        return implode(' ', $kept);
    }

    protected function removeInternalSections(DOMElement $root): void
    {
        $removing = false;

        foreach (iterator_to_array($root->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($removing && $child->parentNode === $root) {
                    $root->removeChild($child);
                }

                continue;
            }

            $isHeading = $this->isHeading($child);

            if ($isHeading) {
                if ($this->isInternalHeadingText($child->textContent ?? '')) {
                    $removing = true;
                    $root->removeChild($child);

                    continue;
                }
                $removing = false;
            }

            if ($removing) {
                $root->removeChild($child);
            }
        }
    }

    protected function scrubElement(DOMElement $el): void
    {
        foreach (iterator_to_array($el->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->scrubElement($child);
            }
        }

        $tag = strtolower($el->tagName);
        if (! in_array($tag, ['p', 'li', 'td', 'th', 'span', 'div', 'blockquote'], true)) {
            return;
        }

        $text = trim(html_entity_decode($el->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || (! $this->containsLeak($text) && ! $this->containsInternalHeading($text))) {
            return;
        }

        $cleaned = $this->sanitizePlain($text);
        if ($cleaned === '') {
            $el->parentNode?->removeChild($el);

            return;
        }

        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        $el->appendChild($el->ownerDocument->createTextNode($cleaned));
    }

    protected function removeEmptyContainers(DOMElement $root): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('*')) as $el) {
            if (! $el instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($el->tagName);
            if (! in_array($tag, ['ul', 'ol', 'div', 'p', 'li'], true)) {
                continue;
            }

            $text = trim($el->textContent ?? '');
            if ($text === '' && $el->getElementsByTagName('img')->length === 0) {
                $el->parentNode?->removeChild($el);
            }
        }
    }

    protected function isHeading(DOMNode $node): bool
    {
        return $node instanceof DOMElement
            && in_array(strtolower($node->tagName), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true);
    }

    protected function isInternalHeadingText(string $text): bool
    {
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        foreach (self::INTERNAL_HEADING_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
