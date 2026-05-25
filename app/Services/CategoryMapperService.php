<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryMapperService
{
    /** @var array<string, string>|null */
    protected ?array $esquireHeadMap = null;

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
