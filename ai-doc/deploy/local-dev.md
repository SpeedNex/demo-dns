# 本地开发启动说明

> 当前实现工作区位于 `ocer-dns/`。本文按 `ocer-dns` 目录说明本地启动顺序；如果后续调整为独立仓库，仍保持四包结构不变。

## 0. 一键启动脚本

项目提供了一键启动脚本 `start-all.sh`，可快速启动所有服务：

```bash
cd ocer-dns
./start-all.sh
```

启动脚本支持以下命令：

| 命令 | 说明 |
|------|------|
| `./start-all.sh` | 启动所有服务 |
| `./start-all.sh status` | 查看服务状态 |
| `./start-all.sh stop` | 停止所有服务 |

启动后会显示访问入口：

```text
portal-web (会员 + 总后台) : http://localhost:5173
dns-resolver DoH           : https://localhost:8443/dns-query
dns-resolver UDP/TCP       : 127.0.0.1:53
geodns                     : http://localhost:5354
```

查看日志：

```bash
tail -f ocer-dns/logs/*.log
```

## 1. 启动基础设施

```bash
cd ai-doc-v1/deploy
docker compose up -d mysql redis clickhouse nats
```

检查：

```bash
docker compose ps
docker compose logs -f mysql
```

## 2. 初始化 portal-web

```bash
cd ../../ocer-dns/portal-web
cp ../../ai-doc-v1/deploy/env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan test
php artisan serve --port=8080
```

## 3. 启动 dns-resolver

```bash
cd ../../ocer-dns/dns-resolver
go test ./...
go build ./cmd/dns-resolver
./dns-resolver
```

如果本机没有 root 权限，UDP 使用 `:1053`，DoH 使用 `:8443`。

当前代码状态说明：

- `portal-web` 目前是 Laravel 风格代码骨架，未包含完整框架 vendor。
- `dns-resolver` 和 `geodns` 可以在本地执行 `go test ./...`。
- 如未先安装 PHP / Node / Composer 依赖，Web 端命令无法完成。

## 5. 手动验收

创建一条 deny rule：

```text
ads.example.com
```

发布配置后测试：

```bash
# UDP，端口按本地配置调整
dig @127.0.0.1 -p 1053 ads.example.com A

# DoH 示例，具体 URL 由实现决定
curl -H 'accept: application/dns-message' \
  'https://127.0.0.1:8443/dns-query/{profile_id}?dns=BASE64URL_DNS_QUERY'
```

预期：

- `ads.example.com` 被拦截。
- `example.com` 走上游解析。
- resolver 上报日志。
- portal-web 能看到查询日志和节点状态。

当前阶段限制：

- 上述 4 条预期中，当前真正具备自动化验证的是 Go 侧规则引擎和 GeoDNS 路由测试。
- Web 端尚未安装 Laravel 运行时，因此暂不能完成完整手动验收。

## 5. 生产注意

- 不要使用 `.env.example` 中的密码。
- 不存在 bootstrap token；resolver 凭据三元组由管理员在 console 后台预签发并由 `resolver install` 写入 `configs/server.yaml`。
- DoH / DoT 必须使用正式证书。
- resolver 不应暴露 Agent 控制端口给公网。
- 日志保留与隐私策略必须上线前确认。

## 附录 A：start-all.sh 启动脚本

完整启动脚本位于 `ocer-dns/start-all.sh`，实现了一键启动所有服务：

