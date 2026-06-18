<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMenuRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMenuConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $menus = AdminMenuRule::with('children')
            ->roots()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($menu) {
                return [
                    'id' => $menu->menu_key,
                    'menuKey' => $menu->menu_key,
                    'labelKey' => $menu->title_key,
                    'path' => $menu->path,
                    'icon' => $menu->icon,
                    'visible' => $menu->visible,
                    'sort' => $menu->sort_order,
                    'permissionCode' => $menu->permission_code,
                    'groupKey' => $menu->group_key,
                    'parentId' => $menu->parent_key,
                    'children' => $menu->children->map(function ($child) {
                        return [
                            'id' => $child->menu_key,
                            'menuKey' => $child->menu_key,
                            'labelKey' => $child->title_key,
                            'path' => $child->path,
                            'icon' => $child->icon,
                            'visible' => $child->visible,
                            'sort' => $child->sort_order,
                            'permissionCode' => $child->permission_code,
                            'groupKey' => $child->group_key,
                            'parentId' => $child->parent_key,
                        ];
                    }),
                ];
            });

        return response()->json(['data' => $menus]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mainMenu' => 'sometimes|array',
            'mainMenu.*.id' => 'required|string',
            'mainMenu.*.labelKey' => 'required|string',
            'mainMenu.*.path' => 'required|string',
            'mainMenu.*.icon' => 'sometimes|string',
            'mainMenu.*.visible' => 'required|boolean',
            'mainMenu.*.sort' => 'required|integer',
            'subMenu' => 'sometimes|array',
            'subMenu.*.id' => 'required|string',
            'subMenu.*.labelKey' => 'required|string',
            'subMenu.*.path' => 'required|string',
            'subMenu.*.icon' => 'sometimes|string',
            'subMenu.*.visible' => 'required|boolean',
            'subMenu.*.sort' => 'required|integer',
            'subMenu.*.parentId' => 'sometimes|string',
        ]);

        DB::beginTransaction();
        try {
            // 更新主菜单
            if (! empty($validated['mainMenu'])) {
                foreach ($validated['mainMenu'] as $item) {
                    AdminMenuRule::where('menu_key', $item['id'])->update([
                        'title_key' => $item['labelKey'],
                        'path' => $item['path'],
                        'icon' => $item['icon'] ?? null,
                        'visible' => $item['visible'],
                        'sort_order' => $item['sort'],
                    ]);
                }
            }

            // 更新子菜单
            if (! empty($validated['subMenu'])) {
                foreach ($validated['subMenu'] as $item) {
                    AdminMenuRule::where('menu_key', $item['id'])->update([
                        'title_key' => $item['labelKey'],
                        'path' => $item['path'],
                        'icon' => $item['icon'] ?? null,
                        'visible' => $item['visible'],
                        'sort_order' => $item['sort'],
                        'parent_key' => $item['parentId'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Menu config updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to update menu config', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateVisibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'visible' => 'required|boolean',
        ]);

        $menu = AdminMenuRule::where('menu_key', $validated['id'])->first();

        if (! $menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $menu->update(['visible' => $validated['visible']]);

        return response()->json(['message' => 'Visibility updated successfully']);
    }
}
