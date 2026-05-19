<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Nette\Schema\Expect;

class AdminCategoryController extends Controller
{
    //Display a nested hierarchical structural category tree.

    public function index()
    {
        try {
            // NestedSet method ->toTree() nests subcategories automatically inside children arrays!
            $categories = Category::with('media')->get()->toTree();

            return response()->json([
                'status' => true,
                'categories' => $categories
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a brand new category item branch inside the node map tree.
     */

    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id', // For creating subcategories
            'icon' => 'nullable|image|max:2048'
        ]);

        try {

            $category = DB::transaction(function () use ($request) {
                $category = Category::create([
                    'name' => $request->name,
                    'slug' => Str::slug($request->name),
                    'parent_id' => $request->parent_id,
                    'is_active' => true,
                ]);

                if ($request->hasFile('icon')) {
                    $category->addMediaFromRequest('icon')->toMediaCollection('icons');
                }

                return $category;
            });
            return response()->json([
                'status' => true,
                'message' => 'Category entry successfully registered inside marketplace map.',
                'category' => $category->load('media')
            ], 201);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                // A category cannot become a child of itself!
                function ($attribute, $value, $fail) use ($category) {

                    if ($value == $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                }
            ],
            'icon' => 'nullable|image|max:2048',
            'is_active' => 'nullable|boolean'
        ]);
        try {

            $updatedCategory = DB::transaction(function () use ($request, $category) {
                //Calculate the new slug path if provided, or regenerate from name string fallback
                $slugValue = $request->filled('slug') ? Str::slug($request->slug) : Str::slug($request->name);
                $category->update([
                    'name' => $request->name,
                    'slug' => $slugValue,
                    'parent_id' => $request->parent_id,
                    'is_active' => $request->get('is_active', $category->is_active)
                ]);

                if ($request->hasFile('icon')) {
                    // clear old icon

                    $category->clearMediaCollection('icons');

                    // add new one icon

                    $category->addMediaFromRequest('icon')->toMediaCollection('icons');
                }
                return $category;
            });

            return response()->json([
                'status' => true,
                'message' => 'Category entry records updated successfully.',
                'category' => $updatedCategory->load('media')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to process category updates. Changes rolled back.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(Category $category)
    {
        try {
            DB::transaction(function () use ($category) {
                // Kalnoy NestedSet macro: gets all descendants (children, grandchildren, etc.)
                $descendants = $category->descendants()->get();

                //Clean up file storage for all subcategories first

                foreach ($descendants as $subCategory) {
                    $subCategory->clearMediaCollection('icons');
                }


                //Clean up file storage for the parent category itself

                $category->clearMediaCollection('icons');

                $category->delete();
            });

            return response()->json([
                'status' => true,
                'message' => 'Category and all its child subcategories were deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete category. Database state rolled back.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
