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

    protected bool $canonicalTreeEnsured = false;

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

        return $this->displayPath('computing-office/computer-accessories');
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

        $slugPath ??= 'computing-office/computer-accessories';

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

        return $this->displayPath('computing-office/computer-accessories');
    }

    public function pathFromAstrumCategory(string $category): string
    {
        $category = html_entity_decode(trim($category), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($category === '') {
            return $this->displayPath('computing-office/computer-accessories');
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

        $slugPath ??= 'computing-office/computer-accessories';

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
                return 'gaming-entertainment/gaming-laptops';
            }

            if (preg_match('/\b(laptop|notebook|chromebook|thinkpad|latitude|elitebook|probook|ideapad|vostro|inspiron|xps|macbook|surface book|surface laptop|precision|vivobook|pavilion|envy|spectre|yoga)\b/i', $name)) {
                return 'computing-office/laptops';
            }

            if (preg_match('/\b(interactive|smart board|smartboard|touch screen|interactive display|digital signage|led display|interactive flat panel)\b/i', $name)) {
                return preg_match('/\b(bus|taxi|vehicle)\b/i', $name)
                    ? 'digital-signage/bus-advertising-screens'
                    : 'digital-signage/interactive-displays';
            }

            if (preg_match('/\b(server|poweredge|proliant|thinksystem|rack\s*server)\b/i', $name)) {
                return 'computing-office/desktops';
            }

            if (preg_match('/\b(nas|synology|qnap|storage\s*array)\b/i', $name)) {
                return 'computing-office/storage-devices';
            }

            if (preg_match('/\b(printer|laserjet|inkjet|mfp|multifunction|toner|cartridge)\b/i', $name)) {
                return preg_match('/\b(toner|cartridge|ink)\b/i', $name)
                    ? 'business-retail/receipt-printers'
                    : 'computing-office/printers';
            }

            if (preg_match('/\b(microsoft 365|office 365|windows 11|windows 10|antivirus|kaspersky|eset|norton)\b/i', $name)) {
                return 'computing-office/software';
            }

            if (preg_match('/\b(nvr|dvr|recorder|bullet|dome|ptz|ip cam|cctv|hikvision|dahua)\b/i', $name)) {
                return preg_match('/\b(nvr|dvr|recorder)\b/i', $name)
                    ? 'security-surveillance/nvr-systems'
                    : 'security-surveillance/ip-cameras';
            }

            if (preg_match('/\b(phone|voip|sip|doorphone|intercom|pbx|yeastar)\b/i', $name)) {
                return preg_match('/\b(pbx|gateway|yeastar)\b/i', $name)
                    ? 'networking-connectivity/routers'
                    : 'security-surveillance/intercom-systems';
            }

            if (preg_match('/\b(ssd|nvme|hard drive|hdd|ram|memory|motherboard|graphics|gpu|processor|cpu)\b/i', $name)) {
                return 'computing-office/storage-devices';
            }

            if (preg_match('/\b(ups|inverter|surge|pdu)\b/i', $name)) {
                return preg_match('/\b(ups|inverter)\b/i', $name)
                    ? 'solar-power/ups-systems'
                    : 'solar-power/power-backup-solutions';
            }

            if (preg_match('/\b(switch|sw\.|catalyst| crs)\b/i', $name)) {
                return 'networking-connectivity/switches';
            }

            if (preg_match('/\b(router|routerboard|hap|hex|cap\s|rb[0-9]|gateway|unifi\s*cloud)\b/i', $name)) {
                return 'networking-connectivity/routers';
            }

            if (preg_match('/\b(access point|u6-|u7-|unifi|omada|wap|wireless)\b/i', $name)) {
                return 'networking-connectivity/access-points';
            }

            if (preg_match('/\b(battery|ups)\b/i', $name)) {
                return 'solar-power/batteries';
            }

            if (preg_match('/\b(rack|cabinet|bracket|mount|tripod|stand off)\b/i', $name)) {
                return 'networking-connectivity/network-cabinets';
            }

            if (preg_match('/\b(cable|patch|sfp|fibre|fiber|pigtail|transceiver|optic)\b/i', $name)) {
                return preg_match('/\b(sfp|fibre|fiber|transceiver|optic)\b/i', $name)
                    ? 'networking-connectivity/fibre-equipment'
                    : 'networking-connectivity/network-cables';
            }

            if (preg_match('/\b(monitor|display)\b/i', $name)) {
                return 'computing-office/monitors';
            }

            if (preg_match('/\b(keyboard|mouse|webcam|headset|dock)\b/i', $name)) {
                return 'computing-office/computer-accessories';
            }
        }

        if ($text !== '') {
            if (preg_match('/\b(laptop|notebook)\b/i', $text) && preg_match('/\b(dell|hp|lenovo)\b/i', $brand)) {
                return 'computing-office/laptops';
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

        return $slugPath ?? 'computing-office/computer-accessories';
    }

    public function resolveCategoryId(string $categories): ?int
    {
        $slugPath = $this->slugPathFromDisplay($categories);

        if ($slugPath === null) {
            return null;
        }

        return $this->resolveCategoryIdFromPath($slugPath);
    }

    public function resolveCategoryIdFromPath(string $slugPath): ?int
    {
        $slugPath = $this->translateLegacyPath(trim($slugPath, '/'));

        if ($slugPath === '') {
            return null;
        }

        [$parentSlug, $childSlug] = array_pad(explode('/', $slugPath, 2), 2, null);

        $this->ensureCanonicalTree();

        $parent = Category::where('slug', $parentSlug)->whereNull('parent_id')->first();

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
        if ($this->canonicalTreeEnsured && Category::query()->whereNull('parent_id')->where('slug', 'computing-office')->exists()) {
            return;
        }

        $this->canonicalTreeEnsured = true;

        foreach (config('category_tree.tree', config('category_map.tree', [])) as $order => $parentData) {
            $parent = Category::updateOrCreate(
                ['slug' => $parentData['slug']],
                [
                    'name' => $parentData['name'],
                    'parent_id' => null,
                    'description' => $parentData['description'] ?? null,
                    'meta_title' => $parentData['meta_title'] ?? null,
                    'meta_description' => $parentData['meta_description'] ?? null,
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
                        'description' => $childData['description'] ?? null,
                        'meta_title' => $childData['meta_title'] ?? null,
                        'meta_description' => $childData['meta_description'] ?? null,
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

        foreach (config('category_tree.tree', config('category_map.tree', [])) as $parent) {
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

        foreach (config('category_tree.tree', config('category_map.tree', [])) as $parent) {
            $this->treeBySlug[$parent['slug']] = $parent;
        }
    }

    protected function translateLegacyPath(string $path): string
    {
        $pathMap = config('category_tree.legacy_path_map', []);

        if (isset($pathMap[$path])) {
            return $pathMap[$path];
        }

        [$parentSlug, $childSlug] = array_pad(explode('/', $path, 2), 2, null);
        $slugMap = config('category_tree.legacy_slug_map', []);

        if ($childSlug && isset($slugMap[$childSlug])) {
            return $slugMap[$childSlug];
        }

        if ($parentSlug && isset($slugMap[$parentSlug])) {
            $mapped = $slugMap[$parentSlug];

            if (! $childSlug || str_contains($mapped, '/')) {
                return $mapped;
            }

            return $mapped.'/'.$childSlug;
        }

        return $path;
    }

    public function resolveCategoryForFilter(string $slug): ?Category
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return null;
        }

        $categoryId = $this->resolveCategoryIdFromPath($slug);

        if ($categoryId) {
            return Category::find($categoryId);
        }

        return Category::where('slug', $slug)->where('is_active', true)->first();
    }

    public function categoryUrlForPath(string $path): ?string
    {
        return $this->resolveCategoryForFilter($path)?->url();
    }

    public function isLegacyCategoryPath(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === '') {
            return false;
        }

        return isset(config('category_tree.legacy_slug_map', [])[$path])
            || isset(config('category_tree.legacy_path_map', [])[$path]);
    }
}
