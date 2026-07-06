#!/usr/bin/env bash
# ============================================================
# ocer-dns 一键停止脚本
#   - 阶段 1：读取 .run/*.pid（如有），按进程组 SIGTERM -> 5s -> SIGKILL
#   - 阶段 2：按目标端口（TCP + UDP）扫 lsof，补充收集仍占用的 PID
#   - 阶段 3：去重后统一按进程组杀，删陈旧 pidfile
#   - 不做兜底：发现无法停止的进程直接报错退出
#   - .run/ 为空时不再提前 exit，继续走端口兜底
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUN_DIR="${SCRIPT_DIR}/.run"

# 颜色
if [[ -t 1 ]] && command -v tput >/dev/null 2>&1 && [[ "$(tput colors 2>/dev/null || echo 0)" -ge 8 ]]; then
    C_RED='\033[0;31m'; C_GREEN='\033[0;32m'; C_YELLOW='\033[0;33m'
    C_BLUE='\033[0;34m'; C_BOLD='\033[1m'; C_RESET='\033[0m'
else
    C_RED=''; C_GREEN=''; C_YELLOW=''; C_BLUE=''; C_BOLD=''; C_RESET=''
fi
log_info()  { printf "${C_BLUE}[INFO]${C_RESET}  %s\n" "$*"; }
log_ok()    { printf "${C_GREEN}[OK]${C_RESET}    %s\n" "$*"; }
log_warn()  { printf "${C_YELLOW}[WARN]${C_RESET}  %s\n" "$*"; }
log_err()   { printf "${C_RED}[ERROR]${C_RESET} %s\n" "$*" >&2; }
log_title() { printf "\n${C_BOLD}== %s ==${C_RESET}\n" "$*"; }

# 目标端口（与 start-all.sh 的 ALL_PORTS 保持一致）
TARGET_PORTS=(8081 5173 8443 53 15354)

# 取一个 PID 的进程组 ID（macOS BSD ps / Linux procps 通用）
get_pgid() {
    local pid="$1"
    ps -o pgid= -p "${pid}" 2>/dev/null | tr -d ' ' || true
}

# 杀一个 PID（先杀进程组 SIGTERM，5s 后 SIGKILL）
# 返回 0 = 已退出，1 = 仍存活
kill_target() {
    local pid="$1"
    local label="${2:-${pid}}"
    if ! kill -0 "${pid}" 2>/dev/null; then
        return 0
    fi
    local pgid
    pgid="$(get_pgid "${pid}")"
    log_info "停止 ${label} (pid=${pid}, pgid=${pgid:-?}) ..."

    if [[ -n "${pgid}" ]]; then
        kill -TERM "-${pgid}" 2>/dev/null || kill -TERM "${pid}" 2>/dev/null || true
    else
        kill -TERM "${pid}" 2>/dev/null || true
    fi
    for _ in $(seq 1 10); do
        sleep 0.5
        kill -0 "${pid}" 2>/dev/null || return 0
    done

    log_warn "${label} 5s 内未退出，发送 SIGKILL"
    if [[ -n "${pgid}" ]]; then
        kill -KILL "-${pgid}" 2>/dev/null || kill -KILL "${pid}" 2>/dev/null || true
    else
        kill -KILL "${pid}" 2>/dev/null || true
    fi
    sleep 0.5
    if kill -0 "${pid}" 2>/dev/null; then
        return 1
    fi
    return 0
}

log_title "停止 ocer-dns 所有端"

# 待杀目标池：元素格式 "PID:label"
entries=()

# ---- 阶段 1：兼容旧逻辑，读 .run/*.pid ----
pidfile_seen=0
if [[ -d "${RUN_DIR}" ]] && [[ -n "$(ls -A "${RUN_DIR}" 2>/dev/null)" ]]; then
    for f in "${RUN_DIR}"/*.pid; do
        [[ -f "${f}" ]] || continue
        pidfile_seen=1
        name="$(basename "${f}" .pid)"
        pid="$(cat "${f}" 2>/dev/null || true)"
        rm -f "${f}"
        [[ -z "${pid}" ]] && continue
        entries+=("${pid}:${name}")
    done
fi
if [[ "${pidfile_seen}" -eq 0 ]]; then
    log_warn ".run/ 为空，将仅基于端口兜底"
fi

# ---- 阶段 2：按端口（TCP + UDP）补充收集 ----
if ! command -v lsof >/dev/null 2>&1; then
    log_err "缺少 lsof 命令，无法完成端口扫描"
    exit 1
fi
for port in "${TARGET_PORTS[@]}"; do
    # TCP LISTEN
    while IFS= read -r pid; do
        [[ -n "${pid}" ]] && entries+=("${pid}:port${port}/tcp")
    done < <(lsof -nP -iTCP:"${port}" -sTCP:LISTEN -t 2>/dev/null || true)
    # UDP（resolver 的 UDP/53 等）
    while IFS= read -r pid; do
        [[ -n "${pid}" ]] && entries+=("${pid}:port${port}/udp")
    done < <(lsof -nP -iUDP:"${port}" -t 2>/dev/null || true)
done

# ---- 阶段 3：去重后逐个杀（兼容 macOS 自带 Bash 3，无关联数组） ----
unique_entries=()
if [[ ${#entries[@]} -gt 0 ]]; then
    while IFS= read -r entry; do
        [[ -n "${entry}" ]] && unique_entries+=("${entry}")
    done < <(printf '%s\n' "${entries[@]}" | awk -F: '!seen[$1]++')
fi

if [[ ${#unique_entries[@]} -eq 0 ]]; then
    log_ok "未发现需要停止的进程"
    exit 0
fi

failed=0
for entry in "${unique_entries[@]}"; do
    pid="${entry%%:*}"
    label="${entry#*:}"
    if ! kill_target "${pid}" "${label}"; then
        log_err "${label} (pid=${pid}) 无法停止，请手工处理"
        failed=$((failed + 1))
    else
        log_ok "${label} 已停止"
    fi
done

if [[ "${failed}" -gt 0 ]]; then
    log_err "${failed} 个进程无法停止"
    exit 1
fi

log_ok "所有端已停止"
