#!/usr/bin/env bash
# ============================================================
# ocer-dns 一键启动脚本：3 端（dns-console-web 已并入 portal-web）
#   1) portal-web        Laravel API  :8081  + Vite :5173
#   2) dns-resolver      Go           DoH:8444  UDP/TCP:5355
#   3) dns-resolver-2    Go           DoH:8445  UDP/TCP:5356 (若 server-node2.yaml 存在)
#   3) geodns            Go           :15354
#
# 用法:
#   ./start-all.sh           # 启动所有端
#   ./start-all.sh status    # 查看状态
#   ./start-all.sh stop      # 停止所有端（等价 stop-all.sh）
#
# 停止:
#   ./stop-all.sh
# ============================================================

set -euo pipefail

# ---- 路径与常量（不依赖外部 env） -------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUN_DIR="${SCRIPT_DIR}/.run"
LOG_DIR="${SCRIPT_DIR}/logs"
mkdir -p "${RUN_DIR}"
mkdir -p "${LOG_DIR}"

# 端口定义（与 vite.config.js / server.yaml / config.example.yaml 保持一致）
PORT_PORTAL_API=8081
PORT_PORTAL_WEB=5173
PORT_RESOLVER_DOH=443
PORT_RESOLVER_UDP=53
PORT_RESOLVER_TCP=53
PORT_RESOLVER_DOT=853
PORT_RESOLVER_DoQ=784
PORT_GEODNS=15354

ALL_PORTS=(
    "${PORT_PORTAL_API}" "${PORT_PORTAL_WEB}"
    "${PORT_RESOLVER_DOH}" "${PORT_RESOLVER_UDP}" "${PORT_RESOLVER_TCP}" "${PORT_RESOLVER_DOT}" "${PORT_RESOLVER_DoQ}"
    "${PORT_GEODNS}"
)

# ---- 颜色（终端禁用时自动降级） ---------------------------------
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

# ---- 工具函数 ---------------------------------------------------
# 端口占用检测：返回 0 表示空闲，1 表示被占用
port_free() {
    local port="$1"
    if command -v lsof >/dev/null 2>&1; then
        lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1 && return 1 || return 0
    elif command -v nc >/dev/null 2>&1; then
        nc -z 127.0.0.1 "${port}" >/dev/null 2>&1 && return 1 || return 0
    else
        # 退化路径：使用 /dev/tcp 探测，失败即视为占用/不可用
        (echo >"/dev/tcp/127.0.0.1/${port}") >/dev/null 2>&1 && return 1 || return 0
    fi
}

# 写 PID：覆盖旧 PID 前先 kill -0 验证
write_pid() {
    local name="$1" pid="$2"
    local pidfile="${RUN_DIR}/${name}.pid"
    if [[ -f "${pidfile}" ]]; then
        local old
        old="$(cat "${pidfile}" 2>/dev/null || true)"
        if [[ -n "${old}" ]] && kill -0 "${old}" 2>/dev/null; then
            log_warn "${name} 似乎已在运行 (pid=${old})，将停止旧进程后重启"
            kill "${old}" 2>/dev/null || true
            sleep 1
        fi
    fi
    echo "${pid}" > "${pidfile}"
}

# 启动并后台化
spawn() {
    local name="$1"; shift
    local logfile="${LOG_DIR}/${name}.log"
    log_info "启动 ${name}: $*"
    # nohup 免疫 SIGHUP；不引入 setsid 以兼容 macOS（macOS 无 setsid 命令）
    nohup bash -c "$*" </dev/null >"${logfile}" 2>&1 &
    local pid=$!
    write_pid "${name}" "${pid}"
    # 给子进程 200ms 启动时间，立即检测是否已异常退出
    sleep 0.2
    if ! kill -0 "${pid}" 2>/dev/null; then
        log_err "${name} 启动后立即退出，查看日志: ${logfile}"
        return 1
    fi
    log_ok "${name} 已启动 (pid=${pid}, log=${logfile})"
}

# 依赖检测（缺一即失败，不做兜底）
check_deps() {
    log_title "依赖检查"
    local missing=0
    local cmd
    for cmd in php composer node npm go; do
        if command -v "${cmd}" >/dev/null 2>&1; then
            local ver=""
            case "${cmd}" in
                php)       ver="$(php -v 2>/dev/null | head -n1 | awk '{print $2}')" ;;
                composer)  ver="$(composer --version 2>/dev/null | awk '{print $3}')" ;;
                node)      ver="$(node -v 2>/dev/null)" ;;
                npm)       ver="$(npm -v 2>/dev/null)" ;;
                go)        ver="$(go version 2>/dev/null | awk '{print $3}')" ;;
            esac
            log_ok "${cmd} ${ver}"
        else
            log_err "未找到命令: ${cmd}"
            missing=$((missing + 1))
        fi
    done
    if [[ "${missing}" -gt 0 ]]; then
        log_err "缺少 ${missing} 个依赖，请先安装后再启动"
        return 1
    fi
}

