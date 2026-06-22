#!/usr/bin/env bash
# ============================================================
# ocer-dns 编译构建脚本
# 功能：
#   1. 自动递增版本号（从 VERSION 文件读取）
#   2. 编译 dns-resolver 和 geodns
#   3. 显示编译版本和时间
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION_FILE="${SCRIPT_DIR}/VERSION"

# 颜色输出
if [[ -t 1 ]]; then
    C_RED='\033[0;31m'; C_GREEN='\033[0;32m'; C_YELLOW='\033[0;33m'
    C_BLUE='\033[0;34m'; C_BOLD='\033[1m'; C_RESET='\033[0m'
else
    C_RED=''; C_GREEN=''; C_YELLOW=''; C_BLUE=''; C_BOLD=''; C_RESET=''
fi

log_info()  { printf "${C_BLUE}[INFO]${C_RESET}  %s\n" "$*"; }
log_ok()    { printf "${C_GREEN}[OK]${C_RESET}    %s\n" "$*"; }
log_title() { printf "\n${C_BOLD}== %s ==${C_RESET}\n" "$*"; }

# 读取当前版本号
read_version() {
    if [[ -f "${VERSION_FILE}" ]]; then
        cat "${VERSION_FILE}"
    else
        echo "1.0.0"
    fi
}

# 递增版本号（补丁版本 +1）
increment_version() {
    local version="$1"
    # 解析版本号：major.minor.patch
    IFS='.' read -r major minor patch <<< "$version"
    patch=$((patch + 1))
    echo "${major}.${minor}.${patch}"
}

# 写入新版本号
write_version() {
    echo "$1" > "${VERSION_FILE}"
}

# 主构建函数
main() {
    log_title "OCER-DNS 构建脚本"
    
    # 获取当前版本并递增
    local current_version=$(read_version)
    local new_version=$(increment_version "$current_version")
    local build_time=$(date '+%Y-%m-%d %H:%M:%S')
    
    log_info "当前版本: ${current_version}"
    log_info "新版本号:  ${new_version}"
    log_info "构建时间:  ${build_time}"
    
    # 写入新版本号
    write_version "${new_version}"
    log_ok "版本号已更新"
    
    # 编译 dns-resolver
    log_title "编译 dns-resolver"
    cd "${SCRIPT_DIR}/dns-resolver"
    go build -ldflags "-X main.version=${new_version} -X main.buildTime=${build_time}" \
        -o "../bin/dns-resolver" \
        ./cmd/dns-resolver/
    log_ok "dns-resolver 编译完成"
    
    # 编译 geodns
    log_title "编译 geodns"
    cd "${SCRIPT_DIR}/geodns"
    go build -ldflags "-X main.version=${new_version} -X main.buildTime=${build_time}" \
        -o "../bin/geodns" \
        ./cmd/geodns/
    log_ok "geodns 编译完成"
    
    log_title "构建完成"
    printf "\n${C_BOLD}版本信息${C_RESET}\n"
    printf "┌─────────────────────────────────────\n"
    printf "│ 版本号:    %s\n" "${new_version}"
    printf "│ 构建时间:  %s\n" "${build_time}"
    printf "│ dns-resolver: bin/dns-resolver\n"
    printf "│ geodns:      bin/geodns\n"
    printf "└─────────────────────────────────────\n"
}

main "$@"