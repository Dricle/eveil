<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Eveil at the centre like a sun, prospects on slow orbits around it.
 * Signals leave the centre for one of them; some prospects answer, and the
 * reply travels back in the second brand color and makes the core flare.
 * Canvas 2D; requestAnimationFrame already pauses in a hidden tab.
 */
const CYAN = '111, 211, 236'
const VIOLET = '168, 184, 255'
const RINGS = [0.17, 0.29, 0.41]
const NODES_PER_RING = [5, 8, 11]
const SIGNAL_EVERY_MS = 650
const REPLY_CHANCE = 0.35

type Prospect = { ring: number, angle: number, speed: number, size: number, glow: number }
type Signal = { node: Prospect, startedAt: number, duration: number, reply: boolean }

const canvas = ref<HTMLCanvasElement | null>(null)

let ctx: CanvasRenderingContext2D
let width = 0
let height = 0
let frame: number | null = null
let observer: ResizeObserver | null = null
let lastSignalAt = 0
let coreFlare = 0
let stars: { x: number, y: number, a: number }[] = []
const signals: Signal[] = []

const prospects: Prospect[] = NODES_PER_RING.flatMap((count, ring) => Array.from({ length: count }, (_, i) => ({
    ring,
    angle: (i / count) * Math.PI * 2 + ring,
    speed: (ring % 2 ? -1 : 1) * (0.05 - ring * 0.012),
    size: 1.6 + Math.random() * 1.6,
    glow: 0
})))

function resize () {
    const el = canvas.value!
    const ratio = window.devicePixelRatio || 1
    width = el.clientWidth
    height = el.clientHeight
    el.width = width * ratio
    el.height = height * ratio
    ctx = el.getContext('2d')!
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
    stars = Array.from({ length: Math.round(width * height / 9000) }, () => ({ x: Math.random() * width, y: Math.random() * height, a: Math.random() * 0.25 }))
}

function centre () {
    return { x: width / 2, y: height * 0.4, unit: Math.min(width, height * 1.1) }
}

function position (node: Prospect) {
    const { x, y, unit } = centre()
    const radius = RINGS[node.ring] * unit
    // Flattened orbits read as a disc seen at an angle, not a flat diagram.
    return { x: x + Math.cos(node.angle) * radius, y: y + Math.sin(node.angle) * radius * 0.62 }
}

function easeInOut (t: number) {
    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2
}

