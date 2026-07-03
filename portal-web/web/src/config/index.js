/**
 * 前端全局配置文件
 * 支持通过环境变量配置
 */

export const SITE_NAME = import.meta.env.VITE_SITE_NAME || 'OcerDNS'
export const SITE_BADGE = import.meta.env.VITE_SITE_BADGE || 'OcerDNS Security Platform'
export const API_BASE = import.meta.env.VITE_API_BASE || '/api/v1'
export const FAVICON_BASE = import.meta.env.VITE_FAVICON_BASE || '/icons'
export const HELP_URL = import.meta.env.VITE_HELP_URL || 'https://help.ocerdns.io'
export const DNS_DOMAIN_DEFAULT = import.meta.env.VITE_DNS_DOMAIN_DEFAULT || 'dns.ocerdns.local'
