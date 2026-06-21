#!/usr/bin/env node
/* eslint-disable no-console */

/**
 * Check for missing i18n keys.
 * Scans .vue files for $t('key') calls and verifies keys exist in locale files.
 * Usage: node scripts/check-i18n.js
 */

import { readFileSync, readdirSync, statSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = resolve(__dirname, '..')

function walk(dir, ext) {
  const files = []
  for (const entry of readdirSync(dir)) {
    const full = resolve(dir, entry)
    if (statSync(full).isDirectory()) {
      files.push(...walk(full, ext))
    } else if (entry.endsWith(ext)) {
      files.push(full)
    }
  }
  return files
}

// Extract all $t('key') calls from vue files
function extractKeysFromFile(filePath) {
  const content = readFileSync(filePath, 'utf-8')
  const keys = new Set()
  // Match: $t('key') or $t("key") or t('key')
  const regex = /[$]t\s*\(\s*['"]([^'"]+)['"]/g
  let match
  while ((match = regex.exec(content)) !== null) {
    keys.add(match[1])
  }
  return keys
}

// Flatten locale object to dot-separated keys
function flatten(obj, prefix = '') {
  const result = new Set()
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
      const nested = flatten(value, fullKey)
      nested.forEach(k => result.add(k))
    }
    result.add(fullKey)
  }
  return result
}

// Parse locale JS file and extract the default export object
function extractLocaleKeys(filePath) {
  const content = readFileSync(filePath, 'utf-8')
  // Remove import/export statements, find the object after 'export default'
  const objMatch = content.match(/export\s+default\s+({[\s\S]*})/m)
  if (!objMatch) return new Set()
  try {
    // Use Function constructor to evaluate the object literal (safer than eval)
    const fn = new Function(`return ${objMatch[1]}`)
    const localeObj = fn()
    return flatten(localeObj)
  } catch (e) {
    console.error(`Error parsing ${filePath}: ${e.message}`)
    return new Set()
  }
}

// Main
const vueFiles = walk(resolve(root, 'src'), '.vue')
const localeFiles = [
  resolve(root, 'src/locales/zh-CN.js'),
  resolve(root, 'src/locales/en.js'),
  resolve(root, 'src/locales/ko.js')
]

// Collect all used keys
const usedKeys = new Set()
for (const file of vueFiles) {
  const keys = extractKeysFromFile(file)
  keys.forEach(k => usedKeys.add(k))
}

// Collect all defined keys (union of all locales)
const definedKeys = new Set()
for (const file of localeFiles) {
  try {
    const keys = extractLocaleKeys(file)
    keys.forEach(k => definedKeys.add(k))
  } catch (e) {
    // skip
  }
}

// Find missing keys
const missingKeys = [...usedKeys].filter(k => !definedKeys.has(k)).sort()

if (missingKeys.length > 0) {
  console.error(`\x1b[31m✗ Missing i18n keys (${missingKeys.length}):\x1b[0m`)
  missingKeys.forEach(k => console.error(`  - ${k}`))
  process.exit(1)
} else {
  console.log('\x1b[32m✓ All i18n keys are defined\x1b[0m')
  process.exit(0)
}