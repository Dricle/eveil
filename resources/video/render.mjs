/*
 * Renders a video page (see page.html) to an MP4, or to still frames.
 *
 *   node render.mjs <page.html> <out.mp4>
 *   node render.mjs <page.html> <out-dir> --stills=1.5,4,7.2
 *   node render.mjs <page.html> - --manifest
 *
 * Drives headless Chromium over --remote-debugging-pipe, so it needs no npm
 * package at all: the shipped image has Node but no node_modules. Each frame
 * is a seek followed by a screenshot piped into ffmpeg, which also lays the
 * composition's sound cues over the picture.
 *
 * The page runs code written by a model, so it gets no network: every
 * request that is not a local file is failed before it leaves the browser.
 *
 * Binaries come from CHROME_BINARY and FFMPEG_BINARY, defaulting to
 * `chromium` and `ffmpeg` on the PATH. Prints the manifest as JSON on success.
 */
import { spawn } from 'node:child_process'
import { existsSync, mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const [page, out, ...flags] = process.argv.slice(2)
const stills = flags.find(flag => flag.startsWith('--stills='))?.slice(9).split(',').map(Number).filter(t => t >= 0)
const manifestOnly = flags.includes('--manifest')
const runtime = dirname(fileURLToPath(import.meta.url))
const sounds = resolve(runtime, 'sounds')
// The only files the page may read: its own staged directory and the runtime.
const readable = [dirname(resolve(page ?? '.')), runtime].map(dir => pathToFileURL(dir).href + '/')
const chrome = process.env.CHROME_BINARY || 'chromium'
const ffmpeg = process.env.FFMPEG_BINARY || 'ffmpeg'
const timeoutMs = Number(process.env.VIDEO_TIMEOUT_MS || 600_000)

if (!page || !out) {
    console.error('Usage: node render.mjs <page.html> <out.mp4|out-dir|-> [--stills=t1,t2|--manifest]')
    process.exit(2)
}

const browser = spawn(chrome, [
    '--headless=new',
    '--remote-debugging-pipe',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-gpu',
    '--disable-extensions',
    '--disable-background-networking',
    '--hide-scrollbars',
    '--mute-audio',
    '--allow-file-access-from-files',
    // Chromium's own sandbox cannot start inside a container (as root, or
    // without user namespaces): there the container is the sandbox, and the
    // page still gets no network.
    ...(process.getuid?.() === 0 || existsSync('/.dockerenv') ? ['--no-sandbox'] : [])
], { stdio: ['ignore', 'ignore', 'pipe', 'pipe', 'pipe'] })

let stderr = ''
browser.stderr.on('data', chunk => (stderr = (stderr + chunk).slice(-4000)))
const fail = (message) => {
    console.error(message)
    browser.kill('SIGKILL')
    process.exit(1)
}
browser.on('error', error => fail(`Could not start ${chrome}: ${error.message}`))
browser.on('exit', code => fail(`${chrome} stopped (exit ${code}):\n${stderr}`))
browser.stdio[4].on('error', () => {})
browser.stdio[3].on('error', () => {})
setTimeout(() => fail(`Timed out after ${timeoutMs} ms`), timeoutMs).unref()

/* The DevTools protocol over the pipe: NUL-terminated JSON both ways. */
const pending = new Map()
const listeners = []
let nextId = 1
let buffer = ''
browser.stdio[4].on('data', (chunk) => {
    buffer += chunk.toString()
    let end
    while ((end = buffer.indexOf('\0')) !== -1) {
        const message = JSON.parse(buffer.slice(0, end))
        buffer = buffer.slice(end + 1)
        if (message.id && pending.has(message.id)) {
            const { resolve: done, reject } = pending.get(message.id)
            pending.delete(message.id)
            message.error ? reject(new Error(message.error.message)) : done(message.result)
        } else {
            listeners.forEach(listener => listener(message))
        }
    }
})

const send = (method, params = {}, sessionId) =>
    new Promise((done, reject) => {
        const id = nextId++
        pending.set(id, { resolve: done, reject })
        browser.stdio[3].write(JSON.stringify({ id, method, params, sessionId }) + '\0')
    })

const once = method => new Promise(done => listeners.push(message => message.method === method && done(message.params)))

const evaluate = async (sessionId, expression) => {
    const { result, exceptionDetails } = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId)
    if (exceptionDetails) {
        throw new Error(exceptionDetails.exception?.description ?? exceptionDetails.text)
    }

    return result.value
}

