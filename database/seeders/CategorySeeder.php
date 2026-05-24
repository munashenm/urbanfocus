<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            [
                'name' => 'Laptops & Notebooks',
                'slug' => 'laptops-notebooks',
                'description' => 'Business laptops, notebooks and mobile workstations for corporate and education.',
                'children' => [
                    ['name' => 'Business Laptops', 'slug' => 'business-laptops'],
                    ['name' => 'Gaming Laptops', 'slug' => 'gaming-laptops'],
                    ['name' => 'Chromebooks', 'slug' => 'chromebooks'],
                    ['name' => 'Laptop Bags & Cases', 'slug' => 'laptop-bags'],
                ],
            ],
            [
                'name' => 'Desktops & Workstations',
                'slug' => 'desktops',
                'description' => 'Desktop PCs, all-in-ones and professional workstations.',
                'children' => [
                    ['name' => 'Business Desktops', 'slug' => 'business-desktops'],
                    ['name' => 'Gaming Desktops', 'slug' => 'gaming-desktops'],
                    ['name' => 'All-in-One PCs', 'slug' => 'all-in-one'],
                    ['name' => 'Mini PCs', 'slug' => 'mini-pcs'],
                ],
            ],
            [
                'name' => 'Monitors & Displays',
                'slug' => 'monitors-displays',
                'description' => 'Office monitors, gaming displays and commercial signage.',
                'children' => [
                    ['name' => 'Office Monitors', 'slug' => 'office-monitors'],
                    ['name' => 'Gaming Monitors', 'slug' => 'gaming-monitors'],
                    ['name' => 'Commercial Displays', 'slug' => 'commercial-displays'],
                    ['name' => 'Monitor Arms & Mounts', 'slug' => 'monitor-mounts'],
                ],
            ],
            [
                'name' => 'Networking',
                'slug' => 'networking',
                'description' => 'Switches, routers, access points, fibre and structured cabling.',
                'children' => [
                    ['name' => 'Switches', 'slug' => 'network-switches'],
                    ['name' => 'Routers & Gateways', 'slug' => 'routers-gateways'],
                    ['name' => 'Wireless Access Points', 'slug' => 'access-points'],
                    ['name' => 'Fibre & SFP Modules', 'slug' => 'fibre-sfp'],
                    ['name' => 'Cabinets & Racks', 'slug' => 'cabinets-racks'],
                ],
            ],
            [
                'name' => 'CCTV & Security',
                'slug' => 'cctv-security',
                'description' => 'IP cameras, NVRs, access control and alarm systems.',
                'children' => [
                    ['name' => 'IP Cameras', 'slug' => 'ip-cameras'],
                    ['name' => 'NVRs & DVRs', 'slug' => 'nvr-dvr'],
                    ['name' => 'Access Control', 'slug' => 'access-control'],
                    ['name' => 'Alarm Systems', 'slug' => 'alarm-systems'],
                ],
            ],
            [
                'name' => 'Servers & Storage',
                'slug' => 'servers',
                'description' => 'Rack servers, NAS, SAN and enterprise storage.',
                'children' => [
                    ['name' => 'Rack Servers', 'slug' => 'rack-servers'],
                    ['name' => 'Tower Servers', 'slug' => 'tower-servers'],
                    ['name' => 'NAS Storage', 'slug' => 'nas-storage'],
                    ['name' => 'Server Components', 'slug' => 'server-components'],
                ],
            ],
            [
                'name' => 'Printers & Scanners',
                'slug' => 'printers',
                'description' => 'Laser, inkjet, label printers and consumables.',
                'children' => [
                    ['name' => 'Laser Printers', 'slug' => 'laser-printers'],
                    ['name' => 'Inkjet Printers', 'slug' => 'inkjet-printers'],
                    ['name' => 'Scanners', 'slug' => 'scanners'],
                    ['name' => 'Ink & Toner', 'slug' => 'ink-toner'],
                ],
            ],
            [
                'name' => 'Software & Licensing',
                'slug' => 'software-licensing',
                'description' => 'Microsoft, antivirus, backup and subscription licensing.',
                'children' => [
                    ['name' => 'Microsoft 365 & Office', 'slug' => 'microsoft-365'],
                    ['name' => 'Windows Licensing', 'slug' => 'windows-licensing'],
                    ['name' => 'Antivirus & Security', 'slug' => 'antivirus-security'],
                    ['name' => 'Backup Software', 'slug' => 'backup-software'],
                ],
            ],
            [
                'name' => 'Telephony & VoIP',
                'slug' => 'telephony-voip',
                'description' => 'IP phones, PBX systems and conferencing.',
                'children' => [
                    ['name' => 'IP Phones', 'slug' => 'ip-phones'],
                    ['name' => 'PBX & Gateways', 'slug' => 'pbx-gateways'],
                    ['name' => 'Headsets', 'slug' => 'headsets'],
                ],
            ],
            [
                'name' => 'Peripherals & Accessories',
                'slug' => 'peripherals',
                'description' => 'Keyboards, mice, webcams, docking stations and cables.',
                'children' => [
                    ['name' => 'Keyboards & Mice', 'slug' => 'keyboards-mice'],
                    ['name' => 'Webcams & Audio', 'slug' => 'webcams-audio'],
                    ['name' => 'Docking Stations', 'slug' => 'docking-stations'],
                    ['name' => 'Cables & Adapters', 'slug' => 'cables-adapters'],
                ],
            ],
            [
                'name' => 'Components & Storage',
                'slug' => 'components-storage',
                'description' => 'SSDs, RAM, graphics cards and PC components.',
                'children' => [
                    ['name' => 'SSDs & Hard Drives', 'slug' => 'ssds-hdds'],
                    ['name' => 'Memory (RAM)', 'slug' => 'memory-ram'],
                    ['name' => 'Graphics Cards', 'slug' => 'graphics-cards'],
                    ['name' => 'CPUs & Motherboards', 'slug' => 'cpus-motherboards'],
                ],
            ],
            [
                'name' => 'UPS & Power',
                'slug' => 'ups-power',
                'description' => 'UPS systems, surge protection and power distribution.',
                'children' => [
                    ['name' => 'UPS Systems', 'slug' => 'ups-systems'],
                    ['name' => 'Surge Protection', 'slug' => 'surge-protection'],
                    ['name' => 'PDUs & Power Cables', 'slug' => 'pdus-cables'],
                ],
            ],
        ];

        foreach ($tree as $order => $parentData) {
            $children = $parentData['children'] ?? [];
            unset($parentData['children']);

            $parent = Category::updateOrCreate(
                ['slug' => $parentData['slug']],
                [
                    ...$parentData,
                    'parent_id' => null,
                    'sort_order' => $order,
                    'is_active' => true,
                ]
            );

            foreach ($children as $childOrder => $childData) {
                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        ...$childData,
                        'parent_id' => $parent->id,
                        'sort_order' => $childOrder,
                        'is_active' => true,
                        'description' => $childData['description'] ?? null,
                    ]
                );
            }
        }
    }
}
