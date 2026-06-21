<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminRegionController
{
    public function index(Request $request): JsonResponse
    {
        $regions = Region::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $regions,
            'meta' => ['total' => $regions->count()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:regions,code',
            'name' => 'required|string|max:100',
            'status' => ['string', Rule::in(['active', 'disabled'])],
            'note' => 'nullable|string|max:255',
        ]);

        $region = Region::create($validated);

        return response()->json(['data' => $region], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $region = Region::findOrFail($id);

        $validated = $request->validate([
            'code' => 'string|max:20|unique:regions,code,' . $region->id,
            'name' => 'string|max:100',
            'status' => ['string', Rule::in(['active', 'disabled'])],
            'note' => 'nullable|string|max:255',
        ]);

        $region->update($validated);

        return response()->json(['data' => $region]);
    }

    public function destroy(string $id): JsonResponse
    {
        $region = Region::findOrFail($id);
        $region->delete();

        return response()->json(['data' => ['id' => (int) $id, 'deleted' => true]]);
    }
}
