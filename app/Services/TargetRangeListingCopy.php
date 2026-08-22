<?php

namespace App\Services;

use Illuminate\Support\Str;

class TargetRangeListingCopy
{
    /**
     * @param  array<string, mixed>  $item
     */
    public function shortDescription(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'This product'));
        $brand = trim((string) ($item['brand'] ?? ''));
        $use = $this->audienceLine($item);

        $lead = $brand !== ''
            ? "Buy the {$name} from Urban Focus in South Africa."
            : "Buy {$name} from Urban Focus in South Africa.";

        return Str::limit(trim($lead.' '.$this->oneLineSpec($item).' '.$use), 220, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaTitle(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'IT product'));
        $brand = trim((string) ($item['brand'] ?? ''));
        $title = $brand !== '' && ! str_starts_with(mb_strtolower($name), mb_strtolower($brand))
            ? $brand.' '.$name
            : $name;

        return Str::limit($title.' | Urban Focus SA', 70, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaDescription(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'business IT'));
        $brand = trim((string) ($item['brand'] ?? 'Urban Focus'));
        $family = $this->familyLabel($item);

        return Str::limit(
            "Buy genuine {$brand} {$name} in South Africa. {$family} with warranty, VAT invoice and nationwide delivery from Urban Focus.",
            160,
            ''
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaKeywords(array $item): string
    {
        $parts = array_filter([
            (string) ($item['brand'] ?? ''),
            (string) ($item['sku'] ?? ''),
            (string) ($item['name'] ?? ''),
            $this->familyLabel($item),
            'South Africa',
            'buy',
            'quote',
            'Urban Focus',
        ]);

        return Str::limit(implode(', ', array_unique($parts)), 255, '');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public function specifications(array $item): array
    {
        $specs = array_filter([
            'Model' => (string) ($item['sku'] ?? ''),
            'Brand' => (string) ($item['brand'] ?? ''),
            'Product type' => $this->familyLabel($item),
            'Availability' => 'Available to order — typically 5–10 working days',
            'Supply' => 'South African B2B supply with VAT invoice',
            'Warranty' => $this->warrantyLabel($item),
        ]);

        foreach ($this->parsedSpecs($item) as $label => $value) {
            $specs[$label] = $value;
        }

        $specs['Urban Focus range'] = 'Target catalogue';

        return $specs;
    }

    /**
     * Full HTML description for Google and the product page.
     *
     * @param  array<string, mixed>  $item
     */
    public function descriptionHtml(array $item): string
    {
        $name = e(trim((string) ($item['name'] ?? 'This product')));
        $brand = e(trim((string) ($item['brand'] ?? 'Urban Focus')));
        $sku = e(trim((string) ($item['sku'] ?? '')));
        $family = e($this->familyLabel($item));

        $sections = [
            $this->p($this->openingParagraph($item)),
            '<h2>'.$name.' in South Africa</h2>',
            $this->p($this->positioningParagraph($item)),
            '<h3>Key specifications</h3>',
            $this->specList($item),
            '<h3>Who this is for</h3>',
            $this->p($this->audienceParagraph($item)),
            '<h3>Why source from Urban Focus</h3>',
            $this->p($this->procurementParagraph($item)),
            '<h3>Configuration, warranty and delivery</h3>',
            $this->p($this->fulfilmentParagraph($item)),
            $this->p("Search and procurement teams looking for {$brand} {$name} ({$sku}) — a {$family} supplied in South Africa — can request a formal quote from Urban Focus. Pricing is set for competitive South African street comparison while covering card, EFT and bank charges so we do not under-quote live business orders."),
        ];

        return implode("\n", $sections);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function openingParagraph(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'This model'));
        $brand = trim((string) ($item['brand'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? ''));
        $short = trim((string) ($item['short_description'] ?? ''));
        $family = $this->family($item);

        $intro = $brand !== ''
            ? "The {$name} is a genuine {$brand} {$this->familyLabel($item)} supplied by Urban Focus for South African businesses, integrators and public-sector buyers."
            : "The {$name} is supplied by Urban Focus for South African businesses, integrators and public-sector buyers.";

        $detail = $short !== ''
            ? ' '.$this->professionalizeShort($short, $item)
            : '';

        $skuBit = $sku !== '' ? " Official model reference {$sku}." : '';

        return $intro.$detail.$skuBit.' '.$this->familyHook($family, $item);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function positioningParagraph(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'this product'));
        $brand = trim((string) ($item['brand'] ?? 'the manufacturer'));

        return match ($this->family($item)) {
            'laptop' => "IT managers compare {$brand} business notebooks against FirstShop, Incredible Connection and OEM stores. Urban Focus lists the {$name} as a current corporate configuration — Windows 11 Pro ready — with a selling price that stays in-market for RFQs while protecting margin after Paystack and bank fees. Ask for a quote if you need a fleet image, docking, or a three-year on-site wrap.",
            'workstation' => "Mobile workstations are specified for CAD, BIM, GIS and content work where a thin-and-light laptop is not enough. The {$name} is positioned for engineering and design teams that need certified graphics, more memory and a machine that can be written into a tender without a consumer SKU. Configuration and ISV notes are confirmed on quote.",
            'router' => "Industrial and branch routers are bought on failover, dual-SIM, temperature rating and remote management — not supermarket Wi-Fi. The {$name} is a {$brand} business router for South African sites that need 4G/5G backup, primary cellular WAN or rugged connectivity. We price it against current street offers and keep enough cover for card and EFT charges.",
            'switch' => "Switching is specified by port speed, PoE budget and uplink class. The {$name} is a {$brand} managed switch for offices, warehouses and campus closets that are moving to 2.5GbE, 10GbE or multi-gig Wi-Fi 7 access points. Urban Focus quotes complete bills of materials — optics, patching and accessories — so the switch is not sold as a bare box without a plan.",
            'access_point' => "Wi-Fi 7 and Wi-Fi 6E access points are now a standard line item on office, hospitality and education refreshes. The {$name} is a {$brand} enterprise AP for controller-based networks (UniFi, Omada, Grandstream or Reyee as applicable). Pricing is set to win against local distributors without giving the product away.",
            'camera' => "The {$name} is a {$brand} IP camera for South African commercial, campus and perimeter projects. Specify it where you need a named OEM model on a drawing, not a generic dome. Urban Focus supplies cameras with matching NVRs, licences and brackets on one quote.",
            'nvr' => "The {$name} is a {$brand} network video recorder for multi-camera sites. Channel count, PoE and AI analytics are confirmed against your camera list before we lock a price. We do not advertise a hollow “from” figure that collapses once storage and licences are added.",
            'access' => "The {$name} is a {$brand} access-control or biometric terminal for offices, schools and guarded sites. Urban Focus can quote readers, controllers, locks and installation partners together so time-and-attendance or door control is a complete system.",
            'ups' => "Load-shedding and unstable utility power make a true online UPS a business continuity item, not an accessory. The {$name} is a {$brand} UPS for server rooms, network closets and till points. Runtime, PDU and network-management cards are quoted to the actual load — we will not under-size a 3 kVA or 6 kVA unit to win a price war.",
            'server' => "The {$name} is a {$brand} rack server class for SME and enterprise rooms in South Africa. Processor, memory, disks and support pack vary by RFQ. The listed price is a complete-enough starting configuration for comparison; the formal quote will not come in below a sustainable project margin.",
            'nas' => "The {$name} is a {$brand} NAS for backup, file share and media workflows. Drive bays ship empty unless you add IronWolf / Red Pro disks on the same order. Urban Focus prices the chassis competitively and the disks as a separate, honest line.",
            'storage' => "The {$name} is enterprise or NAS-grade storage for servers and disk shelves. We sell current-generation capacities that procurement can write into a BOM. Confirm firmware and form factor against the host before you raise a PO.",
            'nic' => "The {$name} is a {$brand} server network adapter for 10GbE or 25GbE upgrades. Pair it with the correct DAC or optic. Urban Focus stocks the adapter as a specified upgrade part, not a grey-import lottery.",
            'rugged' => "The {$name} is a rugged {$brand} device for mining, logistics, municipal field teams and warehouses. It is built for dust, drop and outdoor use that kills a consumer laptop. Urban Focus quotes vehicle docks, extra batteries and 5G SKUs when the site needs them.",
            'av' => "The {$name} is a {$brand} Microsoft Teams / Zoom Rooms endpoint for South African boardrooms and huddle spaces. We quote the bar, tap, compute and display as a room, so the meeting experience is not a pile of unmatched parts.",
            'ai' => "The {$name} is an edge AI platform for inference, CCTV analytics and robotics prototypes. Urban Focus supplies it to integrators and labs that need a named NVIDIA or mini-PC SKU with a South African invoice.",
            default => "The {$name} is listed for South African business buyers who need a named {$brand} SKU, a VAT invoice and a supplier that will still answer after the parcel arrives.",
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function audienceParagraph(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'This product'));

        $mapped = $this->audienceLine($item);

        return "Specify the {$name} when {$mapped} Typical buyers include IT managers, systems integrators, quantity surveyors on ICT bills, and facilities teams refreshing a site. If you are replacing an older generation, send the existing serial or model and we will confirm drop-in fit, mounting and licence migration before you order.";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function audienceLine(array $item): string
    {
        $angle = mb_strtolower(trim((string) ($item['sales_angle'] ?? '')));
        $family = $this->family($item);

        $fromAngle = match (true) {
            str_contains($angle, 'government') || str_contains($angle, 'education') => 'the buyer is writing a government, municipal or education specification.',
            str_contains($angle, 'mining') || str_contains($angle, 'field') => 'the device will live in the field, on a mine, or on a vehicle rather than a desk.',
            str_contains($angle, 'boardroom') || str_contains($angle, 'meeting') => 'the room is used for Microsoft Teams or Zoom and must look and sound like a proper meeting space.',
            str_contains($angle, 'server') || str_contains($angle, 'data centre') || str_contains($angle, 'data-centre') => 'the load sits in a rack and needs enterprise support, not a desktop tower.',
            str_contains($angle, 'cad') || str_contains($angle, 'gis') || str_contains($angle, 'engineering') || str_contains($angle, 'video') => 'the user runs CAD, GIS, engineering or finishing software that needs workstation graphics.',
            str_contains($angle, 'failover') || str_contains($angle, 'isp') => 'the WAN must stay up when fibre fails or a branch has only cellular.',
            str_contains($angle, 'hospitality') || str_contains($angle, 'hotel') => 'the site is hospitality, residence or multi-dwelling and needs controller Wi-Fi.',
            str_contains($angle, 'farm') || str_contains($angle, 'construction') => 'power and fibre are limited and the camera or router must run off-grid or on 4G.',
            default => '',
        };

        if ($fromAngle !== '') {
            return $fromAngle;
        }

        return match ($family) {
            'laptop' => 'you need a current Windows 11 Pro business notebook for staff, not a consumer store special.',
            'workstation' => 'desk or field engineers need a mobile workstation rather than an office laptop.',
            'router' => 'a branch, vehicle or industrial site needs managed cellular or fibre routing.',
            'switch' => 'the closet needs managed switching and enough PoE or uplink speed for the access layer.',
            'access_point' => 'you are refreshing wireless for an office, warehouse, school or hotel.',
            'camera', 'nvr' => 'the site needs a named CCTV model on a professional design, not a kit-box camera.',
            'access' => 'doors or clock-in points need biometric or card control with an audit trail.',
            'ups' => 'servers, POS or network gear must ride through outages on a true online UPS.',
            'server' => 'you are standing up or refreshing a rack server for files, VMs or line-of-business apps.',
            'nas', 'storage' => 'backup and shared files need NAS-grade hardware and disks.',
            'rugged' => 'a consumer tablet will not survive the job site.',
            'av' => 'the meeting room must join Teams or Zoom with one touch.',
            'ai' => 'you are building on-device inference rather than sending every frame to the cloud.',
            default => 'you need a specified business IT product with local supply.',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function procurementParagraph(array $item): string
    {
        $brand = trim((string) ($item['brand'] ?? 'the OEM'));

        return "Urban Focus is a South African B2B IT supplier. We sell {$brand} and allied enterprise brands with a VAT invoice, quoted lead time and a single account team for laptops, networking, CCTV, UPS and servers. You are not buying a marketplace listing with no one to call. Prices on this page already include a buffer for Paystack card fees, EFT and bank receiving charges, plus a catalogue top-up so project work is not sold below a sustainable margin. Volume, public-sector and site-install work is quoted formally — we will not undercut the live price to the point where support disappears.";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function fulfilmentParagraph(array $item): string
    {
        $warranty = $this->warrantyLabel($item);
        $days = $this->deliveryDays($item);

        return "This model is available to order. Typical lead time is {$days} working days once the configuration is confirmed; specialised servers, rugged and display products can take longer if they ship from the vendor. {$warranty} Configuration (memory, storage, optics, disks, licences and mounting) is confirmed on the quote so the delivered unit matches the drawing. Nationwide courier is available. Collect or book installation through your Urban Focus account manager. Do not treat the web price as a promise of same-day Johannesburg stock on every SKU.";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function specList(array $item): string
    {
        $rows = $this->parsedSpecs($item);
        $rows['Brand'] = (string) ($item['brand'] ?? '—');
        $rows['SKU / model'] = (string) ($item['sku'] ?? '—');
        $rows['Warranty'] = $this->warrantyLabel($item);
        $rows['Supply'] = 'Available to order, South Africa';

        $lis = '';
        foreach ($rows as $label => $value) {
            $lis .= '<li><strong>'.e($label).':</strong> '.e($value).'</li>';
        }

        return '<ul>'.$lis.'</ul>';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    protected function parsedSpecs(array $item): array
    {
        $name = (string) ($item['name'] ?? '');
        $text = $name.' '.(string) ($item['short_description'] ?? '');
        $specs = [];

        if (preg_match('/\b(13|14|16|65|86)(?:-inch|\")?\b/i', $name, $m)) {
            $unit = in_array($m[1], ['65', '86'], true) ? 'inch display' : 'inch';
            $specs['Form factor'] = $m[1].'-'.$unit;
        }

        if (preg_match('/Core Ultra(?: 7)?|Ultra 7|U7-258V|M4|Ryzen|Xeon/i', $text, $m)) {
            $specs['Processor'] = trim($m[0]);
        }

        if (preg_match('/\b(\d+GB)\b/i', $name, $m) && ! preg_match('/orin 64/i', $name)) {
            $specs['Memory'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(\d+TB|\d+GB)\b.*(?:ssd|win|nas)?/i', $name, $m) && preg_match('/\b(\d+TB)\b/i', $name, $t)) {
            $specs['Storage'] = strtoupper($t[1]);
        } elseif (preg_match('/\b(512GB|1TB|2TB)\b/i', $name, $m)) {
            $specs['Storage'] = strtoupper($m[1]);
        }

        if (preg_match('/Win(?:dows)? 11 Pro/i', $text)) {
            $specs['Operating system'] = 'Windows 11 Pro';
        }

        if (preg_match('/\b(5G|LTE|4G)\b/i', $name, $m)) {
            $specs['Cellular'] = strtoupper($m[1]);
        }

        if (preg_match('/RTX|Iris|UHD/i', $name, $m)) {
            $specs['Graphics'] = $m[0];
        }

        if (preg_match('/(\d+)[- ]?(?:channel|ch)\b/i', $name, $m)) {
            $specs['Channels'] = $m[1];
        }

        if (preg_match('/(\d+)x|(\d+)-port|(\d+) port/i', $name, $m)) {
            $port = $m[1] ?: ($m[2] ?? $m[3] ?? '');
            if ($port !== '') {
                $specs['Ports'] = $port;
            }
        }

        if (preg_match('/(\d+)VA|(\d+)kVA/i', $name, $m)) {
            $specs['Capacity'] = $m[0];
        }

        if (preg_match('/(\d+)-Bay|(\d+) bay/i', $name, $m)) {
            $specs['Bays'] = ($m[1] ?? '').'-bay';
        }

        if (preg_match('/Wi-?Fi 7|Wi-?Fi 6/i', $name, $m)) {
            $specs['Wireless'] = $m[0];
        }

        if (preg_match('/\b(\d+(?:\.\d+)?(?:GbE|G))\b/i', $name, $m)) {
            $specs['Network speed'] = $m[1];
        }

        return $specs;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function oneLineSpec(array $item): string
    {
        $bits = $this->parsedSpecs($item);
        if ($bits === []) {
            return trim((string) ($item['short_description'] ?? ''));
        }

        $parts = [];
        foreach ($bits as $label => $value) {
            $parts[] = $label.' '.$value;
        }

        return implode(', ', array_slice($parts, 0, 4)).'.';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function professionalizeShort(string $short, array $item): string
    {
        $short = trim($short);
        $short = (string) preg_replace('/\s+/', ' ', $short);
        $short = rtrim($short, '.');

        if ($short === '') {
            return '';
        }

        return $short.'.';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function family(array $item): string
    {
        $hay = mb_strtolower(trim(($item['name'] ?? '').' '.($item['category_path'] ?? '').' '.($item['sku'] ?? '')));

        return match (true) {
            str_contains($hay, 'jetson') || str_contains($hay, 'minisforum') || str_contains($hay, 'iot-devices') => 'ai',
            str_contains($hay, 'rally') || str_contains($hay, 'meetingboard') || str_contains($hay, 'tap ip') || str_contains($hay, 'interactive-displays') => 'av',
            str_contains($hay, 'toughbook') || str_contains($hay, 'getac') || str_contains($hay, 'rugged') || str_contains($hay, 'tab active') || str_contains($hay, 'zebra') || str_contains($hay, 'oukitel') || str_contains($hay, 'ulefone') || str_contains($hay, 'warehouse') => 'rugged',
            str_contains($hay, 'x710') || str_contains($hay, 'xxv710') || str_contains($hay, 'network adapter') || str_contains($hay, 'sfp28') => 'nic',
            str_contains($hay, 'ironwolf') || str_contains($hay, 'red pro') || str_contains($hay, 'nvme') || str_contains($hay, 'hdd') => 'storage',
            str_contains($hay, 'nas') || str_contains($hay, 'ds1825') || str_contains($hay, 'ds925') || str_contains($hay, 'rs3626') || str_contains($hay, 'qnap') || str_contains($hay, 'rackstation') => 'nas',
            str_contains($hay, 'poweredge') || str_contains($hay, 'proliant') || str_contains($hay, 'thinksystem') || (str_contains($hay, 'desktops') && str_contains($hay, 'server')) => 'server',
            str_contains($hay, 'ups') || str_contains($hay, 'pdu') || str_contains($hay, 'ap9641') || str_contains($hay, 'ap8853') => 'ups',
            str_contains($hay, 'face') || str_contains($hay, 'biometric') || str_contains($hay, 'zkteco') || str_contains($hay, 'access controller') || str_contains($hay, 'access-control') || str_contains($hay, 'facial') => 'access',
            str_contains($hay, 'nvr') => 'nvr',
            str_contains($hay, 'camera') || str_contains($hay, 'anpr') || str_contains($hay, 'colorvu') || str_contains($hay, 'ptz') || str_contains($hay, 'cctv') || str_contains($hay, 'ip-cameras') => 'camera',
            str_contains($hay, 'access point') || str_contains($hay, 'eap') || str_contains($hay, 'u7') || str_contains($hay, 'gwn7670') || str_contains($hay, 'wifi 7') || str_contains($hay, 'rap73') || str_contains($hay, 'access-points') => 'access_point',
            str_contains($hay, 'switch') || str_contains($hay, 'crs') || str_contains($hay, 'switches') => 'switch',
            str_contains($hay, 'router') || str_contains($hay, 'rut') || str_contains($hay, 'trb') || str_contains($hay, 'chateau') || str_contains($hay, 'ccr') || str_contains($hay, 'peplink') || str_contains($hay, 'ur75') => 'router',
            str_contains($hay, 'zbook') || str_contains($hay, 'pro max 16') || str_contains($hay, 'p16') || str_contains($hay, 'proart') || str_contains($hay, 'workstation') => 'workstation',
            str_contains($hay, 'laptop') || str_contains($hay, 'thinkpad') || str_contains($hay, 'elitebook') || str_contains($hay, 'macbook') || str_contains($hay, 'omnibook') || str_contains($hay, 'expertbook') || str_contains($hay, 'latitude') || str_contains($hay, 'laptops') => 'laptop',
            default => 'general',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function familyLabel(array $item): string
    {
        return match ($this->family($item)) {
            'laptop' => 'business laptop',
            'workstation' => 'mobile workstation',
            'router' => 'business / industrial router',
            'switch' => 'managed network switch',
            'access_point' => 'enterprise wireless access point',
            'camera' => 'IP security camera',
            'nvr' => 'network video recorder',
            'access' => 'access control terminal',
            'ups' => 'online UPS / power protection',
            'server' => 'rack server',
            'nas' => 'network attached storage',
            'storage' => 'enterprise / NAS drive',
            'nic' => 'server network adapter',
            'rugged' => 'rugged computing device',
            'av' => 'meeting-room collaboration system',
            'ai' => 'edge AI computer',
            default => 'business IT product',
        };
    }

    protected function familyHook(string $family, array $item): string
    {
        $brand = trim((string) ($item['brand'] ?? ''));

        return match ($family) {
            'laptop' => "It is a current-generation {$brand} business laptop for staff who need Windows 11 Pro, memory headroom and a machine that procurement can standardise.",
            'workstation' => "Specify it where office laptops throttle under CAD, 3D or 4K timelines.",
            'router' => "Use it for primary 5G, LTE failover, industrial Ethernet or dual-SIM resilience.",
            'switch' => 'Use it as a specified closet or aggregation switch with a documented PoE and uplink plan.',
            'access_point' => 'Use it on a controller-managed wireless design, not as a standalone home extender.',
            'camera' => 'Use it on a professional CCTV design with a matching NVR and retention plan.',
            'nvr' => 'Size channels, disks and licences to the camera count before you raise the PO.',
            'access' => 'Pair it with the correct controller, lock power and enrolment process.',
            'ups' => 'Size it to the real watt load and required runtime — not the VA number on a brochure alone.',
            'server' => 'Treat the web price as a class starting point; the quote locks CPU, RAM, disks and support.',
            'nas' => 'Add NAS-qualified disks and a backup target on the same order.',
            'storage' => 'Confirm the drive is the NAS or enterprise family your RAID controller supports.',
            'nic' => 'Confirm slot, bracket and optic or copper standard before purchase.',
            'rugged' => 'Budget for the dock, stylus and extra battery if the crew works a full shift outdoors.',
            'av' => 'Design the room: display height, table tap, compute and acoustic treatment.',
            'ai' => 'Confirm power, cooling and camera or sensor I/O before you lock the SKU.',
            default => 'Ask Urban Focus for a written quote before you issue a purchase order.',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function warrantyLabel(array $item): string
    {
        $months = $this->warrantyMonths($item);
        $years = $months >= 12 ? ($months / 12).'-year' : $months.'-month';

        return "{$years} manufacturer-backed warranty (confirm on quote)";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function warrantyMonths(array $item): int
    {
        $path = (string) ($item['category_path'] ?? '');
        $name = (string) ($item['name'] ?? '');
        $text = mb_strtolower($path.' '.$name);

        if (str_contains($text, 'laptop') || str_contains($text, 'thinkpad') || str_contains($text, 'macbook') || str_contains($text, 'toughbook') || str_contains($path, 'warehouse')) {
            return 36;
        }

        if (str_contains($path, 'desktops') || str_contains($path, 'storage-devices') || str_contains($path, 'ups-systems') || str_contains($path, 'interactive-displays')) {
            return 24;
        }

        return 12;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function deliveryDays(array $item): int
    {
        $path = (string) ($item['category_path'] ?? '');

        if (str_contains($path, 'desktops') || str_contains($path, 'warehouse-technology')) {
            return 10;
        }

        return 7;
    }

    protected function p(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }
}
