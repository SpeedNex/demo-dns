<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\FinanceVerifier;
use App\Domain\Jobs\JobRunner;
use Illuminate\Console\Command;

/**
 * UI.md #78 — 财务对账中心。
 *
 * php artisan finance:verify
 */
final class FinanceVerifyCommand extends Command
{
    protected $signature = 'finance:verify';

    protected $description = '财务对账：校验钱包/订单/账单/退款/订阅一致性';

    public function handle(FinanceVerifier $verifier): int
    {
        $result = JobRunner::run('finance_verify', function () use ($verifier) {
            $rows = $verifier->verify();
            $passed = 0;
            $failed = 0;
            foreach ($rows as $name => $r) {
                $tag = $r['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
                $this->line(sprintf('[%s] <%s> %s — %s', $tag, $name, $r['check'], $r['detail']));
                if (! empty($r['samples'])) {
                    $this->line('  <fg=yellow>samples:</>');
                    foreach (array_slice($r['samples'], 0, 3) as $s) {
                        $this->line('    ' . json_encode($s, JSON_UNESCAPED_UNICODE));
                    }
                }
                $r['ok'] ? $passed++ : $failed++;
            }
            if ($failed > 0) {
                $this->error(sprintf('对账失败：%d 项不通过', $failed));
            } else {
                $this->info(sprintf('对账通过：%d 项', $passed));
            }
            return ['passed' => $passed, 'failed' => $failed];
        });

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
