<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public array $fashionCategories = [
        [
            'name' => 'Men',
            'children' => [
                [
                    'name' => 'Clothing',
                    'children' => [
                        ['name' => 'Hoodies & Sweatshirts'],
                        ['name' => 'Jackets & Vests'],
                        ['name' => 'Pants & Tights'],
                        ['name' => 'Shorts'],
                        ['name' => 'Tops & T-shirts']
                    ]
                ],
                [
                    'name' => 'Shoes',
                    'children' => [
                        ['name' => 'Basket Ball'],
                        ['name' => 'Running'],
                        ['name' => 'Sandals & Slides'],
                        ['name' => 'Sneakers'],
                        ['name' => 'Soccer']
                    ]
                ],
                [
                    'name' => 'Accessories',
                    'children' => [
                        ['name' => 'Bags & Backpacks'],
                        ['name' => 'Hat & Beanies'],
                        ['name' => 'Socks'],
                        ['name' => 'Underwear']
                    ]
                ],
            ],
        ],
        [
            'name' => 'Women',
            'children' => [
                [
                    'name' => 'Clothing',
                    'children' => [
                        ['name' => 'Dresses & Skirts'],
                        ['name' => 'Hoodies & Sweatshirts'],
                        ['name' => 'Pants'],
                        ['name' => 'Tights & Leggings'],
                        ['name' => 'Tops & T-shirts']
                    ]
                ],
                [
                    'name' => 'Shoes',
                    'children' => [
                        ['name' => 'Running'],
                        ['name' => 'Sneakers'],
                        ['name' => 'Training & Gym']
                    ]
                ],
                [
                    'name' => 'Accessories',
                    'children' => [
                        ['name' => 'Bags & Backpacks'],
                        ['name' => 'Hats'],
                        ['name' => 'Socks']
                    ]
                ]
            ]
        ],
        [
            'name' => 'Juniors',
            'children' => [
                [
                    'name' => 'Clothing',
                    'children' => [
                        ['name' => 'Hoodies & Sweatshirts'],
                        ['name' => 'Shorts'],
                        ['name' => 'Tops & T-shirts']
                    ]
                ],
                [
                    'name' => 'Shoes',
                    'children' => [
                        ['name' => 'Basket Ball'],
                        ['name' => 'Running'],
                        ['name' => 'Sneakers']
                    ]
                ],
                [
                    'name' => 'Accessories',
                    'children' => [
                        ['name' => 'Bags & Backpacks'],
                        ['name' => 'Hats']
                    ]
                ]
            ]
        ]
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->fashionCategories as $rootCategory) {

            // 1. Create root primary parent node entries
            $parent = Category::create([
                'name'      => $rootCategory['name'],
                'slug'      => $this->generateUniqueSlug($rootCategory['name']),
                'is_active' => true,
                'parent_id' => null
            ]);

            $this->attachIconMedia($parent, $rootCategory['name']);

            // 2. Safely trigger recursion mapping down child groups
            if (isset($rootCategory['children']) && count($rootCategory['children']) > 0) {
                $this->seedNestedChildren($rootCategory['children'], $parent);
            }
        }

        // 💡 3. CRUCIAL FIX UPSTEP: This builds your whole table boundary trees map in a single pass at the end!
        $this->command->info('Rebuilding NestedSet tree indices...');
        Category::fixTree();
    }

    /**
     * Recursively map down child nodes through the hierarchy tree safely.
     */
    private function seedNestedChildren(array $children, Category $parentInstance): void
    {
        foreach ($children as $childData) {

            // Build subcategories bound natively to parentInstance id paths
            $childNode = Category::create([
                'name'      => $childData['name'],
                'slug'      => $this->generateUniqueSlug($childData['name'], $parentInstance->slug),
                'is_active' => true,
                'parent_id' => $parentInstance->id
            ]);

            $this->attachIconMedia($childNode, $childData['name']);

            if (isset($childData['children']) && count($childData['children']) > 0) {
                $this->seedNestedChildren($childData['children'], $childNode);
            }
        }
    }

    /**
     * Generate unique slugs to prevent constraint errors.
     */
    private function generateUniqueSlug(string $name, ?string $prefix = null): string
    {
        $slugBase = Str::slug($name);

        if ($prefix) {
            $slugBase = $prefix . '-' . $slugBase;
        }

        if (Category::where('slug', $slugBase)->exists()) {
            $slugBase .= '-' . Str::lower(Str::random(4));
        }

        return $slugBase;
    }

    /**
     * Spatie MediaLibrary asset helper.
     */
    private function attachIconMedia(Category $category, string $fileName): void
    {
        $displayType = env('DISPLAY_TYPE', 'default');
        $sanitizedName = strtolower(str_replace([' ', '&'], ['_', 'and'], $fileName));
        $localPath = public_path("/images/seeder/product-category/{$displayType}/{$sanitizedName}.png");

        if (file_exists($localPath)) {
            $category->addMedia($localPath)
                ->preservingOriginal()
                ->toMediaCollection('icons');
        }
    }
}
