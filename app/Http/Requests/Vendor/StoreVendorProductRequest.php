<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class StoreVendorProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:product_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'description' => 'nullable|string',
            'pet_type' => 'nullable|string|in:dog,cat,both',
            'price' => 'required|numeric|min:0.01',
            'discount_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:50',
            'stock' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_online' => 'nullable|boolean',
            'is_instore' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'variants' => 'nullable|array',
            'variants.*.flavor' => 'nullable|string|max:100',
            'variants.*.size_lbs' => 'nullable|string|max:50',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.sku' => 'nullable|string|max:50',
        ];
    }
}