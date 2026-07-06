#!/usr/bin/env bash
# =============================================================================
#  OcerDNS GeoDNS Node One-click Install Script
#  Usage:
#    curl -fsSL https://<host>/build/geodns-install.sh | bash -s -- \
#         --server=https://<host> \
#         --token=xxxxx \
#         --node-id=xxxxx
#
#  Behavior (2026-06-23 改造):
#    1) Detect OS / Architecture
#    2) Download geodns-<os>-<arch> from ${server}/build/
#    3) Install binary to ${INSTALL_DIR:-/usr/local/bin}/geodns
#    4) Run `geodns install --server=... --token=... --node-id=... --start`
#       - configs/config.yaml → ${GEODNS_HOME:-/usr/local/etc/geodns}/configs/config.yaml
#       - api_key             → ${GEODNS_HOME:-/usr/local/etc/geodns}/configs/api_key
#       - 绝对路径写入 config，避免 CWD 漂移
#    5) --start 默认开启：geodns install 完成后自动拉起服务
#       - Linux 优先 systemd (写 /etc/systemd/system/geodns.service + enable --now)
#       - macOS 使用 launchd (写 /Library/LaunchDaemons/com.ocerdns.geodns.plist)
#       - 降级 nohup 后台进程(写 configs/geodns.pid + configs/geodns.log)
#       - 不要自动启动可用 --no-start
#  Supported platforms: linux/amd64, linux/arm64, darwin/amd64, darwin/arm64
# =============================================================================

set -euo pipefail

# ---------- Argument Parsing ----------
SERVER=""
TOKEN=""
NODE_ID=""
LISTEN_ADDR=":15354"
DNS_ADDR=":53"
INSTALL_DIR="/usr/local/bin"   # binary install dir
GEODNS_HOME="${GEODNS_HOME:-/usr/local/etc/geodns}"  # 2026-06-22: configs 落点
# 2026-06-22: 一键安装默认自动启动节点。
# 显式传 --no-start 可关闭(给想自己控制启动时机的运维用)。
AUTO_START=1
EXTRA_ARGS=()

log() { echo "[install.sh] $*" >&2; }

usage() {
  cat <<EOF
Usage: $0 --server <url> --token <token> --node-id <id> [options]

Required:
  --server     Console Base URL, e.g. https://console.ocerlink.com
  --token      Node token issued by console
  --node-id    Node code

Options:
  --listen-addr    HTTP listen address (default: :15354)
  --dns-addr       DNS listen address (default: :53)
  --install-dir    Binary install dir (default: /usr/local/bin)
  --home           Config + api_key home dir (default: \$GEODNS_HOME or /usr/local/etc/geodns)
  --start          Auto-start geodns after install (default: enabled)
  --no-start       Skip auto-start; you'll start it manually
  --verbose        Pass --verbose to geodns install (request/response details)
  --               Pass remaining args to geodns install (e.g. -- --config=/x)
  -h, --help       Show this help

Environment:
  GEODNS_HOME      Override default config home dir

Examples:
  # 1) Standard install with auto-start (recommended, one-shot)
  curl -fsSL https://console.ocerlink.com/build/geodns-install.sh | bash -s -- \\
       --server=https://console.ocerlink.com \\
       --token=xxxxx \\
       --node-id=phqval3wur

  # 2) Custom home dir
  GEODNS_HOME=/opt/geodns bash geodns-install.sh --server=... --token=... --node-id=...

  # 3) User-mode install (no sudo)
  bash geodns-install.sh --install-dir=$HOME/bin --home=$HOME/.geodns \\
       --server=... --token=... --node-id=...

  # 4) Install but don't start (start later manually)
  bash geodns-install.sh --no-start --server=... --token=... --node-id=...
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --server=*)        SERVER="${1#*=}"; shift ;;
    --server)          SERVER="$2"; shift 2 ;;
    --token=*)         TOKEN="${1#*=}"; shift ;;
    --token)           TOKEN="$2"; shift 2 ;;
    --node-id=*)       NODE_ID="${1#*=}"; shift ;;
    --node-id)         NODE_ID="$2"; shift 2 ;;
    --listen-addr=*)   LISTEN_ADDR="${1#*=}"; shift ;;
    --listen-addr)     LISTEN_ADDR="$2"; shift 2 ;;
    --dns-addr=*)      DNS_ADDR="${1#*=}"; shift ;;
    --dns-addr)        DNS_ADDR="$2"; shift 2 ;;
    --install-dir=*)   INSTALL_DIR="${1#*=}"; shift ;;
    --install-dir)     INSTALL_DIR="$2"; shift 2 ;;
    --home=*)          GEODNS_HOME="${1#*=}"; shift ;;
    --home)            GEODNS_HOME="$2"; shift 2 ;;
    --start)           AUTO_START=1; shift ;;
    --no-start)        AUTO_START=0; shift ;;
    --verbose)         EXTRA_ARGS+=("--verbose"); shift ;;
    --)                shift; while [[ $# -gt 0 ]]; do EXTRA_ARGS+=("$1"); shift; done ;;
    -h|--help)         usage; exit 0 ;;
    *)                 log "ERROR: unknown argument: $1"; usage; exit 1 ;;
  esac
