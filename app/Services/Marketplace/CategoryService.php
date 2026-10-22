<?php

namespace App\Services\Marketplace;

use App\Models\ProductCategory;
use App\Services\BaseService;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;

class CategoryService extends BaseService
{
    use FileUploadTrait;

    protected string $modelClass = ProductCategory::class;

    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('is_active'),
            AllowedFilter::partial('name'),
            AllowedFilter::partial('slug'),
        ];
    }

    protected function getAllowedIncludes(): array
    {
        return ['products'];
    }

    protected function getAllowedSorts(): array
    {
        return ['order_column', 'name', 'created_at', 'id'];
    }

    /**
     * Retrieve all active categories ordered by order_column with active products count.
     * (Optimized for Customer Marketplace / Mobile App home & category pills)
     */
    public function getActiveCategories()
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->withCount(['products' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('order_column')
            ->get();
    }

    /**
     * Create a new category with icon upload support.
     * (For Admin Portal & Management)
     */
    public function createCategory(array $data, ?Request $request = null): ProductCategory
    {
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if ($request && $request->hasFile('icon')) {
            $data['icon'] = $this->handleFileUpload($request, 'icon', 'categories', 200, 200, 90, false);
        }

        /** @var ProductCategory $category */
        $category = $this->create($data);

        return $category;
    }

    /**
     * Update an existing category with icon file replacement.
     * (For Admin Portal & Management)
     */
    public function updateCategory(ProductCategory $category, array $data, ?Request $request = null): ProductCategory
    {
        if ($request && $request->hasFile('icon')) {
            if ($category->icon) {
                $this->deleteFile($category->icon);
            }
            $data['icon'] = $this->handleFileUpload($request, 'icon', 'categories', 200, 200, 90, false);
        }

        /** @var ProductCategory $updated */
        $updated = $this->update($category->id, $data);

        return $updated;
    }

    /**
     * Delete a category and clean up its stored icon file.
     * (For Admin Portal & Management)
     */
    public function deleteCategory(ProductCategory $category): bool
    {
        if ($category->icon) {
            $this->deleteFile($category->icon);
        }

        return (bool) $this->delete($category->id);
    }
}
