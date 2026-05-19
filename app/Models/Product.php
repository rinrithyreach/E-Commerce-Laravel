<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;


class Product extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, Searchable;
    protected $dates = ['deleted_at'];
    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'is_published',
        'is_approved',
        'meta_title',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_approved' => 'boolean',
            'base_price' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('products')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
            ->useDisk('public'); // or your default storage driver
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relationship listing all the tracked variant mappings for this product profile.
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