done

if [[ -z "$SERVER" || -z "$TOKEN" || -z "$NODE_ID" ]]; then
  log "ERROR: missing required arguments (--server --token --node-id)"
  usage
  exit 1
fi

# 2026-06-22: 把 --start/--no-start 转成 geodns install 的对应 flag
[[ $AUTO_START -eq 1 ]] && EXTRA_ARGS+=("--start") || EXTRA_ARGS+=("--no-start")

log "starting install: server=$SERVER node_id=$NODE_ID listen=$LISTEN_ADDR dns=$DNS_ADDR"
log "install_dir=$INSTALL_DIR  geodns_home=$GEODNS_HOME  auto_start=$AUTO_START"

# ---------- OS / Architecture Detection ----------
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"

case "$OS" in
  linux)  SUFFIX_OS="linux" ;;
  darwin) SUFFIX_OS="darwin" ;;
  *)      log "ERROR: unsupported OS: $OS (supported: linux, darwin)"; exit 1 ;;
esac

case "$ARCH" in
  x86_64|amd64)   SUFFIX_ARCH="amd64" ;;
  aarch64|arm64)  SUFFIX_ARCH="arm64" ;;
  *)              log "ERROR: unsupported arch: $ARCH"; exit 1 ;;
esac

log "platform: ${SUFFIX_OS}/${SUFFIX_ARCH}"

# ---------- Download Binary ----------
BIN_NAME="geodns"
INSTALL_PATH="${INSTALL_DIR}/${BIN_NAME}"
DOWNLOAD_URL="${SERVER%/}/build/geodns-${SUFFIX_OS}-${SUFFIX_ARCH}"

log "downloading: $DOWNLOAD_URL"

TMP_BIN="$(mktemp -t ocnd-geo-XXXXXX)"
trap 'rm -f "$TMP_BIN"' EXIT

if ! curl -fsSL --retry 3 -o "$TMP_BIN" "$DOWNLOAD_URL" 2>&1 | sed 's/^/[curl] /' >&2; then
  log "ERROR: download failed: $DOWNLOAD_URL"
  exit 1
fi

chmod +x "$TMP_BIN"

# Verify downloaded file is a valid executable (ELF for Linux, Mach-O for macOS)
if ! file "$TMP_BIN" | grep -qE "ELF|Mach-O"; then
  log "ERROR: downloaded content is not a valid executable (not ELF/Mach-O)"
  exit 1
fi

BIN_SIZE=$(stat -c%s "$TMP_BIN" 2>/dev/null || stat -f%z "$TMP_BIN" 2>/dev/null)
log "downloaded ${BIN_SIZE} bytes"

# ---------- Install Binary ----------
SUDO=""
if [[ $EUID -ne 0 ]]; then
  SUDO="sudo"
  log "not root, will use sudo to install to $INSTALL_DIR"
