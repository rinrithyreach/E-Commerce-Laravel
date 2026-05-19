<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 1. Determine if this is an update request
        // Checks if the route contains a 'product' parameter (e.g., /products/{product})
        $product = $this->route('product');

        return [
            'category_id'  => 'required|exists:categories,id',
            'name'         => 'required|string|max:255',
            'description'  => 'required|string',
            'base_price'   => 'required|numeric|min:0',
            'is_published' => 'nullable|boolean',
            'meta_title'   => 'nullable|string|max:255',
            'images'       => 'nullable|array|max:5',
            'images.*'     => 'image|max:2048',

            // Variants validation block rules
            'variants'                  => 'required|array|min:1',

            // 2. Add structural tracking for updates
            'variants.*.id'             => 'nullable|integer|exists:product_variants,id',

            // 3. Make the SKU validation ignore existing rows on update
            'variants.*.sku'            => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($product) {
                    // Extract the array index of the variant being validated
                    preg_match('/variants\.(\d+)\.sku/', $attribute, $matches);
                    $index = $matches[1] ?? null;

                    if ($index !== null) {
                        // Get the ID sent for this specific variant array item
                        $variantId = $this->input("variants.{$index}.id");

                        $query = DB::table('product_variants')->where('sku', $value);

                        // If updating an existing variant, ignore its own ID
                        if ($variantId) {
                            $query->where('id', '!=', $variantId);
                        }
                        // Fallback: If no variant ID is sent but we know the parent product,
                        // ignore all variants belonging to this product
                        elseif ($product) {
                            $query->where('product_id', '!=', $product->id);
                        }

                        if ($query->exists()) {
                            $fail("The variant SKU '{$value}' has already been taken.");
                        }
                    }
                },
            ],

            'variants.*.attributes'     => 'required|array',
            'variants.*.price_modifier' => 'required|numeric',
            'variants.*.stock'          => 'required|integer|min:0',
        ];
    }

    /**
     * Customize your error message for a cleaner frontend alert
     */
    public function messages(): array
    {
        return [
            'images.max' => 'You can upload a maximum of 5 images per product.',
        ];
    }
}
