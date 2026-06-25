<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RuleSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * rulesources:sync — 同步所有启用的 rule_sources 威胁情报源到 rule_items 表。
 *
 * 执行逻辑：
 * 1. 遍历 rule_sources 中 enabled=true 的源
 * 2. 按 format (domain_list/adblock/hosts/rpz) 下载并解析
 * 3. 写入 rule_items 表（按 source.category 写入 item.category）
 * 4. 更新 source 的 last_sync_at / last_sync_status / item_count
 *
 * 建议通过 cron 每日执行：
 *   0 3 * * * cd /path && php artisan rulesources:sync >> storage/logs/rulesources-sync.log 2>&1
 */
class SyncRuleSourcesCommand extends Command
{
    protected $signature = 'rulesources:sync {--code= : 只同步指定 code 的源}';
    protected $description = 'Download and sync enabled rule sources into rule_items';

    public function handle(): int
    {
        $query = RuleSource::query()->where('enabled', true);
        if ($code = $this->option('code')) {
            $query->where('code', $code);
        }
        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->warn('No enabled rule sources found.');
            return 0;
        }

        $this->info("Found {$sources->count()} enabled rule sources. Starting sync...");

        $ok = 0;
        $failed = 0;
        foreach ($sources as $source) {
            $this->line("  → syncing: [{$source->code}] {$source->name} ({$source->format}/{$source->category})");
            $source->update([
                'last_sync_status' => 'pending',
                'last_sync_message' => 'Downloading...',
                'last_sync_at' => now(),
            ]);

            try {
                $domains = $this->downloadAndParse($source);
                $imported = $this->writeRuleItems($source, $domains);
                $source->update([
                    'item_count' => $imported,
                    'last_sync_status' => 'ok',
                    'last_sync_message' => "Imported {$imported} rules",
                    'last_sync_at' => now(),
                ]);
                $this->info("    ✓ imported {$imported}");
                $ok++;
            } catch (\Throwable $e) {
                $source->update([
                    'last_sync_status' => 'failed',
                    'last_sync_message' => $e->getMessage(),
                    'last_sync_at' => now(),
                ]);
                $this->error("    ✗ failed: {$e->getMessage()}");
                Log::error('rulesources:sync failed', [
                    'source_id' => $source->id,
                    'code' => $source->code,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Sync done: {$ok} ok, {$failed} failed.");
        return $failed > 0 ? 1 : 0;
    }

    /**
     * @return array<int, string>
     */
    private function downloadAndParse(RuleSource $source): array
    {
        if (empty($source->url)) {
            throw new \RuntimeException('Source URL is empty');
        }

        $response = Http::timeout(60)->retry(2, 1000)->get($source->url);
        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} from {$source->url}");
        }
        $content = $response->body();
        if (empty($content)) {
            throw new \RuntimeException('Empty response');
        }

        return match ($source->format) {
            'domains', 'domain_list' => $this->parseDomainList($content),
            'adblock' => $this->parseAdblock($content),
            'hosts' => $this->parseHosts($content),
            'rpz', 'json' => $this->parseDomainList($content),
            default => throw new \RuntimeException("Unsupported format: {$source->format}"),
        };
    }

    /**
     * @return array<int, string>
     */
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

    /**
     * @return array<int, string>
     */
    private function parseAdblock(string $content): array
    {
        $domains = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '!') || str_starts_with($line, '#')) {
                continue;
            }
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

    /**
     * @return array<int, string>
     */
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
                $ip = $parts[0];
                if (in_array($ip, ['127.0.0.1', '0.0.0.0', '::1'], true)) {
                    if (strtolower($parts[1]) !== 'localhost') {
                        $domains[] = $parts[1];
                    }
                }
            }
        }
        return $domains;
    }

    /**
     * @param array<int, string> $domains
     */
    private function writeRuleItems(RuleSource $source, array $domains): int
    {
        $now = now();
        $batch = [];
        $imported = 0;
        $batchSize = 1000;
        $i = 0;
        $total = count($domains);

        DB::beginTransaction();
        try {
            foreach ($domains as $domain) {
                $domain = strtolower(trim($domain));
                if ($domain === '' || strlen($domain) > 255) {
                    continue;
                }
                $batch[] = [
                    'rule_source_id' => $source->id,
                    'domain' => $domain,
                    'category' => $source->category,
                    'action' => 'block',
                    'created_at' => $now,
                ];
                $i++;
                if ($i % $batchSize === 0) {
                    $this->writeBatch($batch);
                    $imported += count($batch);
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $this->writeBatch($batch);
                $imported += count($batch);
            }

            // 删除本 source 的旧数据中已不存在的（避免脏数据）
            DB::table('rule_items')
                ->where('rule_source_id', $source->id)
                ->whereNotIn('domain', array_map('strtolower', array_slice($domains, 0, 100000)))
                ->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $imported;
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function writeBatch(array $batch): void
    {
        DB::table('rule_items')->upsert(
            $batch,
            ['rule_source_id', 'domain'],
            ['category', 'action'],
        );
    }
}