```bash
#!/usr/bin/env bash
# ============================================================
# ocer-dns 一键启动脚本：3 端（dns-console-web 已并入 portal-web）
#   1) portal-web        Laravel API  :8081  + Vite :5173
#   2) dns-resolver      Go           DoH:8443  UDP/TCP:53
#   3) geodns            Go           :5354
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
mkdir -p "${RUN_DIR}" "${LOG_DIR}"

# 端口定义（与 vite.config.js / server.yaml / config.example.yaml 保持一致）
PORT_PORTAL_API=8081
PORT_PORTAL_WEB=5173
PORT_RESOLVER_DOH=8443
PORT_RESOLVER_UDP=53
PORT_GEODNS=5354

ALL_PORTS=(
    "${PORT_PORTAL_API}" "${PORT_PORTAL_WEB}"
    "${PORT_RESOLVER_DOH}" "${PORT_RESOLVER_UDP}"
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
    nohup bash -c "$*" </dev/null >"${logfile}" 2>&1 &
    local pid=$!
    write_pid "${name}" "${pid}"
    sleep 0.2
    if ! kill -0 "${pid}" 2>/dev/null; then
        log_err "${name} 启动后立即退出，查看日志: ${logfile}"
        return 1
    fi
    log_ok "${name} 已启动 (pid=${pid}, log=${logfile})"
}

# 依赖检测
check_deps() {
    log_title "依赖检查"
    local missing=0
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
    [[ "${missing}" -gt 0 ]] && return 1
}

# 端口检测
check_ports() {
    log_title "端口检查"
    local busy=()
    for p in "${ALL_PORTS[@]}"; do
        if port_free "${p}"; then
            log_ok ":${p} 空闲"
        else
            log_err ":${p} 已被占用"
            busy+=("${p}")
        fi
    done
    [[ ${#busy[@]} -gt 0 ]] && return 1
}

start_portal() {
    log_title "启动 portal-web (Laravel :${PORT_PORTAL_API} + Vite :${PORT_PORTAL_WEB})"
    spawn portal-api   "cd '${SCRIPT_DIR}/portal-web' && php artisan serve --host=0.0.0.0 --port=${PORT_PORTAL_API}"
    spawn portal-web   "cd '${SCRIPT_DIR}/portal-web/web' && npm run dev -- --host 0.0.0.0 --port ${PORT_PORTAL_WEB}"
}

start_resolver() {
    log_title "启动 dns-resolver (Go: DoH :${PORT_RESOLVER_DOH} UDP/TCP :${PORT_RESOLVER_UDP})"
    spawn resolver     "cd '${SCRIPT_DIR}/dns-resolver' && go run ./cmd/dns-resolver"
}

start_geodns() {
    log_title "启动 geodns (Go: :${PORT_GEODNS})"
    spawn geodns       "cd '${SCRIPT_DIR}/geodns' && go run ./cmd/geodns"
}

start_all() {
    log_title "ocer-dns 一键启动"
    log_info "工作目录: ${SCRIPT_DIR}"
    log_info "PID 目录:  ${RUN_DIR}"
    log_info "日志目录:  ${LOG_DIR}"

    check_deps || exit 1
    check_ports || exit 1

    if ! start_portal || ! start_resolver || ! start_geodns; then
        log_err "启动过程中出现错误，正在回滚..."
        "${SCRIPT_DIR}/stop-all.sh" >/dev/null 2>&1 || true
        return 1
    fi
}

do_status() {
    log_title "服务状态"
    [[ ! -d "${RUN_DIR}" ]] || [[ -z "$(ls -A "${RUN_DIR}" 2>/dev/null)" ]] && log_warn "无运行中的进程" && return 0
    for f in "${RUN_DIR}"/*.pid; do
        [[ -f "${f}" ]] || continue
        name="$(basename "${f}" .pid)"
        pid="$(cat "${f}" 2>/dev/null || true)"
        if [[ -n "${pid}" ]] && kill -0 "${pid}" 2>/dev/null; then
            log_ok "${name} pid=${pid} 正在运行"
        else
            log_warn "${name} pid=${pid:-?} 已退出"
        fi
    done
}

case "${1:-start}" in
    start)   start_all ;;
    status)  do_status ;;
    stop)    exec "${SCRIPT_DIR}/stop-all.sh" ;;
    *)       echo "用法: $0 {start|status|stop}"; exit 2 ;;
esac
```

### 启动脚本端口占用说明

| 服务 | 端口 | 说明 |
|------|------|------|
| portal-web (API) | 8081 | Laravel API 服务 |
| portal-web (Web) | 5173 | Vite 开发服务器 |
| dns-resolver (DoH) | 8443 | DNS-over-HTTPS |
| dns-resolver (UDP/TCP) | 53 | 标准 DNS 端口（需 root） |
| geodns | 5354 | GeoDNS 服务 |

### 前端访问地址

- 会员端： http://localhost:5173
- 管理后台： http://localhost:5173/admin
- DNS-over-HTTPS： https://localhost:8443/dns-query
