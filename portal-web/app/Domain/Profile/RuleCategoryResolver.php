<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use Illuminate\Support\Facades\DB;

/**
 * RuleCategoryResolver
 *
 * 从 rule_sources + rule_items 表中读取所有启用的威胁情报源对应的域名，
 * 按 category 分桶后输出为合成的 rule 数组（list_type="category:<category>:<sub>"）。
 *
 * 在 ProfileConfigBuilder 打包 profile 配置时由调用方注入到 rules 数组中。
 *
 * 输出格式：
 *   [
 *     {rule_id: '', list_type: 'category:security:malware', match_type: 'exact',
 *      domain: 'evil.com', normalized_domain: 'evil.com', action: 'block',
 *      category: 'security:malware', enabled: true},
 *     ...
 *   ]
 */
final class RuleCategoryResolver
{
    /**
     * 加载所有 enabled rule_sources 对应的 rule_items，按 rule_items.category 字段分桶。
     *
     * 注意：rule_items 表没有 dns_ 前缀（项目历史遗留），必须严格使用现有表名。
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadCategoryRules(): array
    {
        // 一次性 JOIN 出所有 enabled 源的域名
        $rows = DB::table('rule_items')
            ->join('rule_sources', 'rule_items.rule_source_id', '=', 'rule_sources.id')
            ->where('rule_sources.enabled', true)
            ->where('rule_items.action', 'block')
            ->select([
                'rule_items.domain',
                'rule_items.category',
                'rule_sources.category as source_category',
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $domain = strtolower(trim((string) $row->domain));
            if ($domain === '' || strlen($domain) > 255) {
                continue;
            }

            // item.category 优先（细分分类，例如 malware/phishing/tracker/adult）
            // 回退到 source.category（粗粒度，security/privacy/parental/custom）
            $itemCat = trim((string) ($row->category ?? 'default'));
            if ($itemCat === '' || $itemCat === 'default') {
                $itemCat = trim((string) ($row->source_category ?? 'default'));
            }

            if ($itemCat === '' || $itemCat === 'default' || $itemCat === 'custom') {
                continue;
            }

            $out[] = [
                'rule_id' => '',
                'list_type' => 'category:' . $itemCat,
                'match_type' => 'exact',
                'domain' => $domain,
                'normalized_domain' => DomainNormalizer::normalize($domain),
                'action' => 'block',
                'category' => $itemCat,
                'enabled' => true,
            ];
        }

        return $out;
    }
}
