<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'store_id'      => $this->store_id,
            'category_id'   => $this->relationLoaded('category') && $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'description'   => $this->description,
            'base_price'    => (float) $this->base_price,
            'is_published'  => (bool) $this->is_published,
            'is_approved'   => (bool) $this->is_approved,
            'meta_title'    => $this->meta_title,

            // Nesting your Sub-Resource automatically!
            'variants'      => ProductVariantResource::collection($this->whenLoaded('variants')),

            //  Formats Spatie Media library image links nicely into a clean array for your Flutter app carousel
            'images'        => $this->relationLoaded('media') ? $this->getMedia('products')->map(function ($media) {
                return [
                    'id'        => $media->id,
                    'url'       => $media->getFullUrl(),
                    'file_name' => $media->file_name,
                ];
            }) : [],

            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
