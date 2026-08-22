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
        $sheet = $this->specSheet($item);
        $bits = [];
        foreach (['Processor', 'Graphics', 'Memory', 'Storage', 'Display', 'Cellular', 'Wireless', 'Capacity', 'Ports', 'Operating system'] as $key) {
            if (! empty($sheet['specs'][$key])) {
                $bits[] = $sheet['specs'][$key];
            }
        }

        if ($bits === []) {
            return Str::limit(trim((string) ($item['short_description'] ?? $item['name'] ?? '')), 220, '');
        }

        return Str::limit(implode(', ', array_slice($bits, 0, 6)).'.', 220, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaTitle(array $item): string
    {
        $name = trim((string) ($item['name'] ?? 'IT product'));

        return Str::limit($name.' | Urban Focus', 70, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaDescription(array $item): string
    {
        return Str::limit($this->shortDescription($item), 160, '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function metaKeywords(array $item): string
    {
        $sheet = $this->specSheet($item);
        $parts = array_filter(array_merge([
            (string) ($item['brand'] ?? ''),
            (string) ($item['sku'] ?? ''),
            (string) ($item['name'] ?? ''),
            $this->familyLabel($item),
        ], array_values($sheet['specs'])));

        return Str::limit(implode(', ', array_unique($parts)), 255, '');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public function specifications(array $item): array
    {
        $specs = $this->specSheet($item)['specs'];
        $specs['Brand'] = (string) ($item['brand'] ?? ($specs['Brand'] ?? ''));
        $specs['Model'] = (string) ($item['sku'] ?? ($specs['Model'] ?? ''));
        $specs['Category'] = $this->familyLabel($item);
        $specs['Warranty'] = $specs['Warranty'] ?? $this->warrantyLabel($item);
        $specs['Urban Focus range'] = 'Target catalogue';

        return array_filter($specs);
    }

    /**
     * Distrinode-style description: intro, advantages, suitable for, key specs.
     *
     * @param  array<string, mixed>  $item
     */
    public function descriptionHtml(array $item): string
    {
        $sheet = $this->specSheet($item);

        $suitable = '';
        foreach ($sheet['suitable_for'] as $line) {
            $suitable .= '<li>'.e($line).'</li>';
        }

        $keys = '';
        foreach ($sheet['specs'] as $label => $value) {
            $keys .= '<li><strong>'.e($label).':</strong> '.e($value).'</li>';
        }

        return implode("\n", [
            $this->p($sheet['intro']),
            '<h3>Advantages</h3>',
            $this->p($sheet['advantages']),
            '<h3>Suitable for</h3>',
            '<ul>'.$suitable.'</ul>',
            '<h3>Key specifications</h3>',
            '<ul>'.$keys.'</ul>',
            '<h3>Recommendations</h3>',
            $this->p($sheet['recommendations']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{intro: string, advantages: string, suitable_for: list<string>, specs: array<string, string>, recommendations: string}
     */
    public function specSheet(array $item): array
    {
        $specs = array_merge($this->familySpecs($item), $this->parsedSpecs($item), $this->skuSpecs((string) ($item['sku'] ?? '')));
        $suitable = $this->skuOverrides((string) ($item['sku'] ?? ''))['suitable_for'] ?? $this->familySuitable($item);

        return [
            'intro' => $this->intro($item, $specs),
            'advantages' => $this->advantages($item, $specs),
            'suitable_for' => $suitable,
            'specs' => $specs,
            'recommendations' => $this->recommendations($item, $specs),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $specs
     */
    protected function intro(array $item, array $specs): string
    {
        $name = trim((string) ($item['name'] ?? 'This model'));
        $family = $this->familyLabel($item);
        $lead = $this->hardwareLead($specs);

        return "The {$name} is a {$family} built around {$lead}. It is specified for professional use where the hardware configuration — not marketing copy — decides whether the unit belongs in the bill of materials.";
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $specs
     */
    protected function advantages(array $item, array $specs): string
    {
        $chunks = [];
        foreach ([
            'Processor' => 'processor',
            'Graphics' => 'graphics',
            'Memory' => 'memory',
            'Storage' => 'storage',
            'Display' => 'display',
            'Cellular' => 'cellular',
            'Wireless' => 'wireless',
            'Throughput' => 'throughput',
            'Capacity' => 'capacity',
            'Ports' => 'port layout',
            'PoE' => 'PoE budget',
            'Resolution' => 'imaging',
            'Channels' => 'channel count',
            'Protection' => 'rugged rating',
        ] as $key => $noun) {
            if (! empty($specs[$key])) {
                $chunks[] = $noun.' is '.$specs[$key];
            }
        }

        $os = ! empty($specs['Operating system']) ? ' Ships with '.$specs['Operating system'].'.' : '';
        $warranty = ! empty($specs['Warranty']) ? ' '.$specs['Warranty'].'.' : '';

        if ($chunks === []) {
            return trim((string) ($item['short_description'] ?? $item['name'] ?? '')).$os.$warranty;
        }

        $list = implode('; ', $chunks);

        return 'Hardware highlights: '.$list.'.'.$os.$warranty;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $specs
     */
    protected function recommendations(array $item, array $specs): string
    {
        $name = trim((string) ($item['name'] ?? 'this model'));

        return match ($this->family($item)) {
            'laptop', 'workstation' => "Confirm CPU, memory, storage and graphics against the RFQ before ordering. Pair the {$name} with the OEM dock and a matching power adapter if the user runs dual displays.",
            'router' => "Confirm cellular bands, SIM count, Ethernet speed and power input. Add antennas and a DIN-rail kit if the {$name} is cabinet-mounted.",
            'switch' => 'Confirm port speed, PoE class and uplink optics. Order transceivers and patching with the switch so the closet is complete.',
            'access_point' => 'Confirm controller family, PoE class and mounting. Plan switch PoE budget before you hang the AP.',
            'camera' => 'Confirm lens, IR / colour-at-night mode and NVR compatibility. Add a junction box and the matching cable run.',
            'nvr' => 'Size HDD bays and licences to the camera count. The recorder ships without disks unless they are on the same order.',
            'access' => 'Confirm reader protocol, lock power and controller doors. Enrolment and software licences are specified on quote.',
            'ups' => 'Size VA/watts to the measured load and required runtime. Add a network card and PDU if the rack must be managed remotely.',
            'server' => 'CPU, RAM, disks, RAID and support pack are configuration items. The listed unit is a class starting point — lock the BOM on the quote.',
            'nas' => 'The chassis is typically diskless. Add NAS-qualified HDDs/SSDs and a backup target on the same order.',
            'storage' => 'Confirm form factor, interface and NAS/enterprise firmware against the host RAID or NAS.',
            'nic' => 'Confirm PCIe slot, bracket and copper vs SFP. Order the matching DAC or optic.',
            'rugged' => 'Confirm IP/MIL rating, cellular SKU and dock. Add a spare battery for a full field shift.',
            'av' => 'Confirm room size, display and Teams/Zoom compute. Quote the bar, tap and mount as one room.',
            'ai' => 'Confirm memory, power and camera/sensor I/O. This is a developer / inference platform, not a finished appliance.',
            default => "Confirm the exact {$name} configuration against the manufacturer datasheet before you raise a PO.",
        };
    }

    /**
     * @param  array<string, string>  $specs
     */
    protected function hardwareLead(array $specs): string
    {
        $parts = [];
        foreach (['Processor', 'Graphics', 'Memory', 'Storage', 'Display', 'Cellular', 'Capacity', 'Ports'] as $key) {
            if (! empty($specs[$key])) {
                $parts[] = $specs[$key];
            }
        }

        return $parts !== [] ? implode(', ', array_slice($parts, 0, 4)) : 'a current professional configuration';
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    protected function familySpecs(array $item): array
    {
        $warranty = $this->warrantyLabel($item);

        return match ($this->family($item)) {
            'laptop' => array_filter([
                'Product type' => 'Business laptop',
                'Operating system' => 'Windows 11 Pro',
                'Wireless' => 'Wi-Fi 6E / Wi-Fi 7, Bluetooth',
                'Security' => 'TPM 2.0, firmware security',
                'Keyboard' => 'Spill-resistant, backlit',
                'Warranty' => $warranty,
            ]),
            'workstation' => [
                'Product type' => 'Mobile workstation',
                'Operating system' => 'Windows 11 Pro',
                'Graphics' => 'NVIDIA RTX professional GPU',
                'ISV' => 'CAD / DCC certification on quote',
                'Wireless' => 'Wi-Fi 6E / Wi-Fi 7, Bluetooth',
                'Security' => 'TPM 2.0',
                'Warranty' => $warranty,
            ],
            'router' => [
                'Product type' => 'Industrial / enterprise router',
                'Management' => 'Remote management / RMS or controller',
                'Installation' => 'DIN-rail or desktop, site dependent',
                'Warranty' => $warranty,
            ],
            'switch' => [
                'Product type' => 'Managed network switch',
                'Layer' => 'Layer 2 / Layer 3 features by model',
                'Warranty' => $warranty,
            ],
            'access_point' => [
                'Product type' => 'Enterprise wireless access point',
                'Wireless' => 'Wi-Fi 7',
                'Power' => '802.3at/bt PoE',
                'Mounting' => 'Ceiling / wall',
                'Warranty' => $warranty,
            ],
            'camera' => [
                'Product type' => 'IP security camera',
                'Compression' => 'H.265 / H.264',
                'Power' => 'PoE',
                'Warranty' => $warranty,
            ],
            'nvr' => [
                'Product type' => 'Network video recorder',
                'Compression' => 'H.265 / H.264',
                'Warranty' => $warranty,
            ],
            'access' => [
                'Product type' => 'Access control / biometric terminal',
                'Authentication' => 'Face / fingerprint / card by model',
                'Warranty' => $warranty,
            ],
            'ups' => [
                'Product type' => 'Online double-conversion UPS',
                'Form factor' => 'Rack / tower convertible by model',
                'Warranty' => $warranty,
            ],
            'server' => [
                'Product type' => 'Rack server',
                'Form factor' => str_contains(mb_strtolower((string) $item['name']), '2u') || str_contains(mb_strtolower((string) $item['name']), 'r760') || str_contains(mb_strtolower((string) $item['name']), 'dl380') ? '2U rack' : '1U rack',
                'Management' => 'iDRAC / iLO / XClarity',
                'Warranty' => $warranty,
            ],
            'nas' => [
                'Product type' => 'Network attached storage',
                'Drives' => 'Diskless chassis unless disks are ordered',
                'Warranty' => $warranty,
            ],
            'storage' => [
                'Product type' => 'NAS / enterprise drive',
                'Use' => '24/7 NAS or server duty cycle',
                'Warranty' => $warranty,
            ],
            'nic' => [
                'Product type' => 'Server network adapter',
                'Interface' => 'PCIe',
                'Warranty' => $warranty,
            ],
            'rugged' => [
                'Product type' => 'Rugged laptop / tablet',
                'Protection' => 'IP65 / MIL-STD class (confirm SKU)',
                'Cellular' => str_contains(mb_strtolower((string) $item['name']), '5g') ? '5G' : (str_contains(mb_strtolower((string) $item['name']), 'lte') ? 'LTE' : 'Optional WWAN'),
                'Warranty' => $warranty,
            ],
            'av' => [
                'Product type' => 'Meeting-room collaboration system',
                'Platform' => 'Microsoft Teams Rooms / Zoom Rooms',
                'Warranty' => $warranty,
            ],
            'ai' => [
                'Product type' => 'Edge AI computer',
                'Use' => 'On-device inference, vision, robotics',
                'Warranty' => $warranty,
            ],
            default => [
                'Product type' => $this->familyLabel($item),
                'Warranty' => $warranty,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function familySuitable(array $item): array
    {
        return match ($this->family($item)) {
            'laptop' => [
                'Corporate fleets standardised on Windows 11 Pro',
                'Knowledge workers who need 16–32 GB RAM and NVMe storage',
                'Field and office staff who need a named OEM business SKU',
            ],
            'workstation' => [
                'Engineers and architects using CAD/CAM or BIM',
                'GIS, simulation and 3D visualisation',
                'Video finishing and colour-critical creative work',
                'Users who need ISV-certified NVIDIA RTX graphics',
            ],
            'router' => [
                'Primary 5G / LTE WAN where fibre is unavailable',
                'Automatic failover for branch and industrial sites',
                'Vehicle, cabinet and DIN-rail installations',
            ],
            'switch' => [
                'Access and aggregation switching in office closets',
                'PoE for Wi-Fi 6/7 access points and IP cameras',
                'Multi-gig uplinks for campus and SMB cores',
            ],
            'access_point' => [
                'Controller-managed office and campus Wi-Fi',
                'Hospitality, education and multi-dwelling wireless',
                'High-density meeting rooms and open plan floors',
            ],
            'camera' => [
                'Commercial and campus CCTV designs',
                'Perimeter, parking and ANPR approaches',
                'Sites that specify a named OEM camera on the drawing',
            ],
            'nvr' => [
                'Multi-camera commercial recorder rooms',
                'AcuSense / AI analytics deployments',
                'Projects that need a matching OEM NVR and camera pack',
            ],
            'access' => [
                'Office and school time-and-attendance',
                'Door control with face, fingerprint or card',
                'Sites that need an audit trail on entry',
            ],
            'ups' => [
                'Server rooms and network closets',
                'POS and comms rooms that must ride through outages',
                'Racks that need managed, double-conversion power',
            ],
            'server' => [
                'Virtualisation and line-of-business applications',
                'File, print and domain services',
                'SME and enterprise rack rooms',
            ],
            'nas' => [
                'Central file share and backup',
                'Media and project storage for SMEs',
                'Snapshot / replication workflows',
            ],
            'storage' => [
                'NAS bay upgrades (IronWolf / Red Pro class)',
                'Server and RAID shelf expansion',
                '24/7 duty-cycle storage',
            ],
            'nic' => [
                '10GbE / 25GbE server upgrades',
                'Hypervisor hosts that need extra throughput',
                'Copper RJ45 or SFP28 uplinks',
            ],
            'rugged' => [
                'Mining, utilities and field service',
                'Warehouse and logistics scanning',
                'Municipal and defence-adjacent outdoor use',
            ],
            'av' => [
                'Microsoft Teams Rooms and Zoom Rooms',
                'Boardrooms and huddle spaces',
                'One-touch join with a table controller',
            ],
            'ai' => [
                'On-device video analytics',
                'Robotics and industrial inference',
                'Edge AI prototypes and developer kits',
            ],
            default => [
                'Professional IT bills of materials',
                'Named OEM replacements',
            ],
        };
    }

    /**
     * Known manufacturer-style specs for catalogue SKUs.
     *
     * @return array<string, string>
     */
    protected function skuSpecs(string $sku): array
    {
        return match ($sku) {
            'DB14250-U7-32-1T' => [
                'Processor' => 'Intel Core Ultra 7',
                'Memory' => '32GB',
                'Storage' => '1TB NVMe SSD',
                'Display' => '14-inch business panel',
                'Operating system' => 'Windows 11 Pro',
                'Product type' => 'Business laptop',
            ],
            'DB16250-U7-32-1T' => [
                'Processor' => 'Intel Core Ultra 7',
                'Memory' => '32GB',
                'Storage' => '1TB NVMe SSD',
                'Display' => '16-inch business panel',
                'Operating system' => 'Windows 11 Pro',
            ],
            'PA13250-U7-32-1T' => [
                'Processor' => 'Intel Core Ultra',
                'Memory' => '32GB',
                'Storage' => '1TB NVMe SSD',
                'Display' => '13-inch premium panel',
                'Operating system' => 'Windows 11 Pro',
            ],
            'PM16250-RTX' => [
                'Processor' => 'Intel Core Ultra workstation CPU',
                'Graphics' => 'NVIDIA RTX professional GPU',
                'Display' => '16-inch workstation panel',
                'Operating system' => 'Windows 11 Pro',
                'Product type' => 'Mobile workstation',
            ],
            'EBX-G1I-14-5G' => [
                'Processor' => 'Intel Core Ultra 7',
                'Memory' => '32GB',
                'Storage' => '1TB NVMe SSD',
                'Display' => '14-inch',
                'Cellular' => '5G WWAN',
                'Operating system' => 'Windows 11 Pro',
            ],
            'EB8-G1I-14' => [
                'Processor' => 'Intel Core Ultra 7',
                'Memory' => '32GB',
                'Storage' => '1TB NVMe SSD',
                'Display' => '14-inch',
                'Operating system' => 'Windows 11 Pro',
            ],
            'AD3U3ET' => [
                'Processor' => 'Intel Core Ultra 7 258V',
                'Memory' => '32GB',
                'Storage' => '2TB NVMe SSD',
                'Display' => '16-inch',
                'Operating system' => 'Windows 11 Pro',
                'AI' => 'NPU / Copilot+ class',
            ],
            'ZBF-G1I-16-5G' => [
                'Product type' => 'Mobile workstation',
                'Display' => '16-inch',
                'Cellular' => '5G WWAN',
                'Graphics' => 'NVIDIA RTX workstation GPU (config)',
                'Operating system' => 'Windows 11 Pro',
            ],
            'ZBP-32-RTX-1T' => [
                'Memory' => '32GB',
                'Graphics' => 'NVIDIA RTX',
                'Storage' => '1TB NVMe SSD',
                'Operating system' => 'Windows 11 Pro',
                'Product type' => 'Mobile workstation',
            ],
            'OBU-FLIP14-OLED' => [
                'Display' => '14-inch OLED touch, convertible',
                'Form factor' => '360° flip',
                'Operating system' => 'Windows 11',
            ],
            '21QC001HZA' => [
                'Processor' => 'Intel Core Ultra 7',
                'Memory' => '16GB',
                'Storage' => '1TB NVMe SSD',
                'Display' => '14-inch',
                'Wireless' => 'Wi-Fi (no WWAN)',
                'Operating system' => 'Windows 11 Pro',
            ],
            '21QC000YZA' => [
                'Processor' => 'Intel Core Ultra 7',
                'Memory' => '16GB',
                'Storage' => '512GB NVMe SSD',
                'Display' => '14-inch',
                'Cellular' => 'LTE / 5G WWAN',
                'Operating system' => 'Windows 11 Pro',
            ],
            'L14G6-U5-16-512' => [
                'Display' => '14-inch',
                'Operating system' => 'Windows 11 Pro',
                'Product type' => 'ThinkPad L-series business laptop',
            ],
            'L16G2-LTE' => [
                'Display' => '16-inch',
                'Cellular' => 'LTE WWAN',
                'Operating system' => 'Windows 11 Pro',
            ],
            'X1C-G13' => [
                'Display' => '14-inch (X1 Carbon class)',
                'Product type' => 'Ultraportable business laptop',
                'Operating system' => 'Windows 11 Pro',
                'Weight' => 'Ultralight carbon chassis',
            ],
            'P16-RTX' => [
                'Processor' => 'Intel Core Ultra / Xeon workstation CPU (config)',
                'Graphics' => 'NVIDIA RTX professional GPU',
                'Memory' => 'Workstation SODIMM, expandable',
                'Storage' => 'NVMe SSD (config)',
                'Display' => '16.0-inch IPS workstation panel',
                'Operating system' => 'Windows 11 Pro',
                'Keyboard' => 'ThinkPad backlit, spill-resistant',
                'Security' => 'dTPM 2.0, optional fingerprint, Kensington',
                'ISV' => 'CAD / CAM / DCC certifications on quote',
                'Product type' => 'Mobile workstation',
            ],
            'P5-ULTRA' => [
                'Processor' => 'Intel Core Ultra',
                'Operating system' => 'Windows 11 Pro',
                'Product type' => 'ASUS ExpertBook business laptop',
            ],
            'PROART-P16-RTX' => [
                'Display' => '16-inch creator panel',
                'Graphics' => 'NVIDIA RTX',
                'Product type' => 'Creative mobile workstation',
            ],
            'MBP14-M4PRO' => [
                'Processor' => 'Apple M-series Pro',
                'Display' => '14.2-inch Liquid Retina XDR',
                'Operating system' => 'macOS',
                'Wireless' => 'Wi-Fi 6E, Bluetooth',
            ],
            'MBP16-M4PRO' => [
                'Processor' => 'Apple M-series Pro',
                'Display' => '16.2-inch Liquid Retina XDR',
                'Operating system' => 'macOS',
            ],
            'RUTX50' => [
                'Cellular' => '5G SA/NSA, LTE Cat 20',
                'SIM' => 'Dual SIM',
                'Ethernet' => '5 × Gigabit (configurable WAN/LAN)',
                'Wireless' => 'Wi-Fi 5 (802.11ac) dual-band',
                'GNSS' => 'GPS / GLONASS / BeiDou / Galileo',
                'I/O' => 'Digital I/O, USB',
                'Management' => 'RutOS, RMS remote management',
                'Power' => 'Wide-range DC, industrial',
                'Installation' => 'DIN-rail / compact industrial',
            ],
            'RUTM50' => [
                'Cellular' => '5G industrial',
                'SIM' => 'Dual SIM',
                'Ethernet' => 'Gigabit WAN/LAN',
                'Management' => 'RutOS / RMS',
            ],
            'RUT976' => [
                'Cellular' => '5G RedCap',
                'Product type' => 'Industrial IoT router',
                'SIM' => 'Dual SIM',
            ],
            'TRB500' => [
                'Cellular' => '5G',
                'Product type' => 'Compact 5G gateway',
                'Ethernet' => 'Gigabit',
            ],
            'UR75' => [
                'Cellular' => '5G',
                'SIM' => 'Dual SIM',
                'GNSS' => 'GPS',
                'Product type' => 'Industrial 5G router',
            ],
            'MAX-BR1-PRO-5G' => [
                'Cellular' => '5G',
                'WAN' => 'SpeedFusion / multi-WAN',
                'Ethernet' => 'Gigabit',
                'Product type' => 'Enterprise 5G failover router',
            ],
            'S53UG-5HAXD2HAXD' => [
                'Cellular' => '5G',
                'Wireless' => 'Wi-Fi 6',
                'OS' => 'RouterOS',
                'Product type' => '5G CPE / branch router',
            ],
            'RB-CCR2004SP' => [
                'Ports' => '1 × GbE, 12 × SFP+, 2 × SFP28',
                'OS' => 'RouterOS',
                'Product type' => 'Cloud Core Router',
            ],
            'RB-CCR2116P' => [
                'Ports' => '12 × GbE, 4 × SFP+',
                'CPU' => '16-core AL73400 class',
                'OS' => 'RouterOS',
            ],
            'RB-CCR2216SP' => [
                'Ports' => '1 × GbE, 12 × 25G SFP28, 2 × 100G QSFP28',
                'OS' => 'RouterOS',
                'Product type' => 'Data-centre Cloud Core Router',
            ],
            'CRS326-24S+2Q+RM' => [
                'Ports' => '24 × SFP+, 2 × QSFP+',
                'Form factor' => '1U rack',
                'OS' => 'SwOS / RouterOS',
            ],
            'CRS518-16XS-2XQ-RM' => [
                'Ports' => '16 × 25G SFP28, 2 × 100G QSFP28',
                'Form factor' => '1U rack',
            ],
            'U7-PRO' => [
                'Wireless' => 'Wi-Fi 7 (802.11be)',
                'Bands' => 'Tri-band',
                'Ethernet' => '2.5GbE',
                'Power' => 'PoE+',
                'Platform' => 'UniFi Network',
            ],
            'U7-PRO-MAX' => [
                'Wireless' => 'Wi-Fi 7 (802.11be)',
                'Bands' => 'Tri-band, high-density radios',
                'Ethernet' => '2.5GbE',
                'Power' => 'PoE+',
                'Platform' => 'UniFi Network',
            ],
            'U7-ENTERPRISE' => [
                'Wireless' => 'Wi-Fi 7',
                'Ethernet' => '10GbE class (model dependent)',
                'Platform' => 'UniFi Network',
            ],
            'USW-PRO-MAX-24-POE' => [
                'Ports' => '24 × multi-gig / GbE PoE',
                'Uplinks' => 'SFP+',
                'PoE' => 'UniFi Pro Max PoE budget',
                'Platform' => 'UniFi Network',
            ],
            'USW-E24XG' => [
                'Ports' => '24 × 10GbE',
                'Product type' => 'Enterprise 10GbE switch',
                'Platform' => 'UniFi Network',
            ],
            'EAP723' => [
                'Wireless' => 'Wi-Fi 7',
                'Platform' => 'Omada SDN',
                'Power' => 'PoE',
            ],
            'EAP773' => [
                'Wireless' => 'Wi-Fi 7',
                'Platform' => 'Omada SDN',
                'Power' => 'PoE',
            ],
            'EAP783' => [
                'Wireless' => 'Wi-Fi 7 high-density',
                'Platform' => 'Omada SDN',
                'Power' => 'PoE',
            ],
            'SG3428XPP-M2' => [
                'Ports' => '24 × 2.5GbE PoE+',
                'Uplinks' => '10G SFP+',
                'Platform' => 'Omada SDN',
            ],
            'GWN7670' => [
                'Wireless' => 'Wi-Fi 7',
                'Platform' => 'GWN Manager / GDMS',
                'Power' => 'PoE',
            ],
            'GWN7811P' => [
                'Ports' => '8 × 2.5GbE PoE',
                'Management' => 'Layer 2+ managed',
            ],
            'RG-ES218GS-P' => [
                'Ports' => '2.5GbE PoE smart managed',
                'Platform' => 'Reyee Cloud',
            ],
            'RG-RAP73-PRO' => [
                'Wireless' => 'Wi-Fi 7',
                'Mounting' => 'Ceiling',
                'Platform' => 'Reyee Cloud',
            ],
            'IDS-2CD7A46G0-XZHSY' => [
                'Resolution' => '4MP',
                'Analytics' => 'DeepinView ANPR',
                'Form factor' => 'Bullet',
                'Power' => 'PoE',
            ],
            'DS-2CD2387G2-LU' => [
                'Resolution' => '8MP',
                'Imaging' => 'ColorVu 24/7 colour',
                'Analytics' => 'AcuSense',
                'Power' => 'PoE',
            ],
            'DS-2CD2386G2-ISU' => [
                'Resolution' => '8MP',
                'Analytics' => 'AcuSense human/vehicle',
                'Power' => 'PoE',
            ],
            'DS-7732NXI-I4/16P/S' => [
                'Channels' => '32',
                'PoE' => '16-port PoE on this SKU family',
                'Analytics' => 'AcuSense',
                'Form factor' => '1U / desktop NVR',
            ],
            'DS-9664NXI-I8/S' => [
                'Channels' => '64',
                'Analytics' => 'AI / AcuSense',
                'Bays' => 'Up to 8 HDD',
            ],
            'DS-2DE7A425IW-AEB' => [
                'Optics' => '25× optical zoom',
                'Imaging' => 'DarkFighter',
                'Form factor' => 'PTZ',
            ],
            'DS-2TD2617-10' => [
                'Imaging' => 'Thermal + optical bi-spectrum',
                'Use' => 'Perimeter / specialist detection',
            ],
            'IPC2124SB-ADF40KMC-I0' => [
                'Resolution' => '4MP Starlight',
                'Analytics' => 'ANPR',
                'Power' => 'PoE',
            ],
            'IPC3618SB-ADF28KMC-I0' => [
                'Resolution' => '8MP',
                'Imaging' => 'ColorHunter',
                'Analytics' => 'AI',
            ],
            'P3265-LVE' => [
                'Resolution' => '2MP / Full HD class',
                'Form factor' => 'Outdoor dome, IK10',
                'Analytics' => 'AXIS Object Analytics',
                'Power' => 'PoE',
            ],
            'Q6135-LE' => [
                'Form factor' => 'Outdoor PTZ',
                'Imaging' => 'Lightfinder / Forensic WDR',
                'Power' => 'PoE / 24 V',
            ],
            'UF-SOLAR-AI-4G-KIT' => [
                'Power' => 'Solar + battery',
                'Connectivity' => '4G LTE',
                'Analytics' => 'AI human/vehicle',
                'Use' => 'Off-grid sites',
            ],
            'DS-K1T673DWX' => [
                'Authentication' => 'Face recognition',
                'Display' => 'Touch terminal',
                'Interfaces' => 'Wiegand / network',
            ],
            'DS-K2604T' => [
                'Doors' => '4-door controller class',
                'Authentication' => 'Biometric / card via readers',
            ],
            'MB560-VL' => [
                'Authentication' => 'Face + fingerprint',
                'Use' => 'Time and attendance / access',
            ],
            'SRT3000XLI' => [
                'Capacity' => '3000VA / ~2700W class',
                'Topology' => 'Online double-conversion',
                'Form factor' => 'Rack / tower',
                'Output' => '230V IEC',
            ],
            'SRT5000XLI' => [
                'Capacity' => '5000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => 'Rack / tower',
            ],
            'SRT6KXLI' => [
                'Capacity' => '6000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => 'Rack / tower',
            ],
            '9PX3000RT' => [
                'Capacity' => '3000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => '2U rack',
            ],
            '9PX5KIRTN' => [
                'Capacity' => '5000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => 'Rack',
            ],
            'GXT5-3000IRT2UXL' => [
                'Capacity' => '3000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => '2U rack',
            ],
            'RCT-OL3000-RM' => [
                'Capacity' => '3000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => 'Rackmount',
            ],
            'RCT-OL6000-RM' => [
                'Capacity' => '6000VA',
                'Topology' => 'Online double-conversion',
                'Form factor' => 'Rackmount',
            ],
            'AP9641' => [
                'Product type' => 'UPS network management card',
                'Interface' => 'Ethernet, HTTPS/SNMP',
            ],
            'AP8853' => [
                'Product type' => 'Metered rack PDU',
                'Monitoring' => 'Remote kWh / outlet metering',
            ],
            'R360-ENTRY' => [
                'Form factor' => '1U',
                'Product type' => 'Entry PowerEdge rack server',
                'Management' => 'iDRAC',
            ],
            'R660-CLASS' => [
                'Form factor' => '1U',
                'Product type' => 'Enterprise PowerEdge',
                'Management' => 'iDRAC',
            ],
            'R760-CLASS' => [
                'Form factor' => '2U',
                'Product type' => 'Virtualisation-class PowerEdge',
                'Management' => 'iDRAC',
            ],
            'DL360-GEN11' => [
                'Form factor' => '1U',
                'Management' => 'iLO',
            ],
            'DL380-GEN11' => [
                'Form factor' => '2U',
                'Management' => 'iLO',
            ],
            'SR630-V3' => [
                'Form factor' => '1U',
                'Management' => 'XClarity',
            ],
            'RS3626XS+' => [
                'Bays' => '12-bay rack',
                'Product type' => 'Synology RackStation',
                'Drives' => 'Diskless',
            ],
            'DS1825+' => [
                'Bays' => '8-bay desktop',
                'Drives' => 'Diskless',
            ],
            'DS925+' => [
                'Bays' => '4-bay desktop',
                'Drives' => 'Diskless',
            ],
            'TS-873A' => [
                'Bays' => '8-bay',
                'Network' => '10GbE',
                'Drives' => 'Diskless',
            ],
            'ST20000NT001' => [
                'Capacity' => '20TB',
                'Class' => 'IronWolf Pro NAS HDD',
                'Interface' => 'SATA 6Gb/s',
                'Use' => '24/7 NAS',
            ],
            'WD221KFGX' => [
                'Capacity' => '22TB',
                'Class' => 'WD Red Pro NAS HDD',
                'Interface' => 'SATA 6Gb/s',
            ],
            'MZQL23T8HCLS' => [
                'Capacity' => '3.84TB',
                'Interface' => 'NVMe',
                'Class' => 'Samsung enterprise SSD',
            ],
            'X710-T2L' => [
                'Speed' => '10GbE',
                'Ports' => '2 × RJ45',
                'Interface' => 'PCIe',
            ],
            'XXV710-DA2' => [
                'Speed' => '25GbE',
                'Ports' => '2 × SFP28',
                'Interface' => 'PCIe',
            ],
            'CF-40-5G' => [
                'Cellular' => '5G',
                'Protection' => 'Fully rugged MIL-STD / IP',
                'Product type' => 'Rugged laptop',
            ],
            'FZ-G2-5G' => [
                'Cellular' => '5G',
                'Form factor' => 'Rugged tablet',
                'Protection' => 'MIL-STD / IP',
            ],
            'S510' => [
                'Product type' => 'Rugged laptop',
                'Protection' => 'MIL-STD / IP',
            ],
            'F110' => [
                'Product type' => 'Rugged tablet',
                'Protection' => 'MIL-STD / IP',
            ],
            'SM-X306B' => [
                'Cellular' => '5G',
                'OS' => 'Android (enterprise)',
                'Protection' => 'IP68, MIL-STD',
            ],
            'ET65' => [
                'OS' => 'Android',
                'Product type' => 'Rugged warehouse tablet',
                'Scanning' => 'Enterprise barcode / optional sled',
            ],
            'RT8', 'ARMOR-PAD-5G' => [
                'Product type' => 'Rugged tablet',
                'Cellular' => 'LTE / 5G',
                'Protection' => 'IP68 / MIL-STD class',
            ],
            'RALLY-BAR' => [
                'Camera' => 'PTZ Rally Bar optics',
                'Audio' => 'Integrated beamforming mics / speakers',
                'Platform' => 'Teams Rooms / Zoom Rooms',
                'Room' => 'Medium / large boardroom',
            ],
            'RALLY-BAR-MINI' => [
                'Camera' => 'Rally Bar Mini optics',
                'Platform' => 'Teams Rooms / Zoom Rooms',
                'Room' => 'Huddle / small meeting room',
            ],
            'TAP-IP' => [
                'Product type' => 'Table touch controller',
                'Network' => 'PoE Ethernet',
                'Platform' => 'Teams / Zoom rooms',
            ],
            'MB65' => [
                'Display' => '65-inch 4K interactive',
                'Platform' => 'Yealink MeetingBoard / Teams',
            ],
            'MB86' => [
                'Display' => '86-inch 4K interactive',
                'Platform' => 'Yealink MeetingBoard / Teams',
            ],
            'MS-AIX1-PRO' => [
                'Product type' => 'Edge AI mini PC',
                'Use' => 'Local LLM / vision inference',
            ],
            'JETSON-AGX-ORIN-64' => [
                'Memory' => '64GB',
                'Platform' => 'NVIDIA Jetson AGX Orin',
                'Use' => 'CUDA / TensorRT inference',
            ],
            default => [],
        };
    }

    /**
     * @return array{suitable_for?: list<string>}
     */
    protected function skuOverrides(string $sku): array
    {
        return match ($sku) {
            'P16-RTX' => [
                'suitable_for' => [
                    'Engineers and architects using CAD/CAM software',
                    'Data, GIS and simulation workloads',
                    'Video editors and 3D visualisers',
                    'Professionals who need ISV-certified RTX graphics',
                ],
            ],
            default => [],
        };
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
            $specs['Display'] = in_array($m[1], ['65', '86'], true)
                ? $m[1].'-inch display'
                : $m[1].'-inch';
        }

        if (preg_match('/Core Ultra(?: 7)?(?:\s+265HX)?|Ultra 7(?:\s+258V)?|U7-258V|M4\s*Pro|Apple M-series/i', $text, $m)) {
            $specs['Processor'] = trim($m[0]);
        }

        if (preg_match('/\b(\d+GB)\b/i', $name, $m) && ! preg_match('/orin 64|rtx/i', $name)) {
            $specs['Memory'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(512GB|1TB|2TB)\b/i', $name, $m)) {
            $specs['Storage'] = strtoupper($m[1]).(str_contains(mb_strtolower($name), 'hdd') ? ' HDD' : ' SSD');
        }

        if (preg_match('/Win(?:dows)? 11 Pro/i', $text)) {
            $specs['Operating system'] = 'Windows 11 Pro';
        }

        if (preg_match('/\b(5G|LTE|4G)\b/i', $name, $m)) {
            $specs['Cellular'] = strtoupper($m[1]);
        }

        if (preg_match('/RTX(?:\s+\d+\s+Ada)?/i', $name, $m)) {
            $specs['Graphics'] = $m[0];
        }

        if (preg_match('/(\d+)[- ]?(?:channel|ch)\b/i', $name, $m)) {
            $specs['Channels'] = $m[1];
        }

        if (preg_match('/(\d+)-port|(\d+) port|(\d+) ×/i', $name, $m)) {
            $port = $m[1] ?: ($m[2] ?? $m[3] ?? '');
            if ($port !== '') {
                $specs['Ports'] = $port.'-port';
            }
        }

        if (preg_match('/(\d+\s*k?VA)/i', $name, $m)) {
            $specs['Capacity'] = $m[1];
        }

        if (preg_match('/(\d+)-Bay|(\d+) bay/i', $name, $m)) {
            $specs['Bays'] = ($m[1] ?? '').'-bay';
        }

        if (preg_match('/Wi-?Fi 7|Wi-?Fi 6E|Wi-?Fi 6/i', $name, $m)) {
            $specs['Wireless'] = $m[0];
        }

        if (preg_match('/\b(\d+(?:\.\d+)?GbE)\b/i', $name, $m)) {
            $specs['Network speed'] = $m[1];
        }

        return $specs;
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
            'router' => 'industrial router',
            'switch' => 'managed network switch',
            'access_point' => 'enterprise wireless access point',
            'camera' => 'IP security camera',
            'nvr' => 'network video recorder',
            'access' => 'access control terminal',
            'ups' => 'online UPS',
            'server' => 'rack server',
            'nas' => 'network attached storage',
            'storage' => 'NAS / enterprise drive',
            'nic' => 'server network adapter',
            'rugged' => 'rugged computing device',
            'av' => 'meeting-room system',
            'ai' => 'edge AI computer',
            default => 'professional IT product',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function warrantyLabel(array $item): string
    {
        $months = $this->warrantyMonths($item);

        return $months >= 12
            ? ((int) ($months / 12)).'-year manufacturer warranty'
            : $months.'-month manufacturer warranty';
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

    protected function p(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }
}
