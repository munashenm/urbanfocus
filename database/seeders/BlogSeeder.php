<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Author;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $author = Author::firstOrCreate(
            ['slug' => 'urban-focus-team'],
            [
                'name' => 'Urban Focus Team',
                'title' => 'IT Procurement & Infrastructure',
                'bio' => 'Guides and insights from the Urban Focus team — South African IT distributor for networking, laptops, CCTV and enterprise hardware.',
                'is_active' => true,
            ]
        );

        foreach ($this->pillars() as $pillar) {
            $daysAgo = $pillar['days_ago'] ?? 1;
            unset($pillar['days_ago']);

            Article::updateOrCreate(
                ['slug' => $pillar['slug']],
                array_merge($pillar, [
                    'author_id' => $author->id,
                    'toc_enabled' => true,
                    'is_published' => true,
                    'published_at' => $pillar['published_at'] ?? now()->subDays($daysAgo),
                ])
            );
        }
    }

    /** @return list<array<string, mixed>> */
    protected function pillars(): array
    {
        return [
            [
                'slug' => 'ubiquiti-supplier-south-africa-buyers-guide',
                'title' => 'Ubiquiti Supplier South Africa: A Buyer\'s Guide for Businesses & ISPs',
                'category' => 'networking',
                'is_featured' => true,
                'days_ago' => 2,
                'excerpt' => 'How to choose UniFi access points, switches and gateways from a trusted Ubiquiti supplier in South Africa — with procurement tips for installers and IT teams.',
                'meta_title' => 'Ubiquiti Supplier South Africa Buyer\'s Guide | Urban Focus',
                'meta_description' => 'Plan UniFi Wi‑Fi, switching and routing for South African businesses. Compare access points, PoE budgets and procurement options from Urban Focus.',
                'content' => $this->html(<<<'HTML'
<h2>Why Ubiquiti is popular in South Africa</h2>
<p>Ubiquiti UniFi and airMAX platforms offer enterprise-style networking at accessible price points — ideal for offices, schools, warehouses, estates and wireless ISPs. When sourcing from an Ubiquiti supplier in South Africa, verify genuine stock, warranty support and VAT-compliant invoicing.</p>
<h2>What to buy first</h2>
<ul>
<li><strong>Access points</strong> — UniFi U6/U7 series for office and hospitality Wi‑Fi</li>
<li><strong>PoE switches</strong> — size ports for APs, cameras and uplinks</li>
<li><strong>Cloud gateways</strong> — routing, firewall and UniFi Network management</li>
<li><strong>Protect</strong> — CCTV where you want a single management plane</li>
</ul>
<h2>Procurement checklist</h2>
<p>Confirm PoE budget, ceiling vs wall mount models, fibre uplinks and spare stock for rollouts. Request a formal quote for multi-site projects and ask about lead times on high-demand SKUs.</p>
<p><a href="/solutions/ubiquiti-supplier-south-africa">Browse Ubiquiti supply at Urban Focus</a> or <a href="/b2b/quote">request a project quote</a>.</p>
HTML),
            ],
            [
                'slug' => 'mikrotik-distributor-south-africa-isp-guide',
                'title' => 'MikroTik in South Africa: Router & ISP Deployment Guide',
                'category' => 'networking',
                'days_ago' => 4,
                'excerpt' => 'RouterBOARD, CRS switches and wireless CPE — what South African ISPs and integrators should know when buying MikroTik hardware.',
                'meta_title' => 'MikroTik Distributor South Africa Guide | Urban Focus',
                'meta_description' => 'Deploy MikroTik RouterOS for routing, PPPoE, wireless backhaul and switching. South African procurement guide from Urban Focus.',
                'content' => $this->html(<<<'HTML'
<h2>MikroTik for connectivity providers</h2>
<p>MikroTik remains a workhorse for South African WISPs, fibre resellers and corporate networks. RouterOS delivers routing, firewall, queues, VPN and monitoring in one platform — often at a fraction of traditional enterprise costs.</p>
<h2>Common deployment patterns</h2>
<ul>
<li><strong>CPE &amp; hotspots</strong> — hAP and low-cost RouterBOARD models</li>
<li><strong>PPPoE &amp; BRAS</strong> — CCR series for subscriber scaling</li>
<li><strong>Switching</strong> — CRS for aggregation and core layers</li>
<li><strong>Backhaul</strong> — wireless links and sector deployments</li>
</ul>
<h2>Sourcing tips</h2>
<p>Standardise on a small set of SKUs for supportability, keep spare routers in stock, and document config backups. Buy from a distributor that understands ISP timelines and can supply VAT invoices for finance teams.</p>
<p><a href="/solutions/mikrotik-distributor-south-africa">View MikroTik supply</a> · <a href="/b2b/rfq">Upload an RFQ</a></p>
HTML),
            ],
            [
                'slug' => 'business-laptops-south-africa-procurement-guide',
                'title' => 'Business Laptops South Africa: Fleet Procurement Guide',
                'category' => 'laptops',
                'days_ago' => 6,
                'excerpt' => 'Standardise on Dell, HP and Lenovo corporate notebooks with the right CPU, RAM, warranty and imaging support for South African teams.',
                'meta_title' => 'Business Laptops South Africa Procurement Guide | Urban Focus',
                'meta_description' => 'Choose business laptops for corporate fleets in South Africa. Specs, warranty, bulk pricing and VAT invoicing from Urban Focus.',
                'content' => $this->html(<<<'HTML'
<h2>Planning a laptop refresh</h2>
<p>Corporate fleets need predictable specs, manageability and warranty — not consumer deals. Standardise on business lines such as Dell Latitude, HP EliteBook/ProBook and Lenovo ThinkPad for easier support and repeat ordering.</p>
<h2>Recommended baseline specs (2026)</h2>
<ul>
<li><strong>CPU</strong> — Intel Core i5/i7 or AMD Ryzen 5/7 (match workload)</li>
<li><strong>RAM</strong> — 16GB minimum for knowledge workers; 32GB for power users</li>
<li><strong>Storage</strong> — 512GB NVMe minimum; consider BitLocker-ready devices</li>
<li><strong>Warranty</strong> — 3-year next-business-day where budget allows</li>
</ul>
<h2>Bulk procurement</h2>
<p>Submit quantities, delivery deadlines and preferred brands via RFQ. Ask about docking stations, carry cases and staged delivery for multi-branch rollouts.</p>
<p><a href="/solutions/business-laptops-south-africa">Shop business laptops</a> · <a href="/b2b/quote">Request fleet pricing</a></p>
HTML),
            ],
            [
                'slug' => 'cctv-equipment-supplier-south-africa-guide',
                'title' => 'CCTV Equipment for South African Businesses: Camera & NVR Guide',
                'category' => 'cctv',
                'days_ago' => 8,
                'excerpt' => 'Choose IP cameras, NVRs and PoE infrastructure from Hikvision, Dahua and leading brands — with tips for installers and facilities teams.',
                'meta_title' => 'CCTV Equipment Supplier South Africa Guide | Urban Focus',
                'meta_description' => 'Plan commercial CCTV with IP cameras, NVRs and PoE switching. South African supplier guide from Urban Focus.',
                'content' => $this->html(<<<'HTML'
<h2>Building a commercial CCTV system</h2>
<p>Modern surveillance stacks combine IP cameras, NVR storage, PoE switches and structured cabling. For retail, warehouses and offices, prioritise camera resolution, low-light performance and retention requirements before choosing brands.</p>
<h2>Component checklist</h2>
<ul>
<li><strong>Cameras</strong> — dome, bullet or turret based on mounting location</li>
<li><strong>Recorders</strong> — NVR sized for camera count and retention days</li>
<li><strong>Networking</strong> — PoE switch with uplink capacity for video traffic</li>
<li><strong>Storage</strong> — surveillance-grade drives where supported</li>
</ul>
<h2>Working with a supplier</h2>
<p>Integrators should confirm firmware regions, warranty and spare stock. Request project BOMs for multi-site rollouts and formal quotes for tender submissions.</p>
<p><a href="/solutions/cctv-equipment-supplier">Browse CCTV equipment</a> · <a href="/contact">Contact our team</a></p>
HTML),
            ],
            [
                'slug' => 'bulk-it-procurement-south-africa',
                'title' => 'Bulk IT Procurement in South Africa: A Practical Guide for IT Teams',
                'category' => 'procurement',
                'days_ago' => 10,
                'excerpt' => 'How finance and IT teams streamline bulk hardware orders with VAT invoices, RFQs and repeatable supplier relationships.',
                'meta_title' => 'Bulk IT Procurement South Africa Guide | Urban Focus',
                'meta_description' => 'Streamline bulk IT orders with RFQs, VAT invoices and nationwide delivery. Corporate procurement guide from Urban Focus.',
                'content' => $this->html(<<<'HTML'
<h2>Why bulk procurement needs a specialist partner</h2>
<p>Corporate orders span laptops, networking, CCTV, software licensing and spares — often with tight deadlines and audit requirements. A focused IT distributor simplifies VAT invoicing, quote comparisons and repeat fleet orders.</p>
<h2>RFQ best practices</h2>
<ul>
<li>Include brand, model, quantity and delivery address per line item</li>
<li>State whether pricing must be valid for 30/60/90 days</li>
<li>Attach tender or budget reference numbers for finance</li>
<li>Ask about split shipments for phased rollouts</li>
</ul>
<p><a href="/solutions/bulk-it-procurement">Learn about bulk procurement</a> · <a href="/b2b/rfq">Submit an RFQ</a></p>
HTML),
            ],
            [
                'slug' => 'fibre-networking-solutions-business-guide',
                'title' => 'Fibre Networking for South African Businesses: SFP, Switches & Uplinks',
                'category' => 'guides',
                'days_ago' => 12,
                'excerpt' => 'Plan fibre uplinks, SFP modules and core switching for offices and ISPs — compatibility, optics and procurement tips.',
                'meta_title' => 'Fibre Networking Solutions Guide South Africa | Urban Focus',
                'meta_description' => 'Plan business fibre networking with SFP modules, switches and routers. South African IT infrastructure guide from Urban Focus.',
                'content' => $this->html(<<<'HTML'
<h2>Fibre is the default uplink</h2>
<p>Whether connecting a GPON ONT, office server room or data centre handoff, fibre links demand the right optics, patch cabling and switching capacity. Document wavelength, connector type and distance before ordering SFP/SFP+ modules.</p>
<h2>Common mistakes to avoid</h2>
<ul>
<li>Mixing incompatible SFP brands without testing</li>
<li>Undersizing switch backplane for camera and Wi‑Fi traffic</li>
<li>Forgetting spare optics and patch leads on site</li>
</ul>
<p><a href="/solutions/fibre-networking-solutions">Explore fibre networking</a> · <a href="/shop?category=networking">Shop networking</a></p>
HTML),
            ],
        ];
    }

    protected function html(string $content): string
    {
        return trim($content);
    }
}