# 端口检测（占用即失败，不做兜底）
check_ports() {
    log_title "端口检查"
    local busy=()
    local p
    for p in "${ALL_PORTS[@]}"; do
        if port_free "${p}"; then
            log_ok ":${p} 空闲"
        else
            log_err ":${p} 已被占用"
            busy+=("${p}")
        fi
    done
    if [[ ${#busy[@]} -gt 0 ]]; then
        log_err "请先释放端口: ${busy[*]}"
        return 1
    fi
}

# portal-web 一并承担会员控制台 + 总后台
start_portal() {
    log_title "启动 portal-web (Laravel :${PORT_PORTAL_API} + Vite :${PORT_PORTAL_WEB})"
    [[ -f "${SCRIPT_DIR}/portal-web/composer.json" ]]   || { log_err "缺少 portal-web/composer.json"; return 1; }
    [[ -f "${SCRIPT_DIR}/portal-web/web/package.json" ]] || { log_err "缺少 portal-web/web/package.json"; return 1; }
    [[ -f "${SCRIPT_DIR}/portal-web/.env" ]]            || { log_err "缺少 portal-web/.env，请先 cp .env.example .env"; return 1; }
    spawn portal-api   "cd '${SCRIPT_DIR}/portal-web' && php artisan serve --host=0.0.0.0 --port=${PORT_PORTAL_API}"
    spawn portal-web   "cd '${SCRIPT_DIR}/portal-web/web' && npm run dev -- --host 0.0.0.0 --port ${PORT_PORTAL_WEB}"
}

start_resolver() {
    log_title "启动 dns-resolver (Go: DoH :${PORT_RESOLVER_DOH} DoT :${PORT_RESOLVER_DOT} DoQ :${PORT_RESOLVER_DoQ} UDP/TCP :${PORT_RESOLVER_UDP})"
    [[ -f "${SCRIPT_DIR}/dns-resolver/go.mod" ]]     || { log_err "缺少 dns-resolver/go.mod"; return 1; }
    [[ -f "${SCRIPT_DIR}/dns-resolver/server.yaml" && -f "${SCRIPT_DIR}/dns-resolver/configs/server.yaml" ]] \
        || true   # 配置文件可能由 install 子命令生成；这里不强制阻断
    spawn resolver     "cd '${SCRIPT_DIR}/dns-resolver' && RESOLVER_CONFIG='configs/server.yaml' go run ./cmd/dns-resolver"
}

start_geodns() {
    log_title "启动 geodns (Go: :${PORT_GEODNS})"
    [[ -f "${SCRIPT_DIR}/geodns/go.mod" ]] || { log_err "缺少 geodns/go.mod"; return 1; }
    spawn geodns       "cd '${SCRIPT_DIR}/geodns' && GEODNS_CONFIG='configs/config.yaml' go run ./cmd/geodns"
}

# 入口汇总
start_all() {
    log_title "ocer-dns 一键启动"
    log_info "工作目录: ${SCRIPT_DIR}"
    log_info "PID 目录:  ${RUN_DIR}"
    log_info "日志目录:  ${LOG_DIR}"

    check_deps
    check_ports

    # 任一端启动失败 -> 全部回滚
    if ! start_portal || ! start_resolver || ! start_geodns; then
        log_err "启动过程中出现错误，正在回滚已启动进程 ..."
        "${SCRIPT_DIR}/stop-all.sh" >/dev/null 2>&1 || true
        return 1
    fi

    log_title "全部启动完成"
    cat <<EOF
${C_GREEN}访问入口:${C_RESET}
  portal-web (会员 + 总后台) : http://localhost:${PORT_PORTAL_WEB}
  dns-resolver DoH           : https://localhost:${PORT_RESOLVER_DOH}/dns-query
  dns-resolver DoT           : 127.0.0.1:${PORT_RESOLVER_DOT}
  dns-resolver DoQ           : 127.0.0.1:${PORT_RESOLVER_DoQ}
  dns-resolver UDP/TCP       : 127.0.0.1:${PORT_RESOLVER_UDP}
  geodns                     : http://localhost:${PORT_GEODNS}

${C_GREEN}查看日志:${C_RESET}
  tail -f ${LOG_DIR}/*.log

${C_GREEN}停止服务:${C_RESET}
  ./stop-all.sh
EOF
}

# 子命令 status
do_status() {
    log_title "服务状态"
    if [[ ! -d "${RUN_DIR}" ]] || [[ -z "$(ls -A "${RUN_DIR}" 2>/dev/null)" ]]; then
        log_warn "无运行中的进程（.run/ 为空）"
        return 0
    fi
    local f pid name
    for f in "${RUN_DIR}"/*.pid; do
        [[ -f "${f}" ]] || continue
        name="$(basename "${f}" .pid)"
        pid="$(cat "${f}" 2>/dev/null || true)"
        if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
            log_ok "${name} pid=${pid} 正在运行"
        else
            log_warn "${name} pid=${pid:-?} 已退出（陈旧 pidfile 可清理）"
        fi
    done
}

case "${1:-start}" in
    start)   start_all ;;
    status)  do_status ;;
    stop)    exec "${SCRIPT_DIR}/stop-all.sh" ;;
    *)       log_err "未知子命令: $1"; echo "用法: $0 {start|status|stop}"; exit 2 ;;
esac
