<template>
    <div class="auth-shell" :class="variantClass">
        <div class="auth-shell__background">
            <div class="auth-shell__glow auth-shell__glow--one" />
            <div class="auth-shell__glow auth-shell__glow--two" />
            <div class="auth-shell__grid" />
        </div>

        <div class="auth-shell__frame">
            <section class="auth-shell__aside">
                <div class="auth-shell__brand">
                    <div class="auth-shell__logo">{{ logo }}</div>
                    <div>
                        <strong>{{ brand }}</strong>
                        <span>{{ brandTagline }}</span>
                    </div>
                </div>

                <div class="auth-shell__intro">
                    <span class="auth-shell__eyebrow">{{ eyebrow }}</span>
                    <h1>{{ title }}</h1>
                    <p>{{ description }}</p>
                </div>

                <div class="auth-shell__highlights">
                    <div
                        v-for="item in highlights"
                        :key="item.label"
                        class="auth-shell__highlight"
                    >
                        <strong>{{ item.value }}</strong>
                        <span>{{ item.label }}</span>
                    </div>
                </div>
            </section>

            <section class="auth-shell__panel">
                <div class="auth-shell__panel-header">
                    <h2>{{ panelTitle }}</h2>
                    <p v-if="panelSubtitle">{{ panelSubtitle }}</p>
                </div>
                <slot />
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    variant: { type: String, default: 'member' },
    logo: { type: String, default: 'O' },
    brand: { type: String, default: 'OcerDNS' },
    brandTagline: { type: String, default: 'Edge DNS control plane' },
    eyebrow: { type: String, default: 'Secure DNS Platform' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    panelTitle: { type: String, default: '' },
    panelSubtitle: { type: String, default: '' },
    highlights: {
        type: Array,
        default: () => [],
    },
})

const variantClass = computed(() => `auth-shell--${props.variant}`)
</script>

<style scoped>
.auth-shell {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    background:
        radial-gradient(circle at top left, rgba(14, 165, 233, 0.18), transparent 28%),
        radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.14), transparent 24%),
        linear-gradient(180deg, #eff6ff 0%, #f8fafc 48%, #eef2ff 100%);
}

.auth-shell--admin {
    background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.16), transparent 28%),
        radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.12), transparent 24%),
        linear-gradient(180deg, #eff6ff 0%, #f8fafc 45%, #e2e8f0 100%);
}

.auth-shell__background {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.auth-shell__glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(80px);
    opacity: 0.7;
}

.auth-shell__glow--one {
    top: -120px;
    left: -90px;
    width: 360px;
    height: 360px;
    background: rgba(14, 165, 233, 0.18);
}

.auth-shell__glow--two {
    right: -120px;
    bottom: -140px;
    width: 420px;
    height: 420px;
    background: rgba(59, 130, 246, 0.14);
}

.auth-shell__grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
    background-size: 36px 36px;
    mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.4), transparent 85%);
}

.auth-shell__frame {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    max-width: 1240px;
    margin: 0 auto;
    padding: 40px 24px;
    display: grid;
    grid-template-columns: minmax(320px, 1.05fr) minmax(360px, 460px);
    gap: 28px;
    align-items: center;
}

.auth-shell__aside,
.auth-shell__panel {
    border: 1px solid rgba(226, 232, 240, 0.95);
    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
    box-shadow: 0 28px 80px rgba(15, 23, 42, 0.08);
}

.auth-shell__aside {
    padding: 32px;
    border-radius: 32px;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(30, 41, 59, 0.9));
    color: #e2e8f0;
}

.auth-shell__panel {
    padding: 36px;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.94);
}

.auth-shell__brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 40px;
}

.auth-shell__brand strong,
.auth-shell__brand span {
    display: block;
}

.auth-shell__brand strong {
    font-size: 22px;
    color: #fff;
}

.auth-shell__brand span {
    margin-top: 4px;
    font-size: 13px;
    color: rgba(226, 232, 240, 0.72);
}

.auth-shell__logo {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    box-shadow: 0 18px 30px rgba(37, 99, 235, 0.25);
}

.auth-shell--admin .auth-shell__logo {
    background: linear-gradient(135deg, #2563eb, #0f172a);
}

.auth-shell__eyebrow {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    margin-bottom: 16px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #bfdbfe;
    background: rgba(59, 130, 246, 0.16);
}

.auth-shell__intro h1 {
    margin: 0;
    font-size: clamp(34px, 4vw, 52px);
    line-height: 1.04;
    color: #fff;
}

.auth-shell__intro p {
    margin: 16px 0 0;
    max-width: 540px;
    font-size: 16px;
    line-height: 1.75;
    color: rgba(226, 232, 240, 0.82);
}

.auth-shell__highlights {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-top: 36px;
}

.auth-shell__highlight {
    padding: 18px 16px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.auth-shell__highlight strong,
.auth-shell__highlight span {
    display: block;
}

.auth-shell__highlight strong {
    font-size: 20px;
    color: #fff;
}

.auth-shell__highlight span {
    margin-top: 6px;
    font-size: 13px;
    color: rgba(226, 232, 240, 0.72);
}

.auth-shell__panel-header {
    margin-bottom: 24px;
}

.auth-shell__panel-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
}

.auth-shell__panel-header p {
    margin: 8px 0 0;
    font-size: 14px;
    color: #64748b;
}

@media (max-width: 1024px) {
    .auth-shell__frame {
        grid-template-columns: 1fr;
        padding: 24px 18px;
    }

    .auth-shell__aside,
    .auth-shell__panel {
        padding: 24px;
        border-radius: 24px;
    }

    .auth-shell__highlights {
        grid-template-columns: 1fr;
    }
}
</style>
