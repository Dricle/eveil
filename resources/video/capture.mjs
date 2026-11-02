/*
 * Photographs a product's website, for a project that has no screenshots
 * of its own: the top of the homepage and further down on a desktop, and
 * the top on a phone. Can also turn a logo Chromium can draw (an SVG) into
 * a PNG with a transparent background.
 *
 *   node capture.mjs <url> <out-dir> [--logo=<url>]
 *
 * Unlike the renderer this page is the open web, so it gets the network,
 * but never this machine's files nor an address on its own network:
 * localhost, private and link-local ranges are refused before they load.
 * Prints what it wrote as JSON.
 */
import { mkdirSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { launch } from './chrome.mjs'

const [url, out, ...flags] = process.argv.slice(2)
const logo = flags.find(flag => flag.startsWith('--logo='))?.slice(7)
const chrome = process.env.CHROME_BINARY || 'chromium'
const timeoutMs = Number(process.env.VIDEO_TIMEOUT_MS || 120_000)

if (!url || !out) {
    console.error('Usage: node capture.mjs <url> <out-dir> [--logo=<url>]')
    process.exit(2)
}

// ponytail: the host as written, not as resolved, so a public name that
// resolves to a private address still passes; resolve it here if the
// instance ever runs where that matters.
const internal = (host) => {
    host = host.replace(/^\[|\]$/g, '').toLowerCase()

    return host === 'localhost' || host.endsWith('.localhost') || host.endsWith('.internal') || host === '::1' || host.startsWith('fc') || host.startsWith('fd') || host.startsWith('fe80')
        || /^(127\.|10\.|0\.|169\.254\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(host)
}
const allowed = (address) => {
    if (address.startsWith('data:') || address.startsWith('blob:')) {
        return true
    }
    try {
        const { protocol, hostname } = new URL(address)

        return ['http:', 'https:'].includes(protocol) && !internal(hostname)
    } catch {
        return false
    }
}

const { browser, send, once, listeners, evaluate, fail, errors } = launch(chrome, timeoutMs)
const pause = ms => new Promise(done => setTimeout(done, ms))

try {
    if (!allowed(url)) {
        fail(`Not an address this can open: ${url}`)
    }

    const { targetId } = await send('Target.createTarget', { url: 'about:blank' })
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true })
    listeners.push((message) => {
        if (message.method === 'Fetch.requestPaused') {
            const ok = allowed(message.params.request.url)
            send(ok ? 'Fetch.continueRequest' : 'Fetch.failRequest', ok ? { requestId: message.params.requestId } : { requestId: message.params.requestId, errorReason: 'BlockedByClient' }, sessionId).catch(() => {})
        }
    })
    await send('Fetch.enable', { patterns: [{ urlPattern: '*' }] }, sessionId)
    await send('Page.enable', {}, sessionId)

    // Loaded, fonts in, and the usual consent banner out of the picture.
    const open = async (address) => {
        const loaded = once('Page.loadEventFired')
        await send('Page.navigate', { url: address }, sessionId)
        await Promise.race([loaded, pause(20_000)])
        await pause(1500)
        await evaluate(sessionId, `(async () => {
            await document.fonts.ready
            document.querySelectorAll('[id*="cookie" i], [class*="cookie" i], [id*="consent" i], [class*="consent" i], [id*="gdpr" i]').forEach(node => node.remove())
        })()`).catch(() => {})
    }
    const shoot = async (name) => {
        const { data } = await send('Page.captureScreenshot', { format: 'png' }, sessionId)
        writeFileSync(resolve(out, name), Buffer.from(data, 'base64'))

        return name
    }

    mkdirSync(out, { recursive: true })
    const shots = []

    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 2, mobile: false }, sessionId)
    await open(url)
    const height = await evaluate(sessionId, 'document.documentElement.scrollHeight').catch(() => 900)
    for (const [i, y] of [0, 900, 1800].entries()) {
        if (i > 0 && y > height - 450) {
            break
        }
        await evaluate(sessionId, `window.scrollTo(0, ${y})`)
        await pause(700)
        shots.push({ file: await shoot(`desktop-${i + 1}.png`), view: i === 0 ? 'desktop, top of the page' : 'desktop, further down the page' })
    }

    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 3, mobile: true }, sessionId)
    await open(url)
    shots.push({ file: await shoot('mobile-1.png'), view: 'phone, top of the page' })

    let logoFile = null
    if (logo && allowed(logo)) {
        await send('Emulation.setDeviceMetricsOverride', { width: 512, height: 512, deviceScaleFactor: 1, mobile: false }, sessionId)
        await send('Emulation.setDefaultBackgroundColorOverride', { color: { r: 0, g: 0, b: 0, a: 0 } }, sessionId)
        await open(`data:text/html,${encodeURIComponent(`<html style="margin:0;background:transparent"><img src="${logo.replace(/"/g, '&quot;')}" style="width:100vw;height:100vh;object-fit:contain"></html>`)}`)
        logoFile = await shoot('logo.png')
    }

    console.log(JSON.stringify({ shots, logo: logoFile }))
    browser.kill()
    process.exit(0)
} catch (error) {
    fail(`${error.message}\n${errors()}`)
}
