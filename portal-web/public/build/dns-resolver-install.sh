#!/usr/bin/env bash
# =============================================================================
#  OcerDNS DNS Resolver Node One-click Install Script
#  Usage:
#    curl -fsSL https://<host>/build/dns-resolver-install.sh | sh -s -- \
#         --server=https://<host> \
#         --token=xxxxx \
#         --node-id=xxxxx
#
#  Behavior:
#    1) Detect OS / Architecture
#    2) Download dns-resolver-linux-<arch> from ${server}/build/
#    3) Install to /usr/local/bin/dns-resolver
#    4) Run `dns-resolver install --server=... --token=... --node-id=...`
# =============================================================================

set -euo pipefail

# ---------- Argument Parsing ----------
SERVER=""
TOKEN=""
NODE_ID=""

usage() {
  cat <<EOF
Usage: $0 --server <url> --token <token> --node-id <id>

Options:
  --server     Console Base URL, e.g. https://console.ocerlink.com
  --token      Node token issued by console
  --node-id    Node code
  -h, --help   Show this help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --server=*)   SERVER="${1#*=}"; shift ;;
    --server)     SERVER="$2"; shift 2 ;;
    --token=*)    TOKEN="${1#*=}"; shift ;;
    --token)      TOKEN="$2"; shift 2 ;;
    --node-id=*)  NODE_ID="${1#*=}"; shift ;;
    --node-id)    NODE_ID="$2"; shift 2 ;;
    -h|--help)    usage; exit 0 ;;
    *)            echo "Unknown argument: $1" >&2; usage; exit 1 ;;
  esac
done

if [[ -z "$SERVER" || -z "$TOKEN" || -z "$NODE_ID" ]]; then
  echo "Missing required arguments" >&2
  usage
  exit 1
fi

# ---------- OS / Architecture Detection ----------
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"

case "$OS" in
  linux)   SUFFIX_OS="linux" ;;
  *)       echo "Unsupported OS: $OS (Linux only)" >&2; exit 1 ;;
esac

case "$ARCH" in
  x86_64|amd64)   SUFFIX_ARCH="amd64" ;;
  aarch64|arm64)  SUFFIX_ARCH="arm64" ;;
  *)              echo "Unsupported arch: $ARCH" >&2; exit 1 ;;
esac

echo "Detected platform: ${SUFFIX_OS}/${SUFFIX_ARCH}"

# ---------- Download Binary ----------
BIN_NAME="dns-resolver"
INSTALL_DIR="/usr/local/bin"
INSTALL_PATH="${INSTALL_DIR}/${BIN_NAME}"
DOWNLOAD_URL="${SERVER%/}/build/dns-resolver-${SUFFIX_OS}-${SUFFIX_ARCH}"

echo "Downloading: ${DOWNLOAD_URL}"

TMP_BIN="$(mktemp -t ocnd-bin-XXXXXX)"
trap 'rm -f "$TMP_BIN"' EXIT

if ! curl -fsSL --retry 3 -o "$TMP_BIN" "$DOWNLOAD_URL"; then
  echo "Download failed: $DOWNLOAD_URL" >&2
  exit 1
fi

chmod +x "$TMP_BIN"

# Verify downloaded file is a valid ELF executable
if ! head -c 4 "$TMP_BIN" | grep -q "ELF"; then
  echo "Downloaded content is not a valid ELF executable" >&2
  exit 1
fi

# ---------- Install ----------
SUDO=""
if [[ $EUID -ne 0 ]]; then
  SUDO="sudo"
  echo "Not root, will use sudo to install to ${INSTALL_DIR}"
fi

$SUDO mkdir -p "$INSTALL_DIR"
$SUDO mv "$TMP_BIN" "$INSTALL_PATH"
$SUDO chmod 0755 "$INSTALL_PATH"

echo "Installed to ${INSTALL_PATH}"

# ---------- Verify Installation ----------
INSTALLED_VER="$("$INSTALL_PATH" --version 2>/dev/null || echo 'unknown')"
echo "Version: ${INSTALLED_VER}"

# ---------- Run Install Subcommand ----------
echo "Registering node: ${NODE_ID}"
"$INSTALL_PATH" install \
  --server="$SERVER" \
  --token="$TOKEN" \
  --node-id="$NODE_ID"

echo ""
echo "Installation complete. Will run as system service."
echo "  Check status:   systemctl status ${BIN_NAME}   # if using systemd"
echo "  Check logs:     journalctl -u ${BIN_NAME} -f"