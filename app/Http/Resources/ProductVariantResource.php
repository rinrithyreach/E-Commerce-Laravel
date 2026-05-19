<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [
            'id'             => $this->id,
            'sku'            => $this->sku,
            'attributes'     => $this->attributes, // Automatically array-casted from JSON
            'price_modifier' => (float) $this->price_modifier,
            'stock'          => (int) $this->stock,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
