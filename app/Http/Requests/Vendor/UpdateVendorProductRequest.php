<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;
use Illuminate\Http\UploadedFile;

class UpdateVendorProductRequest extends BaseRequest
{
    /**
     * Normalize inputs before validation.
     * Auto-decodes JSON string variants & images from multipart/form-data and normalizes string booleans.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // 1. Decode variants if sent as JSON string in multipart/form-data
        if (isset($input['variants']) && is_string($input['variants'])) {
            $decoded = json_decode($input['variants'], true);
            if (is_array($decoded)) {
                $input['variants'] = $decoded;
            }
        }

        // 2. Decode images if sent as a JSON string
        if (isset($input['images']) && is_string($input['images'])) {
            $decodedImages = json_decode($input['images'], true);
            if (is_array($decodedImages)) {
                $input['images'] = $decodedImages;
            }
        }

        // 3. Normalize booleans from multipart string inputs ('true', 'false', '1', '0')
        foreach (['is_active', 'is_online', 'is_instore'] as $field) {
            if (isset($input[$field])) {
                $input[$field] = filter_var($input[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $input[$field];
            }
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        return [
            // General Info
            'name' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:product_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'description' => 'nullable|string',
            'pet_type' => 'nullable|string|in:dog,cat,both',

            // Pricing & Inventory
            'price' => 'nullable|numeric|min:0.01',
            'discount_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:50',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',

            // Variants (Optional)
            'variants' => 'nullable|array',
            'variants.*.flavor' => 'nullable|string|max:100',
            'variants.*.size_lbs' => 'nullable|string|max:50',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',

            // Visibility & Channels
            'is_active' => 'nullable|boolean',
            'is_online' => 'nullable|boolean',
            'is_instore' => 'nullable|boolean',

            // Media / Images: Supports both UploadedFile (multipart/form-data) AND string URLs (application/json)
            'images' => 'nullable|array',
            'images.*' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof UploadedFile) {
                        if (!$value->isValid() || !str_starts_with($value->getMimeType() ?? '', 'image/')) {
                            $fail("The {$attribute} must be a valid image file.");
                            return;
                        }
                        if ($value->getSize() > 5120 * 1024) {
                            $fail("The {$attribute} must not exceed 5MB.");
                            return;
                        }
                    } elseif (is_string($value)) {
                        if (trim($value) === '') {
                            $fail("The {$attribute} image path/URL cannot be empty.");
                        }
                    } else {
                        $fail("The {$attribute} must be an image file or a valid URL/string.");
                    }
                },
            ],
        ];
    }
}
