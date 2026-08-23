<?php

namespace App\Services;

use Illuminate\Support\Str;

class SpecialistListingCopy
{
    /**
     * @param  array<string, mixed>  $item
     */
    public function shortDescription(array $item): string
    {
        $custom = trim((string) ($item['short_description'] ?? ''));
        if ($custom !== '') {
            return Str::limit($custom, 320, '');
        }

        return Str::limit($this->intro($item), 320, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaTitle(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'IT product'));

        return Str::limit($name.' | South Africa | Urban Focus', 70, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaDescription(array $item): string
    {
        $availability = $this->availabilityLabel($item);
        $lead = $this->shortDescription($item);

        return Str::limit("Buy {$lead} VAT invoices, nationwide courier and {$availability}. Supplied by Urban Focus in South Africa.", 160, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaKeywords(array $item): string
    {
        $parts = array_filter(array_merge([
            (string) ($item['brand'] ?? ''),
            (string) ($item['sku'] ?? ''),
            (string) ($item['name'] ?? ''),
            $this->familyLabel($item),
            'South Africa',
            'Johannesburg',
            'buy online',
            'supplier',
            'Urban Focus',
        ], array_values($this->specs($item))));

        return Str::limit(implode(', ', array_unique($parts)), 255, '');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public function specifications(array $item): array
    {
        $specs = $this->specs($item);
        $specs['Brand'] = (string) ($item['brand'] ?? ($specs['Brand'] ?? 'Urban Focus'));
        $specs['Model'] = (string) ($item['sku'] ?? ($specs['Model'] ?? ''));
        $specs['Category'] = $this->familyLabel($item);
        $specs['Warranty'] = $specs['Warranty'] ?? $this->warrantyLabel($item);
        $specs['Availability'] = $this->availabilityLabel($item);
        $specs['Availability key'] = (string) ($item['availability'] ?? 'eu_stock');
        $specs['Country'] = 'South Africa supply';
        $specs['Urban Focus range'] = SpecialistCatalogService::CATALOG_RANGE_SPEC_VALUE;

        foreach ($this->faqs($item) as $i => $faq) {
            $n = $i + 1;
            $specs["FAQ {$n} question"] = $faq['question'];
            $specs["FAQ {$n} answer"] = $faq['answer'];
        }

        return array_filter($specs);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function descriptionHtml(array $item): string
    {
        $suitable = '';
        foreach ($this->suitableFor($item) as $line) {
            $suitable .= '<li>'.e($line).'</li>';
        }

        $keys = '';
        foreach ($this->specs($item) as $label => $value) {
            $keys .= '<li><strong>'.e($label).':</strong> '.e($value).'</li>';
        }

        $faqs = '';
        foreach ($this->faqs($item) as $faq) {
            $faqs .= '<h4>'.e($faq['question']).'</h4>'.$this->p($faq['answer']);
        }

        $name = e((string) ($item['name'] ?? 'This product'));

        return implode("\n", array_filter([
            $this->p($this->intro($item)),
            '<h3>Advantages</h3>',
            $this->p($this->advantages($item)),
            '<h3>Suitable for</h3>',
            '<ul>'.$suitable.'</ul>',
            '<h3>Key specifications</h3>',
            '<ul>'.$keys.'</ul>',
            '<h3>South African supply</h3>',
            $this->p("Urban Focus supplies the {$name} to companies, schools and government buyers across South Africa with VAT invoices, courier delivery and local technical support. Listings are prepared for Google Shopping, Google Images and organic search, including Johannesburg, Cape Town, Durban and nationwide dispatch."),
            '<h3>Recommendations</h3>',
            $this->p($this->recommendations($item)),
            $faqs !== '' ? '<h3>Frequently asked questions</h3>'.$faqs : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function warrantyMonths(array $item): int
    {
        if (! empty($item['warranty_months'])) {
            return (int) $item['warranty_months'];
        }

        return match ($this->family($item)) {
            'linux-pc', 'mini-server', 'cluster', 'industrial-pc', 'plc' => 24,
            'smartphone' => 24,
            'software', 'service', 'solution' => 12,
            default => 24,
        };
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
        $key = (string) ($item['availability'] ?? 'eu_stock');

        return (string) (config("specialist.availability.{$key}.label") ?: 'EU STOCK – 5–10 BUSINESS DAYS');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function googleProductCategory(array $item): string
    {
        if (! empty($item['google_product_category'])) {
            return (string) $item['google_product_category'];
        }

        return (string) (config('specialist.google_product_category.'.$this->family($item)) ?: '5032');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function family(array $item): string
    {
        return (string) ($item['family'] ?? 'solution');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function familyLabel(array $item): string
    {
        return match ($this->family($item)) {
            'fido-key' => 'FIDO2 hardware security key',
            'hsm' => 'Hardware security module',
            'encrypted-storage' => 'Hardware-encrypted USB storage',
            'mfa-bundle' => 'Hardware MFA bundle',
            'pikvm' => 'KVM-over-IP remote server management',
            'cluster' => 'ARM cluster / private cloud board',
            'compute-module' => 'Cluster compute module',
            'mini-server' => 'Mini server / personal cloud',
            'nas' => 'Personal NAS / private cloud',
            'industrial-pc' => 'Industrial IoT computer',
            'plc' => 'Industrial Raspberry Pi / PLC',
            'iot-gateway' => 'Industrial IoT gateway',
            'ai-accelerator' => 'Edge AI accelerator',
            'edge-ai' => 'Edge AI appliance',
            'network-tool' => 'Network engineering tool',
            'secure-router' => 'Open-source secure router',
            'linux-pc' => 'Linux business computer',
            'smartphone' => 'Repairable smartphone',
            'software' => 'Enterprise software licence',
            'service' => 'Professional IT service',
            'firewall' => 'Open-source firewall',
            default => 'Specialist IT solution',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function intro(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'This product'));
        $brand = trim((string) ($item['brand'] ?? 'Urban Focus'));
        $family = $this->familyLabel($item);

        return "The {$name} from {$brand} is a {$family} supplied by Urban Focus for South African organisations that need specialist technology with local billing, POPIA-aware deployment advice and nationwide delivery.";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function advantages(array $item): string
    {
        if (! empty($item['advantages'])) {
            return is_array($item['advantages']) ? implode(' ', $item['advantages']) : (string) $item['advantages'];
        }

        return match ($this->family($item)) {
            'fido-key' => 'Hardware-backed FIDO2 / WebAuthn authentication resists phishing better than SMS OTP. Open-source firmware, no vendor lock-in, and suitable for Microsoft 365, Google Workspace and VPN MFA.',
            'hsm' => 'Keep private keys in tamper-resistant hardware for PKI, code signing and certificate authorities instead of leaving them on a general-purpose server.',
            'pikvm' => 'BIOS/UEFI access over IP lets Johannesburg and remote-branch technicians recover servers without flying a person to the rack.',
            'mini-server', 'cluster', 'nas' => 'Run a private cloud, Docker, Proxmox or NAS locally so files stay in South Africa instead of a public hyperscaler.',
            'industrial-pc', 'plc', 'iot-gateway' => 'DIN-rail industrial hardware with LTE, LoRa, Modbus or RS-485 for mines, farms, solar plants and municipalities.',
            'ai-accelerator', 'edge-ai' => 'On-device inference for CCTV analytics, ANPR and inspection without sending camera streams to a public cloud.',
            'secure-router', 'firewall' => 'Open-source routing and firewalling with local policy control for SMEs that do not want a black-box appliance.',
            'software', 'service' => 'Licensed through Urban Focus with South African invoicing, implementation and after-sales support.',
            default => 'Specified for professional use in South Africa with VAT invoices, courier logistics and Urban Focus technical support.',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function suitableFor(array $item): array
    {
        if (! empty($item['suitable_for']) && is_array($item['suitable_for'])) {
            return array_values($item['suitable_for']);
        }

        return match ($this->family($item)) {
            'fido-key', 'mfa-bundle' => ['Corporate MFA', 'Microsoft 365 tenants', 'Google Workspace', 'VPN and admin access', 'South African POPIA programmes'],
            'hsm' => ['PKI / certificate authorities', 'Code signing', 'Server authentication', 'Government and banking security teams'],
            'pikvm' => ['Remote branch servers', 'Data centre lights-out management', 'School server rooms', 'CCTV / NVR racks'],
            'mini-server', 'cluster', 'nas' => ['Homelab and DevOps labs', 'SME private cloud', 'Edge computing', 'On-prem file sync'],
            'industrial-pc', 'plc', 'iot-gateway' => ['Mining telemetry', 'Agriculture and irrigation', 'Solar plant monitoring', 'Municipal water and pumps', 'Factory Modbus / CAN'],
            'ai-accelerator', 'edge-ai' => ['CCTV analytics', 'Number-plate recognition', 'Retail counting', 'Manufacturing inspection'],
            'linux-pc' => ['Software developers', 'Cybersecurity professionals', 'Universities', 'DevOps engineers'],
            'smartphone' => ['Repairable / sustainable handsets', 'IT teams that want spare-part support'],
            'software', 'service', 'solution' => ['South African SMEs', 'Schools and government', 'Professional services firms'],
            default => ['South African businesses', 'IT managers', 'System integrators'],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    protected function specs(array $item): array
    {
        $specs = is_array($item['specs'] ?? null) ? $item['specs'] : [];

        return array_filter(array_merge($this->familySpecs($item), $specs));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    protected function familySpecs(array $item): array
    {
        return match ($this->family($item)) {
            'fido-key' => ['Protocols' => 'FIDO2 / WebAuthn', 'Use' => 'Passwordless and MFA', 'Origin' => 'Germany'],
            'hsm' => ['Type' => 'Hardware Security Module', 'Use' => 'PKI and key storage', 'Origin' => 'Germany'],
            'pikvm' => ['Type' => 'KVM-over-IP', 'Access' => 'BIOS / UEFI / OS', 'Use' => 'Remote server management'],
            'ai-accelerator' => ['Type' => 'Edge AI accelerator', 'Deployment' => 'On-prem inference'],
            'secure-router' => ['Type' => 'Open-source secure router', 'Use' => 'SME firewall / VPN'],
            'software' => ['Licence' => 'Vendor subscription via Urban Focus', 'Billing' => 'South African VAT invoice'],
            'service' => ['Delivery' => 'Urban Focus professional services', 'Region' => 'South Africa'],
            default => ['Supplier' => 'Urban Focus', 'Region' => 'South Africa'],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{question: string, answer: string}>
     */
    protected function faqs(array $item): array
    {
        if (! empty($item['faqs']) && is_array($item['faqs'])) {
            return array_values($item['faqs']);
        }

        $name = (string) ($item['name'] ?? 'this product');

        return [
            [
                'question' => "Can I buy the {$name} in South Africa?",
                'answer' => "Yes. Urban Focus supplies the {$name} with a VAT invoice and courier delivery nationwide, including Johannesburg, Cape Town, Durban, Pretoria and remote sites.",
            ],
            [
                'question' => 'How long does delivery take?',
                'answer' => $this->availabilityLabel($item).'. '.$this->leadTimeCopy($item),
            ],
            [
                'question' => 'Is this listing ready for Google Shopping?',
                'answer' => 'Yes. Each specialist product includes a unique title, description, MPN/SKU, brand, image alt text and structured data so Google Merchant Center, Google Images and AI search overviews can index it.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function leadTimeCopy(array $item): string
    {
        return match ((string) ($item['availability'] ?? 'eu_stock')) {
            'in_stock_za' => 'Local stock typically ships in 1–3 business days.',
            'eu_stock' => 'European warehouse stock is usually 5–10 business days to a South African address.',
            'special_order_eu' => 'Built or allocated in Europe on order; allow two to four weeks plus customs.',
            'request_quote' => 'Lead time is confirmed on the official quote.',
            'contact_licensing' => 'Licence keys and terms are confirmed after a licensing conversation with Urban Focus.',
            default => 'Lead time is confirmed when you order.',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function recommendations(array $item): string
    {
        if (! empty($item['recommendations'])) {
            return (string) $item['recommendations'];
        }

        return 'Ask Urban Focus to size quantity, licences and professional services before you buy. We can bundle hardware with Proxmox, Nextcloud, OPNsense or Microsoft 365 hardening for a complete South African deployment.';
    }

    protected function p(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }
}
