<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /** POST /dashboard/categories */
    public function store(Request $request): JsonResponse
    {
        $user = $request->dashboard_user;

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'icon' => 'nullable|string|max:10',
        ]);

        // Limit: 30 custom categories per user
        if (Category::forUser($user->id)->count() >= 30) {
            return response()->json(['error' => 'Maksimal 30 kategori.'], 422);
        }

        $maxSort = Category::forUser($user->id)->max('sort_order') ?? 0;

        $cat = Category::create([
            'user_id'    => $user->id,
            'name'       => $validated['name'],
            'icon'       => $validated['icon'] ?? '💰',
            'color'      => 'var(--accent)',
            'is_default' => false,
            'sort_order' => $maxSort + 10,
        ]);

        return response()->json($cat, 201);
    }

    /** PATCH /dashboard/categories/{id} */
    public function update(Request $request, Category $category): JsonResponse
    {
        $user = $request->dashboard_user;

        if ($category->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:80',
            'icon' => 'nullable|string|max:10',
        ]);

        $category->update(array_filter([
            'name' => $validated['name'] ?? null,
            'icon' => $validated['icon'] ?? null,
        ], fn($v) => $v !== null));

        return response()->json($category);
    }

    /** DELETE /dashboard/categories/{id} */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $user = $request->dashboard_user;

        if ($category->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($category->is_default) {
            return response()->json(['error' => 'Kategori default tidak bisa dihapus.'], 422);
        }

        $category->delete();

        return response()->json(['ok' => true]);
    }
}
