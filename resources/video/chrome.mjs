/*
 * Headless Chromium driven over --remote-debugging-pipe, shared by
 * `render.mjs` and `capture.mjs`: no npm package needed, the shipped image
 * has Node but no node_modules.
 *
 * Whatever goes wrong (the browser will not start, stops, or the run takes
 * too long) prints why and exits 1, which is what the PHP side reads.
 */
import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'

export function launch (chrome, timeoutMs, flags = []) {
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
        ...flags,
        // Chromium's own sandbox cannot start inside a container (as root, or
        // without user namespaces): there the container is the sandbox.
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

    return { browser, send, once, listeners, evaluate, fail, errors: () => stderr }
}
