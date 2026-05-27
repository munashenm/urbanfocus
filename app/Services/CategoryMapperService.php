<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryMapperService
{
    /** @var array<string, string>|null */
    protected ?array $esquireHeadMap = null;

    /** @var array<string, string>|null */
    protected ?array $astrumCategoryMap = null;

    /** @var array<string, string>|null */
    protected ?array $scoopBrandMap = null;

    /** @var list<array{prefix: string, path: string}>|null */
    protected ?array $pinnaclePaths = null;

    /** @var array<string, array{name: string, slug: string, children?: list<array{name: string, slug: string}>}>|null */
    protected ?array $treeBySlug = null;

    /** @param array<string, mixed> $data */
    public function mapImportCategories(array $data): string
    {
        if (trim($data['category_tree'] ?? '') !== '') {
            return $this->pathFromPinnacleTree($data['category_tree']);
        }

        if (($data['import_source'] ?? '') === 'astrum') {
            return $this->pathFromAstrumCategory($data['category'] ?? '');
        }

        if (($data['import_source'] ?? '') === 'scoop') {
            return $this->pathFromScoopProduct($data);
        }

        $head = trim($data['category_head'] ?? '');

        if ($head !== '') {
            return $this->pathFromEsquireHead($head);
        }

        return trim($data['categories'] ?? '');
    }

    /** @param list<string> $parts */
    public function mapCategoryParts(array $parts): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts)));

        if ($parts === []) {
            return '';
        }

        foreach ($parts as $part) {
            $slugPath = $this->esquireHeadMap()[$part] ?? null;

            if ($slugPath === null) {
                foreach ($this->esquireHeadMap() as $known => $path) {
                    if (strcasecmp($known, $part) === 0) {
                        $slugPath = $path;
                        break;
                    }
                }
            }

            if ($slugPath !== null) {
                return $this->displayPath($slugPath);
            }
        }

        return $this->displayPath('peripherals');
    }

    public function pathFromEsquireHead(string $head): string
    {
        $head = trim($head);
        $slugPath = $this->esquireHeadMap()[$head] ?? null;

        if ($slugPath === null) {
            foreach ($this->esquireHeadMap() as $known => $path) {
                if (strcasecmp($known, $head) === 0) {
                    $slugPath = $path;
                    break;
                }
            }
        }

        $slugPath ??= 'peripherals';

        return $this->displayPath($slugPath);
    }

    public function pathFromPinnacleTree(string $tree): string
    {
        $tree = strtolower(trim($tree));

        foreach ($this->pinnaclePathRules() as $rule) {
            if ($tree === $rule['prefix'] || str_starts_with($tree, $rule['prefix'].'/')) {
                return $this->displayPath($rule['path']);
            }
        }

        return $this->displayPath('peripherals');
    }

    public function pathFromAstrumCategory(string $category): string
    {
        $category = html_entity_decode(trim($category), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($category === '') {
            return $this->displayPath('peripherals');
        }

        $slugPath = $this->astrumCategoryMap()[$category] ?? null;

        if ($slugPath === null) {
            foreach ($this->astrumCategoryMap() as $known => $path) {
                if (strcasecmp($known, $category) === 0) {
                    $slugPath = $path;
                    break;
                }
            }
        }

        $slugPath ??= 'peripherals';

        return $this->displayPath($slugPath);
    }

    public function pathFromCatalogProduct(\App\Models\Product $product): string
    {
        return $this->pathFromScoopProduct([
            'name' => $product->name,
            'brand' => $product->brand ?? '',
            'description' => trim(($product->short_description ?? '').' '.strip_tags((string) $product->description)),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function pathFromScoopProduct(array $data): string
    {
        return $this->displayPath($this->inferSlugPath($data));
    }

    /** @param array<string, mixed> $data */
    public function inferSlugPath(array $data): string
    {
        $name = strtolower(trim($data['name'] ?? $data['description'] ?? ''));
        $text = strtolower(trim(($data['name'] ?? '').' '.($data['description'] ?? '')));
        $brand = trim($data['brand'] ?? '');

        if ($name !== '') {
            if (preg_match('/\b(gaming laptop|g15|rog strix|omen|nitro|legion)\b/i', $name)) {
                return 'laptops-notebooks/gaming-laptops';
            }

            if (preg_match('/\b(laptop|notebook|chromebook|thinkpad|latitude|elitebook|probook|ideapad|vostro|inspiron|xps)\b/i', $name)) {
                return 'laptops-notebooks/business-laptops';
            }

            if (preg_match('/\b(server|poweredge|proliant|thinksystem|rack\s*server)\b/i', $name)) {
                return 'servers/rack-servers';
            }

            if (preg_match('/\b(nas|synology|qnap|storage\s*array)\b/i', $name)) {
                return 'servers/nas-storage';
            }

            if (preg_match('/\b(printer|laserjet|inkjet|mfp|multifunction|toner|cartridge)\b/i', $name)) {
                return preg_match('/\b(toner|cartridge|ink)\b/i', $name)
                    ? 'printers/ink-toner'
                    : 'printers/laser-printers';
            }

            if (preg_match('/\b(microsoft 365|office 365|windows 11|windows 10|antivirus|kaspersky|eset|norton)\b/i', $name)) {
                return 'software-licensing';
            }

            if (preg_match('/\b(nvr|dvr|recorder|bullet|dome|ptz|ip cam|cctv|hikvision|dahua)\b/i', $name)) {
                return preg_match('/\b(nvr|dvr|recorder)\b/i', $name)
                    ? 'cctv-security/nvr-dvr'
                    : 'cctv-security/ip-cameras';
            }

            if (preg_match('/\b(phone|voip|sip|doorphone|intercom|pbx|yeastar)\b/i', $name)) {
                return preg_match('/\b(pbx|gateway|yeastar)\b/i', $name)
                    ? 'telephony-voip/pbx-gateways'
                    : 'telephony-voip/ip-phones';
            }

            if (preg_match('/\b(ssd|nvme|hard drive|hdd|ram|memory|motherboard|graphics|gpu|processor|cpu)\b/i', $name)) {
                return 'components-storage';
            }

            if (preg_match('/\b(ups|inverter|surge|pdu)\b/i', $name)) {
                return preg_match('/\b(ups|inverter)\b/i', $name)
                    ? 'ups-power/ups-systems'
                    : 'ups-power/pdus-cables';
            }

            if (preg_match('/\b(switch|sw\.|catalyst| crs)\b/i', $name)) {
                return 'networking/network-switches';
            }

            if (preg_match('/\b(router|routerboard|hap|hex|cap\s|rb[0-9]|gateway|unifi\s*cloud)\b/i', $name)) {
                return 'networking/routers-gateways';
            }

            if (preg_match('/\b(access point|u6-|u7-|unifi|omada|wap|wireless)\b/i', $name)) {
                return 'networking/access-points';
            }

            if (preg_match('/\b(battery|ups)\b/i', $name)) {
                return 'ups-power/pdus-cables';
            }

            if (preg_match('/\b(rack|cabinet|bracket|mount|tripod|stand off)\b/i', $name)) {
                return 'networking/cabinets-racks';
            }

            if (preg_match('/\b(cable|patch|sfp|fibre|fiber|pigtail|transceiver|optic)\b/i', $name)) {
                return preg_match('/\b(sfp|fibre|fiber|transceiver|optic)\b/i', $name)
                    ? 'networking/fibre-sfp'
                    : 'peripherals/cables-adapters';
            }

            if (preg_match('/\b(monitor|display)\b/i', $name)) {
                return 'monitors-displays/office-monitors';
            }

            if (preg_match('/\b(keyboard|mouse|webcam|headset|dock)\b/i', $name)) {
                return 'peripherals';
            }
        }

        if ($text !== '') {
            if (preg_match('/\b(laptop|notebook)\b/i', $text) && preg_match('/\b(dell|hp|lenovo)\b/i', $brand)) {
                return 'laptops-notebooks/business-laptops';
            }
        }

        $slugPath = $this->scoopBrandMap()[$brand] ?? null;

        if ($slugPath === null) {
            foreach ($this->scoopBrandMap() as $known => $path) {
                if (strcasecmp($known, $brand) === 0) {
                    $slugPath = $path;
                    break;
                }
            }
        }

        return $slugPath ?? 'peripherals';
    }

    public function resolveCategoryId(string $categories): ?int
    {
        $slugPath = $this->slugPathFromDisplay($categories);

        if ($slugPath === null) {
            return null;
        }

        [$parentSlug, $childSlug] = array_pad(explode('/', $slugPath, 2), 2, null);

        $this->ensureCanonicalTree();

        $parent = Category::where('slug', $parentSlug)->first();

        if (! $parent) {
            return null;
        }

        if ($childSlug) {
            $child = Category::where('slug', $childSlug)->where('parent_id', $parent->id)->first();

            return $child?->id ?? $parent->id;
        }

        return $parent->id;
    }

    public function ensureCanonicalTree(): void
    {
        foreach (config('category_map.tree', []) as $order => $parentData) {
            $parent = Category::updateOrCreate(
                ['slug' => $parentData['slug']],
                [
                    'name' => $parentData['name'],
                    'parent_id' => null,
                    'sort_order' => $order,
                    'is_active' => true,
                ]
            );

            foreach ($parentData['children'] ?? [] as $childOrder => $childData) {
                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        'name' => $childData['name'],
                        'parent_id' => $parent->id,
                        'sort_order' => $childOrder,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /** @return list<string> */
    public function canonicalSlugs(): array
    {
        $slugs = [];

        foreach (config('category_map.tree', []) as $parent) {
            $slugs[] = $parent['slug'];
            foreach ($parent['children'] ?? [] as $child) {
                $slugs[] = $child['slug'];
            }
        }

        return $slugs;
    }

    protected function displayPath(string $slugPath): string
    {
        $this->buildTreeIndex();

        [$parentSlug, $childSlug] = array_pad(explode('/', $slugPath, 2), 2, null);
        $parent = $this->treeBySlug[$parentSlug] ?? null;

        if (! $parent) {
            return Str::title(str_replace('-', ' ', $parentSlug));
        }

        if ($childSlug) {
            foreach ($parent['children'] ?? [] as $child) {
                if ($child['slug'] === $childSlug) {
                    return $parent['name'].' > '.$child['name'];
                }
            }
        }

        return $parent['name'];
    }

    protected function slugPathFromDisplay(string $categories): ?string
    {
        $this->buildTreeIndex();

        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $categories) ?: [])));

        if ($parts === []) {
            return null;
        }

        foreach ($this->treeBySlug as $parentSlug => $parent) {
            if (strcasecmp($parent['name'], $parts[0]) !== 0) {
                continue;
            }

            if (count($parts) === 1) {
                return $parentSlug;
            }

            foreach ($parent['children'] ?? [] as $child) {
                if (strcasecmp($child['name'], $parts[1]) === 0) {
                    return $parentSlug.'/'.$child['slug'];
                }
            }

            return $parentSlug;
        }

        return null;
    }

    /** @return array<string, string> */
    protected function esquireHeadMap(): array
    {
        return $this->esquireHeadMap ??= config('category_map.esquire_heads', []);
    }

    /** @return array<string, string> */
    protected function astrumCategoryMap(): array
    {
        return $this->astrumCategoryMap ??= config('category_map.astrum_categories', []);
    }

    /** @return array<string, string> */
    protected function scoopBrandMap(): array
    {
        return $this->scoopBrandMap ??= config('category_map.scoop_brands', []);
    }

    /** @return list<array{prefix: string, path: string}> */
    protected function pinnaclePathRules(): array
    {
        if ($this->pinnaclePaths !== null) {
            return $this->pinnaclePaths;
        }

        $rules = [];

        foreach (config('category_map.pinnacle_paths', []) as $prefix => $path) {
            $rules[] = ['prefix' => strtolower($prefix), 'path' => $path];
        }

        usort($rules, fn (array $a, array $b) => strlen($b['prefix']) <=> strlen($a['prefix']));

        return $this->pinnaclePaths = $rules;
    }

    protected function buildTreeIndex(): void
    {
        if ($this->treeBySlug !== null) {
            return;
        }

        $this->treeBySlug = [];

        foreach (config('category_map.tree', []) as $parent) {
            $this->treeBySlug[$parent['slug']] = $parent;
        }
    }
}