function draw (now: number, dt: number) {
    const { x: cx, y: cy, unit } = centre()
    ctx.clearRect(0, 0, width, height)

    for (const star of stars) {
        ctx.fillStyle = `rgba(255, 255, 255, ${star.a})`
        ctx.fillRect(star.x, star.y, 1, 1)
    }

    ctx.lineWidth = 1
    RINGS.forEach((ring, i) => {
        ctx.strokeStyle = `rgba(${CYAN}, ${0.09 - i * 0.02})`
        ctx.beginPath()
        ctx.ellipse(cx, cy, ring * unit, ring * unit * 0.62, 0, 0, Math.PI * 2)
        ctx.stroke()
    })

    // The core: a soft halo that breathes, and flares when a reply lands.
    coreFlare = Math.max(0, coreFlare - dt * 1.5)
    const halo = unit * (0.09 + 0.008 * Math.sin(now / 900) + coreFlare * 0.04)
    const gradient = ctx.createRadialGradient(cx, cy, 0, cx, cy, halo)
    gradient.addColorStop(0, `rgba(${CYAN}, ${0.9})`)
    gradient.addColorStop(0.25, `rgba(${CYAN}, ${0.35 + coreFlare * 0.3})`)
    gradient.addColorStop(1, `rgba(${CYAN}, 0)`)
    ctx.fillStyle = gradient
    ctx.beginPath()
    ctx.arc(cx, cy, halo, 0, Math.PI * 2)
    ctx.fill()
    ctx.fillStyle = '#e9fbff'
    ctx.beginPath()
    ctx.arc(cx, cy, unit * 0.012, 0, Math.PI * 2)
    ctx.fill()

    for (let i = signals.length - 1; i >= 0; i--) {
        const signal = signals[i]
        // A reply waits half a second at the prospect before it leaves.
        if (now < signal.startedAt) {
            continue
        }
        const t = Math.min((now - signal.startedAt) / signal.duration, 1)
        const target = position(signal.node)
        const [from, to] = signal.reply ? [target, { x: cx, y: cy }] : [{ x: cx, y: cy }, target]
        const color = signal.reply ? VIOLET : CYAN
        const head = easeInOut(t)
        const tail = easeInOut(Math.max(0, t - 0.18))
        const hx = from.x + (to.x - from.x) * head
        const hy = from.y + (to.y - from.y) * head

        const trail = ctx.createLinearGradient(from.x + (to.x - from.x) * tail, from.y + (to.y - from.y) * tail, hx, hy)
        trail.addColorStop(0, `rgba(${color}, 0)`)
        trail.addColorStop(1, `rgba(${color}, 0.8)`)
        ctx.strokeStyle = trail
        ctx.lineWidth = 1.5
        ctx.beginPath()
        ctx.moveTo(from.x + (to.x - from.x) * tail, from.y + (to.y - from.y) * tail)
        ctx.lineTo(hx, hy)
        ctx.stroke()

        ctx.fillStyle = `rgba(${color}, 1)`
        ctx.beginPath()
        ctx.arc(hx, hy, 2, 0, Math.PI * 2)
        ctx.fill()

        if (t === 1) {
            signals.splice(i, 1)
            if (signal.reply) {
                coreFlare = 1
            } else {
                signal.node.glow = 1
                if (Math.random() < REPLY_CHANCE) {
                    signals.push({ node: signal.node, startedAt: now + 500, duration: signal.duration, reply: true })
                }
            }
        }
    }

    for (const node of prospects) {
        node.angle += node.speed * dt
        node.glow = Math.max(0, node.glow - dt * 0.6)
        const { x, y } = position(node)
        // Depth: prospects behind the core are dimmer.
        const depth = 0.55 + 0.45 * Math.sin(node.angle)

        if (node.glow > 0) {
            ctx.strokeStyle = `rgba(${CYAN}, ${node.glow * 0.6})`
            ctx.lineWidth = 1
            ctx.beginPath()
            ctx.arc(x, y, node.size + (1 - node.glow) * 14, 0, Math.PI * 2)
            ctx.stroke()
        }

        ctx.fillStyle = node.glow > 0
            ? `rgba(${CYAN}, ${0.5 + node.glow * 0.5})`
            : `rgba(220, 235, 245, ${0.25 + depth * 0.35})`
        ctx.beginPath()
        ctx.arc(x, y, node.size * (0.8 + depth * 0.3), 0, Math.PI * 2)
        ctx.fill()
    }
}

let previous = 0

function loop (now: number) {
    frame = requestAnimationFrame(loop)
    const dt = Math.min((now - (previous || now)) / 1000, 0.05)
    previous = now

    if (now - lastSignalAt > SIGNAL_EVERY_MS) {
        lastSignalAt = now
        const idle = prospects.filter(node => node.glow === 0)
        const node = idle[Math.floor(Math.random() * idle.length)]
        if (node) {
            signals.push({ node, startedAt: now, duration: 1100 + node.ring * 350, reply: false })
        }
    }

    draw(now, dt)
}

onMounted(() => {
    resize()
    observer = new ResizeObserver(resize)
    observer.observe(canvas.value!)

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // ponytail: static frame only, the orbits stay put.
        observer.disconnect()
        observer = new ResizeObserver(() => {
            resize()
            draw(0, 0)
        })
        observer.observe(canvas.value!)
        draw(0, 0)
    } else {
        frame = requestAnimationFrame(loop)
    }
})

onBeforeUnmount(() => {
    if (frame !== null) {
        cancelAnimationFrame(frame)
    }
    observer?.disconnect()
})
</script>

<template>
    <canvas
        ref="canvas"
        aria-hidden="true"
        class="size-full"
    />
</template>