fi

$SUDO mkdir -p "$INSTALL_DIR"
$SUDO mv "$TMP_BIN" "$INSTALL_PATH"
$SUDO chmod 0755 "$INSTALL_PATH"

log "binary installed to $INSTALL_PATH"

# ---------- Verify Installation ----------
INSTALLED_VER="$("$INSTALL_PATH" --version 2>/dev/null || echo 'unknown')"
log "version: $INSTALLED_VER"

# ---------- Prepare Config Home ----------
$SUDO mkdir -p "$GEODNS_HOME/configs"
log "config home: $GEODNS_HOME"

# ---------- Run Install Subcommand ----------
# 2026-06-22: 默认带 --start,geodns install 完成后会自动拉起服务
# (systemd 优先,降级 nohup,见 geodns/cmd/geodns/install.go startService)。
log "running: $INSTALL_PATH install --install-dir=$GEODNS_HOME --config=$GEODNS_HOME/configs/config.yaml --api-key=$GEODNS_HOME/configs/api_key ..."
"$INSTALL_PATH" install \
  --server="$SERVER" \
  --token="$TOKEN" \
  --node-id="$NODE_ID" \
  --listen-addr="$LISTEN_ADDR" \
  --dns-addr="$DNS_ADDR" \
  --install-dir="$GEODNS_HOME" \
  --config="$GEODNS_HOME/configs/config.yaml" \
  --api-key="$GEODNS_HOME/configs/api_key" \
  "${EXTRA_ARGS[@]}"

# 2026-06-22: --start/--no-start 走 geodns install 内部,这里只打收尾提示。
# 启动成功/失败的具体信息(走 systemd 还是 nohup、PID 文件、日志路径)
# 已经在 geodns install 自己的输出里给出,这里不再重复。
if [[ "$OS" == "darwin" ]]; then
  echo ""
  echo "================================================================"
  echo "  Installation complete (macOS)"
  echo "================================================================"
  echo "  Binary:    $INSTALL_PATH"
  echo "  Config:    $GEODNS_HOME/configs/config.yaml"
  echo "  API key:   $GEODNS_HOME/configs/api_key"
  echo ""
  echo "  Manage (launchd):"
  echo "    sudo launchctl load /Library/LaunchDaemons/com.ocerdns.geodns.plist"
  echo "    sudo launchctl unload /Library/LaunchDaemons/com.ocerdns.geodns.plist"
  echo "    sudo launchctl list com.ocerdns.geodns"
  echo ""
  echo "  Manage (nohup fallback):"
  echo "    $GEODNS_HOME/configs/geodns.pid  # pid file"
  echo "    $GEODNS_HOME/configs/geodns.log  # log file"
  echo ""
  echo "  Manual start:"
  echo "    $INSTALL_PATH --config=$GEODNS_HOME/configs/config.yaml"
  echo "================================================================"
else
  echo ""
  echo "================================================================"
  echo "  Installation complete"
  echo "================================================================"
  echo "  Binary:    $INSTALL_PATH"
  echo "  Config:    $GEODNS_HOME/configs/config.yaml"
  echo "  API key:   $GEODNS_HOME/configs/api_key"
  echo ""
  echo "  Manage (systemd):"
  echo "    systemctl status geodns   # status"
  echo "    journalctl -u geodns -f   # tail logs"
  echo "    systemctl restart geodns  # restart"
  echo "    systemctl stop geodns     # stop"
  echo ""
  echo "  Manage (nohup fallback):"
  echo "    $GEODNS_HOME/configs/geodns.pid  # pid file"
  echo "    $GEODNS_HOME/configs/geodns.log  # log file"
  echo "    kill \$(cat $GEODNS_HOME/configs/geodns.pid)  # stop"
  echo ""
  echo "  Manual start (if you passed --no-start):"
  echo "    $INSTALL_PATH --config=$GEODNS_HOME/configs/config.yaml"
  echo "================================================================"
fi