try {
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' })
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true })

    // The staged files and the runtime only: anything else, another file on
    // this machine included, is refused before it is read.
    listeners.push((message) => {
        if (message.method === 'Fetch.requestPaused') {
            const url = message.params.request.url
            const local = url.startsWith('data:') || readable.some(dir => url.startsWith(dir))
            send(local ? 'Fetch.continueRequest' : 'Fetch.failRequest', local ? { requestId: message.params.requestId } : { requestId: message.params.requestId, errorReason: 'BlockedByClient' }, sessionId).catch(() => {})
        }
    })
    await send('Fetch.enable', { patterns: [{ urlPattern: '*' }] }, sessionId)

    const errors = []
    listeners.push((message) => {
        if (message.method === 'Runtime.exceptionThrown') {
            errors.push(message.params.exceptionDetails.exception?.description ?? message.params.exceptionDetails.text)
        }
    })
    await send('Runtime.enable', {}, sessionId)
    await send('Page.enable', {}, sessionId)

    const loaded = once('Page.loadEventFired')
    await send('Page.navigate', { url: pathToFileURL(resolve(page)).href }, sessionId)
    await loaded

    const manifest = await evaluate(sessionId, `new Promise((done, fail) => {
        if (window.video) return done(window.video.manifest());
        if (window.videoError) return fail(new Error(window.videoError));
        window.addEventListener('video:ready', (event) => done(event.detail));
        window.addEventListener('video:error', (event) => fail(new Error(event.detail)));
        setTimeout(() => fail(new Error('The composition never finished building.')), 15000);
    })`).catch(error => fail(`${error.message}\n${errors.join('\n')}`))

    // Numbers from the page end up in ffmpeg's arguments: numbers they stay.
    const bounded = (value, min, max) => Math.min(max, Math.max(min, Number(value) || 0))
    manifest.duration = bounded(manifest.duration, 0, 180)
    manifest.fps = bounded(manifest.fps, 1, 60)
    manifest.width = bounded(manifest.width, 16, 2160)
    manifest.height = bounded(manifest.height, 16, 3840)
    manifest.sounds = Array.isArray(manifest.sounds) ? manifest.sounds : []

    if (!manifest.duration) {
        fail(`The composition has no scenes.\n${errors.join('\n')}`)
    }

    await send('Emulation.setDeviceMetricsOverride', { width: manifest.width, height: manifest.height, deviceScaleFactor: 1, mobile: false }, sessionId)

    const frameAt = async (t, format = 'jpeg') => {
        await evaluate(sessionId, `video.seek(${t}); new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done)))`)
        const { data } = await send('Page.captureScreenshot', { format, quality: format === 'jpeg' ? 92 : undefined, optimizeForSpeed: true }, sessionId)

        return Buffer.from(data, 'base64')
    }

    if (manifestOnly) {
        // Only what the composition asks for (its voice lines, its music), so
        // that audio can be made before the real render.
    } else if (stills) {
        mkdirSync(out, { recursive: true })
        for (const t of stills) {
            writeFileSync(resolve(out, `${t.toFixed(2)}.png`), await frameAt(Math.min(t, manifest.duration), 'png'))
        }
    } else {
        const frames = Math.round(manifest.duration * manifest.fps)
        // The page wrote these cues, and the page runs a model's script: only
        // a bundled sound or a file staged for this video, and plain numbers.
        const staged = dirname(resolve(page)) + '/'
        const cues = manifest.sounds.flatMap((cue) => {
            const file = typeof cue.src === 'string' && cue.src.startsWith('file:')
                ? fileURLToPath(cue.src)
                : (/^[a-z]+$/.test(cue.name ?? '') ? resolve(sounds, `${cue.name}.mp3`) : null)
            const inside = file && (resolve(file).startsWith(staged) || resolve(file).startsWith(sounds + '/')) && existsSync(file)
            const at = Number(cue.at)
            const volume = Number(cue.volume)

            return inside && Number.isFinite(at) && Number.isFinite(volume)
                ? [{ file, at: Math.max(0, at), volume: Math.min(2, Math.max(0, volume)), music: cue.music === true }]
                : []
        })
        const inputs = cues.flatMap(cue => ['-i', cue.file])
        const fadeAt = Math.max(0, manifest.duration - 1.5)
        const mix = cues.length
            ? cues.map((cue, i) => cue.music
                ? `[${i + 1}:a]atrim=0:${manifest.duration},afade=t=in:d=0.5,afade=t=out:st=${fadeAt}:d=1.5,volume=${cue.volume}[s${i}]`
                : `[${i + 1}:a]adelay=${Math.round(cue.at * 1000)}:all=1,volume=${cue.volume}[s${i}]`).join(';') + `;${cues.map((_, i) => `[s${i}]`).join('')}amix=inputs=${cues.length}:normalize=0,apad[a]`
            : 'anullsrc=r=48000:cl=stereo[a]'

        const encoder = spawn(ffmpeg, [
            '-y', '-loglevel', 'error',
            '-f', 'image2pipe', '-framerate', String(manifest.fps), '-c:v', 'mjpeg', '-i', '-',
            ...inputs,
            '-filter_complex', mix,
            '-map', '0:v', '-map', '[a]',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '160k', '-ar', '48000',
            '-t', String(frames / manifest.fps),
            '-movflags', '+faststart',
            out
        ], { stdio: ['pipe', 'ignore', 'pipe'] })
        let encoderErrors = ''
        encoder.stderr.on('data', chunk => (encoderErrors += chunk))
        const encoded = new Promise(done => encoder.on('close', done))

        for (let frame = 0; frame < frames; frame++) {
            const jpeg = await frameAt(frame / manifest.fps)
            if (!encoder.stdin.write(jpeg)) {
                await new Promise(done => encoder.stdin.once('drain', done))
            }
        }
        encoder.stdin.end()
        if ((await encoded) !== 0) {
            fail(`ffmpeg failed: ${encoderErrors}`)
        }
    }

    console.log(JSON.stringify(manifest))
    browser.kill()
    process.exit(0)
} catch (error) {
    fail(`${error.message}\n${stderr}`)
}
