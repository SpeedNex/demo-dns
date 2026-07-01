<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMenuRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMenuConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $navigationScope = $request->query('scope') === 'navigation';
        $allowedKeys = $navigationScope ? $this->allowedMenuKeys($request) : null;

        $menus = AdminMenuRule::with('children')
            ->roots()
            ->orderBy('sort_order')
            ->get()
            ->when($navigationScope, function ($items) use ($allowedKeys) {
                return $items
                    ->filter(function ($menu) use ($allowedKeys): bool {
                        if (! $menu->visible) {
                            return false;
                        }
                        if ($allowedKeys === null) {
                            return true;
                        }
                        if (in_array($menu->menu_key, $allowedKeys, true)) {
                            return true;
                        }
                        return $menu->children
                            ->where('visible', true)
                            ->contains(fn ($child): bool => in_array($child->menu_key, $allowedKeys, true));
                    })
                    ->values();
            })
            ->map(function ($menu) use ($navigationScope, $allowedKeys) {
                $children = $menu->children;
                if ($navigationScope) {
                    $children = $children
                        ->filter(function ($child) use ($allowedKeys): bool {
                            if (! $child->visible) {
                                return false;
                            }
                            return $allowedKeys === null || in_array($child->menu_key, $allowedKeys, true);
                        })
                        ->values();
                }

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
                    'children' => $children->map(function ($child) {
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

        return response()->json([
            'data' => $menus,
        ]);
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
                        'parent_key' => null,
                        'permission_code' => $item['permissionCode'] ?? null,
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
                        'permission_code' => $item['permissionCode'] ?? null,
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'menu_key' => 'required|string|max:80|unique:admin_menu_rule,menu_key',
            'labelKey' => 'required|string|max:100',
            'path' => 'required|string|max:200',
            'icon' => 'sometimes|string|max:50',
            'visible' => 'required|boolean',
            'sort' => 'required|integer|min:0',
            'parentId' => 'sometimes|string|max:80',
            'permission_code' => 'sometimes|string|max:80',
        ]);

        $menu = AdminMenuRule::create([
            'menu_key' => $validated['menu_key'],
            'title_key' => $validated['labelKey'],
            'path' => $validated['path'],
            'icon' => $validated['icon'] ?? null,
            'visible' => $validated['visible'],
            'sort_order' => $validated['sort'],
            'parent_key' => $validated['parentId'] ?? null,
            'permission_code' => $validated['permission_code'] ?? null,
            'group_key' => 'custom',
        ]);

        return response()->json([
            'data' => [
                'id' => $menu->menu_key,
                'menuKey' => $menu->menu_key,
                'labelKey' => $menu->title_key,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'visible' => $menu->visible,
                'sort' => $menu->sort_order,
                'permissionCode' => $menu->permission_code,
                'parentId' => $menu->parent_key,
            ],
        ], 201);
    }

    private function allowedMenuKeys(Request $request): ?array
    {
        $admin = $request->user();
        if ($admin?->is_super === true) {
            return null;
        }

        return DB::table('admin_role_nav_rules as r')
            ->join('admin_user_roles as ur', 'ur.admin_role_id', '=', 'r.admin_role_id')
            ->where('ur.admin_id', $admin?->admin_id)
            ->where('r.visible', true)
            ->pluck('r.nav_key')
            ->map(fn ($key): string => (string) $key)
            ->unique()
            ->values()
            ->all();
    }
}
