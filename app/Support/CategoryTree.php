<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Loads the category table once per request and serves id/slug/parent
 * lookups from memory. Eloquent does not stitch parent relations onto
 * a Category::query()->get() collection, so walking parent chains used
 * to fire `select * from categories where id in (N)` once per row.
 */
class CategoryTree
{
    protected bool $booted = false;

    protected bool $booting = false;

    /** @var Collection<int, Category> */
    protected Collection $byId;

    /** @var array<string, list<Category>> */
    protected array $bySlug = [];

    /** @var array<int, list<int>> */
    protected array $childrenOf = [];

    public static function current(): self
    {
        if (! app()->bound(self::class)) {
            app()->instance(self::class, new self());
        }

        $tree = app(self::class);
        $tree->ensureBooted();

        return $tree;
    }

    public static function forget(): void
    {
        if (! app()->bound(self::class)) {
            return;
        }

        $tree = app(self::class);
        $tree->booted = false;
        $tree->booting = false;
        $tree->byId = collect();
        $tree->bySlug = [];
        $tree->childrenOf = [];
    }

    public static function isBooting(): bool
    {
        if (! app()->bound(self::class)) {
            return false;
        }

        return app(self::class)->booting;
    }

    public function find(int $id): ?Category
    {
        $this->ensureBooted();

        return $this->byId->get($id);
    }

    public function findBySlug(string $slug, ?int $parentId = null): ?Category
    {
        $this->ensureBooted();

        $matches = $this->bySlug[$slug] ?? [];

        if ($matches === []) {
            return null;
        }

        if ($parentId === null) {
            foreach ($matches as $category) {
                if ($category->parent_id === null) {
                    return $category;
                }
            }

            return $matches[0];
        }

        foreach ($matches as $category) {
            if ((int) $category->parent_id === $parentId) {
                return $category;
            }
        }

        return null;
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        $this->ensureBooted();

        foreach ($this->bySlug[$slug] ?? [] as $category) {
            if ($category->is_active) {
                return $category;
            }
        }

        return null;
    }

    /** @return Collection<int, Category> */
    public function all(): Collection
    {
        $this->ensureBooted();

        return $this->byId;
    }

    /** @return list<int> */
    public function descendantIds(int $categoryId): array
    {
        $this->ensureBooted();

        $ids = [$categoryId];
        $stack = [$categoryId];

        while ($stack !== []) {
            $id = array_pop($stack);
            foreach ($this->childrenOf[$id] ?? [] as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $stack[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * @return list<array{id: int, parent_id: int|null}>
     */
    public function activeParentRows(): array
    {
        $this->ensureBooted();

        $rows = [];
        foreach ($this->byId as $category) {
            if (! $category->is_active) {
                continue;
            }
            $rows[] = [
                'id' => (int) $category->id,
                'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
            ];
        }

        return $rows;
    }

    public function hydrate(Category $category): void
    {
        if ($this->booting || ! $this->booted) {
            return;
        }

        if ($category->relationLoaded('parent')) {
            return;
        }

        if (! $category->parent_id) {
            $category->setRelation('parent', null);

            return;
        }

        $parent = $this->byId->get((int) $category->parent_id);
        $category->setRelation('parent', $parent);
    }

    protected function ensureBooted(): void
    {
        if ($this->booted || $this->booting) {
            return;
        }

        $this->booting = true;
        $this->byId = collect();
        $this->bySlug = [];
        $this->childrenOf = [];

        try {
            $all = Category::query()->get();
        } finally {
            $this->booting = false;
        }

        $this->byId = $all->keyBy(fn (Category $category) => (int) $category->id);

        foreach ($this->byId as $category) {
            $this->bySlug[$category->slug][] = $category;

            if ($category->parent_id) {
                $this->childrenOf[(int) $category->parent_id][] = (int) $category->id;
                $parent = $this->byId->get((int) $category->parent_id);
                $category->setRelation('parent', $parent);
            } else {
                $category->setRelation('parent', null);
            }
        }

        $this->booted = true;
    }
}
