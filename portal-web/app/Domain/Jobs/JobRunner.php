<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/**
 * UI.md #83 / #84 — Job 执行追踪 + 失败告警。
 *
 * 写入 job_executions；连续失败 3 次触发告警通道。
 */
final class JobRunner
{
    /**
     * @param callable():array $work 业务执行闭包，返回结果会写入 meta
     * @return array{ok:bool, execution_id:int, consecutive_failures:int}
     */
    public static function run(string $jobType, callable $work): array
    {
        $exec = \App\Models\JobExecution::create([
            'job_type' => $jobType,
            'status' => \App\Models\JobExecution::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $start = microtime(true);
        try {
            $result = $work();
            $duration = (int) ((microtime(true) - $start) * 1000);
            $exec->update([
                'status' => \App\Models\JobExecution::STATUS_SUCCESS,
                'finished_at' => now(),
                'duration_ms' => $duration,
                'consecutive_failures' => 0,
                'meta' => is_array($result) ? $result : ['result' => $result],
            ]);
            return ['ok' => true, 'execution_id' => $exec->id, 'consecutive_failures' => 0];
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);
            $prevFails = (int) \App\Models\JobExecution::where('job_type', $jobType)
                ->orderByDesc('id')->value('consecutive_failures');
            $nextFails = $prevFails + 1;
            $exec->update([
                'status' => \App\Models\JobExecution::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => $duration,
                'consecutive_failures' => $nextFails,
                'error_message' => $e->getMessage(),
            ]);
            if ($nextFails >= \App\Models\JobExecution::FAILURE_THRESHOLD) {
                self::alert($jobType, $nextFails, $e->getMessage());
            }
            return ['ok' => false, 'execution_id' => $exec->id, 'consecutive_failures' => $nextFails];
        }
    }

    /**
     * UI.md #84 — 失败告警通道。
     * 当前实现：写 admin_audit_logs 作为系统侧告警记录，
     * 真实邮件/Webhook 接入可在此处扩展。
     */
    public static function alert(string $jobType, int $consecutiveFailures, string $error): void
    {
        if (! class_exists(\App\Models\AdminAuditLog::class)) {
            return;
        }
        try {
            \App\Models\AdminAuditLog::record(
                action: 'job.alert',
                targetType: 'job_execution',
                targetId: null,
                payload: [
                    'job_type' => $jobType,
                    'consecutive_failures' => $consecutiveFailures,
                    'error' => $error,
                ],
                actorId: 'system',
            );
        } catch (\Throwable) {
            // 告警本身失败不抛
        }
    }
}
