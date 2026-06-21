<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\RuleSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class AdminRuleController
{
    public function index(Request $request): JsonResponse
    {
        $query = RuleSource::query();

        if ($request->filled('type')) {
            $query->where('format', $this->mapTypeToFormat((string) $request->input('type')));
        }

        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $sources = $query->orderBy('name')->get()->map(fn (RuleSource $source): array => $this->presentSource($source))->all();

        return response()->json([
            'data' => $sources,
            'meta' => [
                'total' => count($sources),
                'enabled' => count(array_filter($sources, fn (array $s): bool => (bool) $s['enabled'])),
                'last_sync_at' => collect($sources)->max('last_synced_at'),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->presentSource(RuleSource::query()->findOrFail($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(['domain_list', 'adblock', 'hosts', 'rpz'])],
            'url' => 'required|string|url|max:500',
            'enabled' => 'boolean',
        ]);

        $source = RuleSource::create([
            'name' => $validated['name'],
            'format' => $this->mapTypeToFormat($validated['type']),
            'url' => $validated['url'],
            'enabled' => $validated['enabled'] ?? true,
        ]);

        AdminAuditLog::record('rule.create', 'rule_source', $source->id, $this->presentSource($source), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentSource($source)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $source = RuleSource::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:100',
            'type' => ['string', Rule::in(['domain_list', 'adblock', 'hosts', 'rpz'])],
            'url' => 'string|url|max:500',
            'enabled' => 'boolean',
        ]);

        if (isset($validated['type'])) {
            $validated['format'] = $this->mapTypeToFormat($validated['type']);
            unset($validated['type']);
        }

        $source->update($validated);

        AdminAuditLog::record('rule.update', 'rule_source', $id, $this->presentSource($source->fresh()), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentSource($source->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $source = RuleSource::findOrFail($id);
        $source->delete();

        AdminAuditLog::record('rule.delete', 'rule_source', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    public function sync(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $source = RuleSource::findOrFail($id);

        $source->update([
            'last_sync_status' => 'pending',
            'last_sync_at' => now(),
            'last_sync_message' => 'Downloading...',
        ]);

        try {
            // Download the rule source
            $response = Http::timeout(30)->get($source->url);
            $content = $response->body();

            if (empty($content)) {
                throw new \RuntimeException('Empty response from ' . $source->url);
            }

            // Parse based on type
            $domains = match ($source->type) {
                'domain_list' => $this->parseDomainList($content),
                'adblock' => $this->parseAdblock($content),
                'hosts' => $this->parseHosts($content),
                'rpz' => $this->parseRpz($content),
                default => throw new \RuntimeException('Unsupported format: ' . $source->type),
            };

            // Store parsed domains
            $imported = 0;
            $now = now();
            foreach ($domains as $domain) {
                if (empty($domain) || strlen($domain) > 255) {
                    continue;
                }
                DB::table('rule_items')->updateOrInsert(
                    ['rule_source_id' => $source->id, 'domain' => $domain],
                    ['category' => 'default', 'action' => 'block', 'created_at' => $now]
                );
                $imported++;
            }

            $source->update([
                'item_count' => $imported,
                'last_sync_status' => 'ok',
                'last_sync_message' => "Imported {$imported} rules",
                'last_sync_at' => now(),
            ]);

            AdminAuditLog::record('rule.sync', 'rule_source', $id, [
                'imported' => $imported,
                'status' => 'success',
            ], $actorId, null, $request->ip(), $request->userAgent());

        } catch (\Throwable $e) {
            $source->update([
                'last_sync_status' => 'failed',
                'last_sync_message' => $e->getMessage(),
                'last_sync_at' => now(),
            ]);

            AdminAuditLog::record('rule.sync', 'rule_source', $id, [
                'error' => $e->getMessage(),
                'status' => 'failed',
            ], $actorId, null, $request->ip(), $request->userAgent());
        }

        return response()->json([
            'data' => [
                'id' => $id,
                'status' => $source->last_sync_status,
                'message' => $source->last_sync_message,
                'synced_at' => $source->last_synced_at?->toIso8601String(),
            ],
        ]);
    }

    private function parseDomainList(string $content): array
    {
        $domains = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//') || str_starts_with($line, '!')) {
                continue;
            }
            $domains[] = $line;
        }
        return $domains;
    }

    private function parseAdblock(string $content): array
    {
        $domains = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '!') || str_starts_with($line, '#')) {
                continue;
            }
            // Extract domain from AdBlock syntax: ||example.com^ or |https://example.com
            if (preg_match('/^\|\|([a-zA-Z0-9.\-]+)\^/', $line, $m)) {
                $domains[] = $m[1];
            } elseif (preg_match('/^\|https?:\/\/([a-zA-Z0-9.\-]+)/', $line, $m)) {
                $domains[] = $m[1];
            } elseif (preg_match('/^([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})(?:\/|$)/', $line, $m)) {
                $domains[] = $m[1];
            }
        }
        return $domains;
    }

    private function parseHosts(string $content): array
    {
        $domains = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                // Skip localhost and broadcast addresses
                $ip = $parts[0];
                if ($ip === '127.0.0.1' || $ip === '0.0.0.0' || $ip === '::1') {
                    $domains[] = $parts[1];
                }
            }
        }
        return $domains;
    }

    private function parseRpz(string $content): array
    {
        $domains = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }
            // RPZ format: domain IN CNAME .
            if (preg_match('/^([a-zA-Z0-9.\-]+)\s+IN\s+(A|CNAME)\s+/', $line, $m)) {
                $domains[] = $m[1];
            }
        }
        return $domains;
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = RuleSource::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('rule.batch_delete', 'rule_source', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    private function presentSource(RuleSource $source): array
    {
        $row = $source->toArray();
        $row['type'] = $source->type;
        $row['rule_count'] = $source->rule_count;
        $row['last_synced_at'] = $source->last_synced_at?->toIso8601String();

        return $row;
    }

    private function mapTypeToFormat(string $type): string
    {
        return match ($type) {
            'domain_list' => 'domains',
            'rpz' => 'json',
            default => $type,
        };
    }
}
