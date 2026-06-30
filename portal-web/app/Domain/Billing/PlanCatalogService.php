<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanCatalogService
{
    public function ensureDefaults(): void
    {
        if (Plan::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $defaults = [
                [
                    'code' => 'free',
                    'name' => 'Free',
                    'description' => 'For personal baseline protection',
                    'status' => 'active',
                    'sort_order' => 10,
                    'is_featured' => false,
                    'badge' => 'Free',
                    'features' => [
                        '300,000 queries / month',
                        'Basic security protection',
                        'Basic privacy protection',
                        'Up to 2 profiles',
                    ],
                    'limits' => [
                        'monthly_queries' => 300000,
                        'profiles' => 2,
                        'team_members' => 1,
                    ],
                    'prices' => [
                        ['billing_cycle' => 'monthly', 'currency' => 'USD', 'amount_minor' => 0, 'status' => 'active'],
                    ],
                ],
                [
                    'code' => 'pro',
                    'name' => 'Pro',
                    'description' => 'For families and advanced users',
                    'status' => 'active',
                    'sort_order' => 20,
                    'is_featured' => true,
                    'badge' => 'Recommended',
                    'features' => [
                        'Unlimited queries',
                        'Advanced security protection',
                        'Advanced privacy protection',
                        'Parental control',
                        'Unlimited profiles',
                        'Query logs and analytics',
                    ],
                    'limits' => [
                        'monthly_queries' => null,
                        'profiles' => null,
                        'team_members' => 3,
                    ],
                    'prices' => [
                        ['billing_cycle' => 'monthly', 'currency' => 'USD', 'amount_minor' => 399, 'status' => 'active'],
                        ['billing_cycle' => 'yearly', 'currency' => 'USD', 'amount_minor' => 3999, 'original_amount_minor' => 4788, 'status' => 'active'],
                    ],
                ],
                [
                    'code' => 'business',
                    'name' => 'Business',
                    'description' => 'For teams and organizations',
                    'status' => 'active',
                    'sort_order' => 30,
                    'is_featured' => false,
                    'badge' => null,
                    'features' => [
                        'Everything in Pro',
                        'Team management',
                        'Seat-based control',
                        'Priority support',
                    ],
                    'limits' => [
                        'monthly_queries' => null,
                        'profiles' => null,
                        'team_members' => 50,
                    ],
                    'prices' => [
                        ['billing_cycle' => 'monthly', 'currency' => 'USD', 'amount_minor' => 500, 'status' => 'active'],
                    ],
                ],
            ];

            foreach ($defaults as $payload) {
                $this->store($payload);
            }
        });
    }

    public function adminList(): array
    {
        $this->ensureDefaults();

        $plans = Plan::query()
            ->with('prices')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 2026-06-30: 附加 user_count 字段（订阅了该 plan_code 的用户数）
        // 用途：/admin/plans 页面"用户数"列 + 抽屉入口
        $userCounts = DB::table('users')
            ->whereIn('plan_code', $plans->pluck('code'))
            ->select('plan_code', DB::raw('COUNT(*) as cnt'))
            ->groupBy('plan_code')
            ->pluck('cnt', 'plan_code');

        return $plans->map(function (Plan $plan) use ($userCounts): array {
            $data = $this->serializePlan($plan);
            $data['user_count'] = (int) ($userCounts[$plan->code] ?? 0);
            return $data;
        })->all();
    }

    public function memberList(): array
    {
        $this->ensureDefaults();

        return Plan::query()
            ->with(['prices' => fn ($query) => $query->where('status', 'active')->orderBy('amount_minor')])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): array => $this->serializePlan($plan))
            ->all();
    }

    public function activePlan(string $code): Plan
    {
        $this->ensureDefaults();

        $plan = Plan::query()
            ->with(['prices' => fn ($query) => $query->where('status', 'active')->orderBy('amount_minor')])
            ->where('code', $code)
            ->where('status', 'active')
            ->first();

        if (! $plan instanceof Plan) {
            throw ValidationException::withMessages([
                'plan_code' => 'Plan not found or inactive.',
            ]);
        }

        return $plan;
    }

    public function store(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $plan = Plan::query()->create([
                'code' => $payload['code'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
                'badge' => $payload['badge'] ?? null,
                'features' => $this->normalizeFeatures($payload['features'] ?? []),
                'limits' => $payload['limits'] ?? [],
            ]);

            $this->syncPrices($plan, $payload['prices'] ?? []);

            return $this->serializePlan($plan->fresh(['prices']));
        });
    }

    public function update(Plan $plan, array $payload): array
    {
        return DB::transaction(function () use ($plan, $payload): array {
            $plan->update([
                'name' => $payload['name'] ?? $plan->name,
                'description' => $payload['description'] ?? $plan->description,
                'status' => $payload['status'] ?? $plan->status,
                'sort_order' => (int) ($payload['sort_order'] ?? $plan->sort_order),
                'is_featured' => (bool) ($payload['is_featured'] ?? $plan->is_featured),
                'badge' => array_key_exists('badge', $payload) ? $payload['badge'] : $plan->badge,
                'features' => array_key_exists('features', $payload) ? $this->normalizeFeatures($payload['features'] ?? []) : $plan->features,
                'limits' => $payload['limits'] ?? $plan->limits,
            ]);

            if (array_key_exists('prices', $payload)) {
                $this->syncPrices($plan, $payload['prices'] ?? []);
            }

            return $this->serializePlan($plan->fresh(['prices']));
        });
    }

    public function delete(Plan $plan): void
    {
        $plan->delete();
    }

    private function syncPrices(Plan $plan, array $prices): void
    {
        $plan->prices()->delete();

        foreach ($prices as $price) {
            $plan->prices()->create([
                'billing_cycle' => $price['billing_cycle'],
                'currency' => strtoupper((string) ($price['currency'] ?? 'USD')),
                'amount_minor' => (int) ($price['amount_minor'] ?? 0),
                'original_amount_minor' => isset($price['original_amount_minor']) ? (int) $price['original_amount_minor'] : null,
                'status' => $price['status'] ?? 'active',
            ]);
        }
    }

    /**
     * @param array<int, mixed> $features
     * @return array<int, string>
     */
    private function normalizeFeatures(array $features): array
    {
        return collect($features)
            ->map(fn ($feature) => trim((string) $feature))
            ->filter(fn (string $feature) => $feature !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'status' => $plan->status,
            'sort_order' => (int) $plan->sort_order,
            'is_featured' => (bool) $plan->is_featured,
            'badge' => $plan->badge,
            'features' => $plan->features ?? [],
            'limits' => $plan->limits ?? [],
            'prices' => $plan->prices->map(fn ($price) => [
                'id' => $price->id,
                'billing_cycle' => $price->billing_cycle,
                'currency' => $price->currency,
                'amount_minor' => (int) $price->amount_minor,
                'original_amount_minor' => $price->original_amount_minor !== null ? (int) $price->original_amount_minor : null,
                'status' => $price->status,
            ])->values()->all(),
        ];
    }
}
