<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class UpdateVendorProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:product_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'description' => 'nullable|string',
            'pet_type' => 'nullable|string|in:dog,cat,both',
            'price' => 'nullable|numeric|min:0.01',
            'discount_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:50',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_online' => 'nullable|boolean',
            'is_instore' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'variants' => 'nullable|array',
        ];
    }
}