/*
 * The video runtime: a short social video described as plain JavaScript,
 * played back in any browser and rendered frame by frame by `render.mjs`.
 *
 * Every motion is a Web Animation created paused, so `seek(t)` puts the
 * whole frame exactly where it is at `t` seconds, as many times as asked
 * and in any order. That is what makes the editor's preview and the
 * rendered file the same video: both only ever call `seek`.
 *
 * A composition calls `Video.compose(v => { ... })`: scenes one after the
 * other, each filled with bricks that stack top to bottom. Times inside a
 * scene are seconds from the start of that scene. Screenshot coordinates
 * are fractions of the image (0 to 1), so they survive any image size.
 *
 * Nothing here reaches the network: assets arrive as URLs the page was
 * given, and the renderer refuses every request that is not a local file.
 */
(() => {
    const SOUNDS = ['pop', 'click', 'typing', 'chime', 'reveal']

    // Deliberately plain: a composition sets its own theme from the brand,
    // and a forgotten one should look unbranded rather than like anyone's.
    const DEFAULT_THEME = {
        background: '#121212',
        surface: '#1c1c1c',
        ink: '#f4f4f4',
        muted: 'rgba(244,244,244,.62)',
        accent: '#f4f4f4',
        accent2: '#bdbdbd',
        display: 'Inter',
        body: 'Inter',
        grid: false
    }

    const ease = 'cubic-bezier(.22,1,.36,1)'
    const easeInOut = 'cubic-bezier(.65,0,.35,1)'

    const el = (tag, style = {}, parent = null, text = null) => {
        const node = document.createElement(tag)
        Object.assign(node.style, style)
        if (text !== null) {
            node.textContent = text
        }
        parent?.appendChild(node)

        return node
    }

    // Any CSS colour as #rrggbb, so the bricks can append an alpha to it.
    const probe = document.createElement('canvas').getContext('2d')
    const hex = (color) => {
        probe.fillStyle = '#000000'
        probe.fillStyle = String(color)

        return probe.fillStyle
    }
    const luminance = (color) => {
        const h = hex(color)
        if (!h.startsWith('#')) {
            return 0.5
        }
        const [r, g, b] = [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16) / 255).map(c => c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)

        return 0.2126 * r + 0.7152 * g + 0.0722 * b
    }
    const contrast = (a, b) => {
        const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x)

        return (light + 0.05) / (dark + 0.05)
    }

    // How a scene arrives. The covering ones slide their own colour field
    // over the scene before, which stays put underneath until covered.
    const COVERING = ['push', 'rise', 'wipe', 'iris']

    /** `**words**` become the accent gradient, the rest stays plain text. */
    const rich = (node, text) => {
        String(text)
            .split(/(\*\*[^*]+\*\*)/)
            .filter(Boolean)
            .forEach((part) => {
                const strong = part.startsWith('**')
                const span = el('span', {}, node, strong ? part.slice(2, -2) : part)
                if (strong) {
                    span.className = 'grad'
                }
            })

        return node
    }

    class Video {
        constructor (options = {}) {
            this.width = options.width ?? 1080
            this.height = options.height ?? 1920
            this.fps = options.fps ?? 30
            this.assets = options.assets ?? {}
            this.duration = 0
            this.sounds = []
            this.voices = []
            this.track = null
            this.audio = options.audio ?? { voices: {}, durations: {}, music: null }
            this.tickers = []
            this.scenes = []
            this.unit = Math.min(this.width, this.height) / 1080
            this.root = el('div', { position: 'fixed', inset: '0', width: `${this.width}px`, height: `${this.height}px`, overflow: 'hidden' }, document.body)
            this.theme({})
        }

        /** Colours and fonts. Call it first; anything left out keeps the default. */
        theme (theme) {
            this.colors = { ...DEFAULT_THEME, ...(this.colors ?? {}), ...theme }
            const c = this.colors
            Object.assign(this.root.style, { background: c.background, color: c.ink, fontFamily: `${c.body}, Inter, sans-serif` })
            this.root.style.setProperty('--accent', c.accent)
            this.root.style.setProperty('--accent2', c.accent2)

            return this
        }

        /** Plays one of the bundled sounds `at` seconds into the video. */
        sound (name, at, volume = 0.5) {
            if (SOUNDS.includes(name)) {
                this.sounds.push({ name, at: Math.max(0, at), volume })
            }

            return this
        }

        /** Who reads the voice lines, by the name of one of the narrators offered. */
        narrator (name) {
            this.voiceName = String(name)

            return this
        }

        /**
         * Background music for the whole video, described in words. Made for
         * it when the instance can (an ElevenLabs key), silent otherwise.
         */
        music (prompt, volume = 0.16) {
            this.track = { prompt: String(prompt), volume }

            return this
        }

        /**
         * One scene of `duration` seconds. Scenes play back to back, each
         * arriving by its `transition`: a short zoom and blur by default.
         */
        scene (options, build) {
            const duration = typeof options === 'number' ? options : options.duration
            const scene = new Scene(this, this.duration, duration, typeof options === 'object' ? options : {})
            const previous = this.scenes.at(-1)
            this.scenes.push(scene)
            build(scene)
            scene.finish()
            previous?.leave(scene)
            this.duration += scene.duration

            return this
        }

        animate (node, keyframes, at, duration, easing = ease, fill = 'both') {
            return node.animate(keyframes, { delay: at * 1000, duration: Math.max(1, duration * 1000), fill, easing })
        }

        /** Runs `fn(t)` on every seek, for what CSS cannot animate (numbers, typed text). */
        tick (fn) {
            this.tickers.push(fn)
        }

        background () {
            const c = this.colors
            const glow = el('div', { position: 'absolute', inset: '-20%', background: `radial-gradient(40% 30% at 30% 15%, ${c.accent}2e, transparent 70%), radial-gradient(40% 30% at 75% 85%, ${c.accent2}24, transparent 70%)` }, this.root)
            this.root.prepend(glow)
            this.animate(glow, [{ transform: 'translate(-4%, -2%)' }, { transform: 'translate(4%, 3%)' }], 0, Math.max(1, this.duration), 'ease-in-out')
            if (c.grid) {
                const size = 72 * this.unit
                const grid = el('div', {
                    position: 'absolute',
                    inset: '0',
                    backgroundImage: `linear-gradient(${c.ink}0d 1px, transparent 1px), linear-gradient(90deg, ${c.ink}0d 1px, transparent 1px)`,
                    backgroundSize: `${size}px ${size}px`,
                    maskImage: 'radial-gradient(ellipse at center, black 20%, transparent 75%)'
                }, this.root)
                glow.after(grid)
                this.animate(grid, [{ backgroundPosition: '0 0' }, { backgroundPosition: `0 ${size * 2}px` }], 0, Math.max(1, this.duration), 'linear')
            }
        }

        seek (t) {
            const ms = Math.max(0, Math.min(t, this.duration)) * 1000
            for (const animation of this.root.getAnimations({ subtree: true })) {
                animation.currentTime = ms
            }
            for (const fn of this.tickers) {
                fn(t)
            }
        }

        /** What the renderer and the editor need to know about the finished video. */
        manifest () {
            // Voice lines and music play once their audio has been made and
            // handed to the page; until then they are only listed.
            const voiced = this.voices
                .filter(line => this.audio.voices?.[line.text])
                .map(line => ({ src: this.audio.voices[line.text], at: line.at, volume: line.volume }))
            const music = this.track && this.audio.music ? [{ src: this.audio.music, at: 0, volume: this.track.volume, music: true }] : []

            return {
                width: this.width,
                height: this.height,
                fps: this.fps,
                duration: this.duration,
                sounds: [...this.sounds, ...voiced, ...music],
                voices: this.voices.map(line => line.text),
                narrator: this.voiceName ?? null,
                music: this.track?.prompt ?? null
            }
        }
    }

    class Scene {
        constructor (video, start, duration, options) {
            this.video = video
            this.start = start
            this.duration = duration
            const v = video
            this.node = el('div', {
                position: 'absolute',
                inset: '0',
                display: 'flex',
                flexDirection: 'column',
                alignItems: options.align === 'left' ? 'flex-start' : 'center',
                justifyContent: options.justify ?? 'center',
                gap: `${40 * v.unit}px`,
                padding: `${110 * v.unit}px ${80 * v.unit}px`,
                boxSizing: 'border-box',
                // Its own stacking context: shapes sit behind its bricks, not behind the video.
                isolation: 'isolate',
                textAlign: options.align === 'left' ? 'left' : 'center'
            }, v.root)
            this.transition = ['blur', 'cut', ...COVERING].includes(options.transition) ? options.transition : 'blur'
            // A covering scene needs a field of its own to cover with.
            const field = options.background ?? (COVERING.includes(this.transition) ? 'background' : null)
            if (field) {
                this.paint(field, options.ink)
            }
            this.lines = []
            if (options.label) {
                this.label(options.label)
            }
        }

        /**
         * Once its bricks are in: places its voice lines one after the other,
         * each where it was asked for unless the previous one is still being
         * read, and lengthens the scene when they need it. The bricks are
         * untouched: a longer scene simply holds its last frame. Then the
         * scene's own entrance and exit.
         */
        finish () {
            const lengths = this.video.audio.durations ?? {}
            let end = 0
            for (const line of this.lines.sort((a, b) => a.at - b.at)) {
                const at = Math.max(line.at, end)
                end = at + (lengths[line.text] ?? 0) + 0.15
                this.video.voices.push({ text: line.text, at: this.at(at), volume: line.volume })
            }
            this.duration = Math.max(this.duration, end + 0.35)

            const v = this.video
            const length = Math.min(this.transition === 'blur' ? 0.3 : 0.5, this.duration / 4)
            const arrivals = {
                blur: [{ opacity: 0, transform: 'scale(1.08)', filter: 'blur(14px)' }, { opacity: 1, transform: 'scale(1)', filter: 'blur(0px)' }],
                cut: [{ opacity: 1 }, { opacity: 1 }],
                push: [{ transform: 'translateX(100%)' }, { transform: 'translateX(0)' }],
                rise: [{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }],
                wipe: [{ clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)' }],
                iris: [{ clipPath: 'circle(0% at 50% 50%)' }, { clipPath: 'circle(75% at 50% 50%)' }]
            }
            // Hidden until its turn, then in.
            v.animate(this.node, [{ visibility: 'hidden' }, { visibility: 'visible' }], this.start, 0.001, 'linear')
            v.animate(this.node, arrivals[this.transition], this.start, length, this.transition === 'blur' ? ease : easeInOut)
        }

        /**
         * Once the next scene is known: steps aside the way it arrives. A
         * covering one is let in over this scene, a push pushes it out, the
         * others fade it.
         */
        leave (next) {
            const v = this.video
            const end = this.start + this.duration
            if (next.transition === 'push') {
                v.animate(this.node, [{ transform: 'translateX(0)' }, { transform: 'translateX(-35%)', opacity: 0.4 }], end, 0.5, easeInOut, 'forwards')
            } else if (next.transition === 'cut') {
                v.animate(this.node, [{ opacity: 1 }, { opacity: 0 }], end, 0.001, 'linear', 'forwards')
                return
            } else if (!COVERING.includes(next.transition)) {
                v.animate(this.node, [{ opacity: 1, filter: 'blur(0px)', transform: 'scale(1)' }, { opacity: 0, filter: 'blur(10px)', transform: 'scale(.96)' }], end - 0.25, 0.25, 'ease-in', 'forwards')
                return
            }
            // Gone once fully covered, so its animations never show through.
            v.animate(this.node, [{ visibility: 'visible' }, { visibility: 'hidden' }], end + 0.55, 0.001, 'linear', 'forwards')
        }

        /**
         * Fills the scene with a colour (a theme name like 'accent' or 'ink',
         * or any CSS colour) and recolours its bricks to stay readable on it.
         */
        paint (field, ink) {
            const theme = this.video.colors
            const background = hex(theme[field] ?? field)
            const text = ink ? hex(theme[ink] ?? ink) : [theme.ink, theme.background, '#ffffff', '#111111'].map(hex).find(c => contrast(c, background) >= 4.5) ?? '#ffffff'
            const readable = color => contrast(color, background) >= 2.2 ? hex(color) : text
            const accent = readable(theme.accent)
            this.palette = { background, ink: text, muted: `${text}bf`, surface: `${text}14`, accent, accent2: accent === text ? text : readable(theme.accent2) }
            this.node.style.background = background
            this.node.style.color = text
            this.node.style.setProperty('--accent', this.palette.accent)
            this.node.style.setProperty('--accent2', this.palette.accent2)
        }

        get unit () {
            return this.video.unit
        }

        get colors () {
            return this.palette ? { ...this.video.colors, ...this.palette } : this.video.colors
        }

        /** Absolute time of a moment inside this scene. */
        at (t = 0) {
            return this.start + t
        }

        pop (node, at = 0, distance = 40) {
            this.video.animate(node, [
                { opacity: 0, transform: `translateY(${distance * this.unit}px) scale(.94)` },
                { opacity: 1, transform: 'translateY(0) scale(1)' }
            ], this.at(at), 0.55, 'cubic-bezier(.34,1.4,.64,1)')

            return node
        }

        /** A spoken line, `at` seconds into the scene. Read aloud when the instance has a voice. */
        voice (text, { at = 0.2, volume = 1 } = {}) {
            this.lines.push({ text: String(text), at: Math.max(0, at), volume })

            return this
        }

        /** A small uppercase line pinned to the top of the scene. */
        label (text) {
            const node = el('div', {
                position: 'absolute',
                top: `${70 * this.unit}px`,
                left: '0',
                right: '0',
                textAlign: 'center',
                fontFamily: 'JetBrains Mono, monospace',
                fontSize: `${26 * this.unit}px`,
                letterSpacing: '.14em',
                textTransform: 'uppercase',
                color: this.colors.accent
            }, this.node, text)

            return this.pop(node, 0.1, 10)
        }

        /** Words one at a time, huge, like a trailer. Each holds `every` seconds. */
        slam (words, { at = 0, every = 0.38, size = 190, sound = true } = {}) {
            const box = el('div', { position: 'absolute', inset: '0', display: 'flex', alignItems: 'center', justifyContent: 'center' }, this.node)
            words.forEach((word, i) => {
                const node = rich(el('div', {
                    position: 'absolute',
                    fontFamily: `${this.colors.display}, sans-serif`,
                    fontWeight: '700',
                    fontSize: `${size * this.unit}px`,
                    letterSpacing: '-.04em',
                    lineHeight: '1',
                    padding: `0 ${60 * this.unit}px`,
                    color: i % 2 ? this.colors.accent : this.colors.ink
                }, box), word)
                node.dataset.fit = this.video.width
                const start = this.at(at + i * every)
                const last = i === words.length - 1
                this.video.animate(node, [
                    { opacity: 0, transform: 'scale(1.35)' },
                    { offset: 0.12, opacity: 1, transform: 'scale(1.08)', easing: 'linear' },
                    { offset: last ? 1 : 0.94, opacity: 1, transform: 'scale(1)' },
                    { opacity: last ? 1 : 0, transform: 'scale(1)' }
                ], start, last ? this.duration - at - i * every : every, 'linear')
                if (sound) {
                    this.video.sound('pop', start, 0.35)
                }
            })

            return this
        }

        /** The headline. `**words**` take the accent gradient. Words arrive one after another. */
        title (text, { at = 0, size = 104 } = {}) {
            const node = el('h1', {
                margin: '0',
                fontFamily: `${this.colors.display}, sans-serif`,
                fontWeight: '650',
                fontSize: `${size * this.unit}px`,
                lineHeight: '1.06',
                letterSpacing: '-.035em',
                textWrap: 'balance'
            }, this.node)
            node.dataset.fit = this.video.width - 160 * this.unit
            let i = 0
            String(text)
                .split(/(\*\*[^*]+\*\*)/)
                .filter(Boolean)
                .forEach((part) => {
                    const strong = part.startsWith('**');
                    (strong ? part.slice(2, -2) : part).split(/(\s+)/).forEach((word) => {
                        if (/^\s+$/.test(word)) {
                            node.append(word)

                            return
                        }
                        const span = el('span', { display: 'inline-block' }, node, word)
                        if (strong) {
                            span.className = 'grad'
                        }
                        this.pop(span, at + i++ * 0.07, 30)
                    })
                })

            return this
        }

        /** A paragraph under the title. */
        text (text, { at = 0.3, size = 46 } = {}) {
            const node = rich(el('p', { margin: '0', fontSize: `${size * this.unit}px`, lineHeight: '1.4', color: this.colors.muted, maxWidth: `${880 * this.unit}px`, textWrap: 'pretty' }, this.node), text)

            return this.pop(node, at, 24) && this
        }

        card (style = {}) {
            return el('div', {
                background: `linear-gradient(180deg, ${this.colors.ink}10, ${this.colors.ink}05)`,
                border: `1px solid ${this.colors.ink}1f`,
                borderRadius: `${28 * this.unit}px`,
                boxShadow: `0 ${40 * this.unit}px ${110 * this.unit}px -${40 * this.unit}px ${this.colors.accent}55`,
                boxSizing: 'border-box',
                ...style
            }, this.node)
        }

        /** An input that types itself, then a button that gets clicked. */
        field (text, { at = 0.2, label = null, button = null, speed = 18, sound = true } = {}) {
            const u = this.unit
            const box = this.pop(this.card({ width: '100%', padding: `${32 * u}px`, textAlign: 'left' }), at)
            if (label) {
                el('div', { fontFamily: 'JetBrains Mono, monospace', fontSize: `${22 * u}px`, letterSpacing: '.08em', textTransform: 'uppercase', color: this.colors.muted, marginBottom: `${14 * u}px` }, box, label)
            }
            const input = el('div', { fontFamily: 'JetBrains Mono, monospace', fontSize: `${40 * u}px`, padding: `${24 * u}px ${26 * u}px`, borderRadius: `${16 * u}px`, background: this.colors.background, border: `2px solid ${this.colors.accent}`, minHeight: `${50 * u}px`, whiteSpace: 'pre-wrap' }, box)
            const typeStart = this.at(at + 0.4)
            const typeEnd = typeStart + text.length / speed
            this.video.tick((t) => {
                const count = Math.max(0, Math.min(text.length, Math.floor((t - typeStart) * speed)))
                input.textContent = text.slice(0, count) + (t > typeStart && t < typeEnd + 0.3 && Math.floor(t * 3) % 2 === 0 ? '▍' : '')
            })
            if (sound) {
                this.video.sound('typing', typeStart, 0.35)
            }
            if (button) {
                const press = typeEnd + 0.35
                const node = el('div', { marginTop: `${22 * u}px`, padding: `${24 * u}px`, borderRadius: `${16 * u}px`, background: this.colors.accent, color: this.colors.background, fontFamily: `${this.colors.display}, sans-serif`, fontWeight: '650', fontSize: `${38 * u}px`, textAlign: 'center' }, box, button)
                this.video.animate(node, [{ transform: 'scale(1)' }, { offset: 0.4, transform: 'scale(.93)' }, { transform: 'scale(1)' }], press, 0.3)
                if (sound) {
                    this.video.sound('click', press, 0.6)
                }
            }

            return this
        }

        /** Points that pop in one after the other, each with a check. */
        list (items, { at = 0.3, every = 0.35, sound = true } = {}) {
            const u = this.unit
            const box = el('div', { display: 'flex', flexDirection: 'column', gap: `${22 * u}px`, width: '100%', textAlign: 'left' }, this.node)
            items.forEach((item, i) => {
                const row = this.card({ display: 'flex', gap: `${24 * u}px`, alignItems: 'center', padding: `${28 * u}px ${32 * u}px`, fontSize: `${40 * u}px`, lineHeight: '1.3' })
                box.appendChild(row)
                el('span', { flex: 'none', width: `${52 * u}px`, height: `${52 * u}px`, borderRadius: '99px', background: `${this.colors.accent}26`, color: this.colors.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: `${30 * u}px` }, row, '✓')
                rich(el('span', {}, row), item)
                this.pop(row, at + i * every, 30)
                if (sound) {
                    this.video.sound('pop', this.at(at + i * every), 0.25)
                }
            })

            return this
        }

        /** Cards fanned out side by side or stacked: features, posts, quotes. */
        cards (items, { at = 0.3, every = 0.18, sound = true } = {}) {
            const u = this.unit
            const box = el('div', { display: 'flex', flexDirection: this.video.width > this.video.height ? 'row' : 'column', gap: `${26 * u}px`, width: '100%' }, this.node)
            items.forEach((item, i) => {
                const node = this.card({ flex: '1', padding: `${34 * u}px ${36 * u}px`, textAlign: 'left' })
                box.appendChild(node)
                if (item.kicker) {
                    el('div', { fontFamily: 'JetBrains Mono, monospace', fontSize: `${22 * u}px`, letterSpacing: '.08em', textTransform: 'uppercase', color: this.colors.accent, marginBottom: `${12 * u}px` }, node, item.kicker)
                }
                if (item.title) {
                    rich(el('div', { fontFamily: `${this.colors.display}, sans-serif`, fontWeight: '650', fontSize: `${42 * u}px`, lineHeight: '1.15', marginBottom: item.text ? `${10 * u}px` : '0' }, node), item.title)
                }
                if (item.text) {
                    rich(el('div', { fontSize: `${32 * u}px`, lineHeight: '1.4', color: this.colors.muted }, node), item.text)
                }
                const tilt = (i - (items.length - 1) / 2) * 2.5
                this.video.animate(node, [
                    { opacity: 0, transform: `translateY(${120 * u}px) rotate(${tilt * 3}deg) scale(.9)` },
                    { opacity: 1, transform: `translateY(0) rotate(${tilt}deg) scale(1)` }
                ], this.at(at + i * every), 0.7, 'cubic-bezier(.34,1.3,.64,1)')
                if (sound) {
                    this.video.sound('pop', this.at(at + i * every), 0.25)
                }
            })

            return this
        }

        /** A number that counts up to `to`, with a label under it. */
        counter ({ to, from = 0, prefix = '', suffix = '', label = '', at = 0.2, duration = 1.4, decimals = 0 } = {}) {
            const u = this.unit
            const box = this.pop(el('div', {}, this.node), at)
            // Shrinks with what it has to fit, so a long suffix never wraps.
            const longest = `${prefix}${to.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}${suffix}`.length
            const number = el('div', { fontFamily: `${this.colors.display}, sans-serif`, fontWeight: '700', fontSize: `${200 * u * Math.min(1, 6 / longest)}px`, letterSpacing: '-.05em', lineHeight: '1', whiteSpace: 'nowrap' }, box)
            number.className = 'grad'
            if (label) {
                rich(el('div', { fontSize: `${44 * u}px`, color: this.colors.muted, marginTop: `${16 * u}px` }, box), label)
            }
            const start = this.at(at)
            this.video.tick((t) => {
                const p = Math.max(0, Math.min(1, (t - start) / duration))
                const value = from + (to - from) * (1 - (1 - p) ** 3)
                number.textContent = `${prefix}${value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}${suffix}`
            })

            return this
        }

        /** An uploaded image, slowly drifting. */
        image (asset, { at = 0, height = 900, radius = 28 } = {}) {
            const u = this.unit
            const node = el('img', { height: `${height * u}px`, maxWidth: '100%', objectFit: 'cover', borderRadius: `${radius * u}px`, boxShadow: `0 ${40 * u}px ${120 * u}px -${40 * u}px ${this.colors.accent}66` }, this.node)
            node.src = this.video.assets[asset]?.url ?? ''
            this.pop(node, at)

            return this
        }

        /**
         * A screenshot filmed by a camera. Each shot frames `view` (x, y, w, h
         * as fractions of the image) from its `at`, and can spotlight `spot`,
         * move the cursor to `cursor` (x, y fractions) and `click`, and show a
         * `caption`. Best alone in its scene.
         */
        screen (asset, { shots = [], move = 0.7, sound = true } = {}) {
            const v = this.video
            const u = this.unit
            const image = v.assets[asset]
            if (!image || !shots.length) {
                return this
            }
            const iw = image.width
            const ih = image.height
            const stage = { width: v.width, height: v.height * 0.74, cx: v.width / 2, cy: v.height * 0.42 }
            const cameraFor = ({ view = [0, 0, 1, 1] }) => {
                const [x, y, w, h] = view
                const scale = Math.min(3, (stage.width * 0.92) / (w * iw), (stage.height * 0.9) / (h * ih))

                return { scale, tx: stage.cx - (x + w / 2) * iw * scale, ty: stage.cy - (y + h / 2) * ih * scale }
            }
            const toScreen = (camera, fx, fy) => [camera.tx + fx * iw * camera.scale, camera.ty + fy * ih * camera.scale]

            const holder = el('div', { position: 'absolute', inset: '0', overflow: 'hidden' }, this.node)
            const img = el('img', {
                position: 'absolute',
                left: '0',
                top: '0',
                width: `${iw}px`,
                height: `${ih}px`,
                transformOrigin: '0 0',
                borderRadius: `${14 / u}px`,
                boxShadow: `0 0 0 2px ${this.colors.ink}22, 0 60px 160px -40px ${this.colors.accent}66`
            }, holder)
            img.src = image.url

            const sorted = [...shots].sort((a, b) => (a.at ?? 0) - (b.at ?? 0))
            const cameras = sorted.map(cameraFor)
            const total = this.duration
            const frames = []
            sorted.forEach((shot, i) => {
                const t = Math.max(0, shot.at ?? 0)
                const camera = cameras[i]
                const transform = `translate(${camera.tx}px, ${camera.ty}px) scale(${camera.scale})`
                if (i === 0) {
                    frames.push({ offset: 0, transform, easing: easeInOut })
                } else {
                    frames.push({ offset: Math.min(1, t / total), transform: frames[frames.length - 1].transform, easing: easeInOut })
                    frames.push({ offset: Math.min(1, (t + move) / total), transform, easing: easeInOut })
                }
            })
            frames.push({ offset: 1, transform: frames[frames.length - 1].transform })
            v.animate(img, frames, this.start, total, 'linear')

            const shade = el('div', { position: 'absolute', inset: '0', background: `linear-gradient(transparent 62%, ${this.colors.background}f5 80%)` }, holder)
            shade.style.pointerEvents = 'none'

            sorted.forEach((shot, i) => {
                const begin = (i ? (shot.at ?? 0) + move : 0) + 0.1
                const end = sorted[i + 1]?.at ?? total
                const length = Math.max(0.2, end - begin)
                const camera = cameras[i]
                const fade = node => v.animate(node, [
                    { opacity: 0 },
                    { offset: Math.min(0.5, 0.25 / length), opacity: 1 },
                    { offset: Math.max(0.5, 1 - 0.15 / length), opacity: 1 },
                    { opacity: 0 }
                ], this.at(begin), length, 'linear')
                if (shot.spot) {
                    const [sx, sy] = toScreen(camera, shot.spot[0], shot.spot[1])
                    const pad = 12 * u
                    fade(el('div', {
                        position: 'absolute',
                        left: `${sx - pad}px`,
                        top: `${sy - pad}px`,
                        width: `${shot.spot[2] * iw * camera.scale + pad * 2}px`,
                        height: `${shot.spot[3] * ih * camera.scale + pad * 2}px`,
                        borderRadius: `${18 * u}px`,
                        border: `${4 * u}px solid ${this.colors.accent}`,
                        boxShadow: `0 0 0 9999px rgba(4,7,11,.6), 0 0 ${50 * u}px ${this.colors.accent}88`
                    }, holder))
                }
                if (shot.caption) {
                    const caption = rich(el('div', {
                        position: 'absolute',
                        left: `${70 * u}px`,
                        right: `${70 * u}px`,
                        bottom: `${v.height * 0.07}px`,
                        textAlign: 'center',
                        fontFamily: `${this.colors.display}, sans-serif`,
                        fontWeight: '650',
                        fontSize: `${(v.width > v.height ? 60 : 72) * u}px`,
                        lineHeight: '1.1',
                        letterSpacing: '-.03em',
                        textWrap: 'balance'
                    }, holder), shot.caption)
                    fade(caption)
                }
            })

            const clicks = sorted.map((shot, i) => ({ shot, camera: cameras[i] })).filter(({ shot }) => shot.cursor)
            if (clicks.length) {
                const cursor = el('div', { position: 'absolute', left: '0', top: '0' }, holder)
                cursor.innerHTML = `<svg width="${56 * u}" height="${56 * u}" viewBox="0 0 24 24" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,.6))"><path d="M3 2l7 19 2.6-8.1L21 10z" fill="#fff" stroke="#0b1018" stroke-width="1.4" stroke-linejoin="round"/></svg>`
                const path = []
                clicks.forEach(({ shot, camera }, i) => {
                    const [x, y] = toScreen(camera, shot.cursor[0], shot.cursor[1])
                    const arrive = (shot.at ?? 0) + move + 0.35
                    if (i === 0) {
                        path.push({ offset: 0, transform: `translate(${x + 260 * u}px, ${y + 300 * u}px)`, opacity: 0 })
                        path.push({ offset: Math.max(0, (arrive - 0.6) / total), transform: `translate(${x + 260 * u}px, ${y + 300 * u}px)`, opacity: 0, easing: ease })
                    } else {
                        path.push({ offset: Math.max(path[path.length - 1].offset, (arrive - 0.6) / total), transform: path[path.length - 1].transform, opacity: 1, easing: ease })
                    }
                    path.push({ offset: Math.min(1, arrive / total), transform: `translate(${x}px, ${y}px)`, opacity: 1 })
                    if (shot.click) {
                        const ripple = el('div', { position: 'absolute', left: `${x - 45 * u}px`, top: `${y - 45 * u}px`, width: `${90 * u}px`, height: `${90 * u}px`, borderRadius: '99px', border: `${4 * u}px solid ${this.colors.accent}` }, holder)
                        v.animate(ripple, [{ opacity: 0, transform: 'scale(.3)' }, { offset: 0.01, opacity: 1, transform: 'scale(.3)' }, { opacity: 0, transform: 'scale(1.3)' }], this.at(arrive + 0.15), 0.5)
                        if (sound) {
                            v.sound('click', this.at(arrive + 0.15), 0.6)
                        }
                    }
                })
                path.push({ offset: 1, transform: path[path.length - 1].transform, opacity: 1 })
                v.animate(cursor, path, this.start, total, 'linear')
            }

            return this
        }

        /**
         * A flat shape behind the bricks: 'circle', 'ring', 'square', 'blob'
         * or 'band' (a stripe across the whole width). x and y place its
         * centre as fractions of the frame, size is in px at 1080 wide.
         */
        shape (kind, { x = 0.5, y = 0.5, size = 520, color = 'accent', at = 0, spin = 0, drift = 0, opacity = 1 } = {}) {
            const v = this.video
            const u = this.unit
            // Decoration keeps the brand's own colours, readable or not.
            const fill = hex(v.colors[color] ?? color)
            const box = size * u
            const band = kind === 'band'
            const node = el('div', {
                position: 'absolute',
                zIndex: '-1',
                left: band ? '-10%' : `${x * v.width - box / 2}px`,
                top: `${y * v.height - (band ? box / 4 : box / 2)}px`,
                width: band ? '120%' : `${box}px`,
                height: band ? `${box / 2}px` : `${box}px`,
                borderRadius: { circle: '50%', ring: '50%', blob: '42% 58% 63% 37% / 41% 44% 56% 59%', square: `${box * 0.12}px` }[kind] ?? '0',
                background: kind === 'ring' ? 'transparent' : fill,
                border: kind === 'ring' ? `${Math.max(6, box * 0.07)}px solid ${fill}` : 'none',
                boxSizing: 'border-box',
                opacity: String(opacity)
            }, this.node)
            v.animate(node, [{ transform: band ? 'scaleX(0)' : 'scale(0) rotate(-20deg)' }, { transform: band ? 'scaleX(1)' : 'scale(1) rotate(0deg)' }], this.at(at), 0.6, 'cubic-bezier(.34,1.3,.64,1)', 'backwards')
            if (spin || drift) {
                // Keeps moving for the rest of the scene, so a still frame never looks frozen.
                const rest = Math.max(0.1, this.duration - at - 0.6)
                node.animate([{ translate: '0 0', rotate: '0deg' }, { translate: `0 ${-drift * u}px`, rotate: `${spin}deg` }], { delay: this.at(at + 0.6) * 1000, duration: rest * 1000, fill: 'both', easing: 'linear' })
            }

            return this
        }

        /**
         * A line of huge type running across the frame, repeated, at y (a
         * fraction of the height). Behind the bricks unless `front`.
         */
        marquee (text, { y = 0.5, size = 220, color = 'ink', speed = 160, reverse = false, outline = false, opacity = 1, front = false } = {}) {
            const v = this.video
            const u = this.unit
            const ink = hex(this.colors[color] ?? color)
            const node = el('div', {
                position: 'absolute',
                zIndex: front ? '1' : '-1',
                left: '0',
                top: `${y * v.height - size * u * 0.6}px`,
                whiteSpace: 'nowrap',
                fontFamily: `${this.colors.display}, sans-serif`,
                fontWeight: '800',
                fontSize: `${size * u}px`,
                lineHeight: '1.2',
                letterSpacing: '-.03em',
                color: outline ? 'transparent' : ink,
                webkitTextStroke: outline ? `${3 * u}px ${ink}` : '0',
                opacity: String(opacity)
            }, this.node, `${text}  ·  `.repeat(8))
            const travel = speed * u * this.duration
            v.animate(node, [{ transform: `translateX(${reverse ? -travel : 0}px)` }, { transform: `translateX(${reverse ? 0 : -travel}px)` }], this.start, this.duration, 'linear')

            return this
        }

        /** The closing card: logo, a line, and where to go. */
        outro ({ tagline = '', cta = '', at = 0.1, sound = true } = {}) {
            const u = this.unit
            const logo = this.video.assets.logo
            if (logo) {
                const img = this.pop(el('img', { height: `${170 * u}px`, maxWidth: `${700 * u}px`, objectFit: 'contain', borderRadius: `${28 * u}px` }, this.node), at)
                img.src = logo.url
            }
            if (tagline) {
                this.title(tagline, { at: at + 0.35, size: 84 })
            }
            if (cta) {
                this.pop(el('div', { padding: `${24 * u}px ${48 * u}px`, borderRadius: `${18 * u}px`, background: this.colors.accent, color: this.colors.background, fontFamily: `${this.colors.display}, sans-serif`, fontWeight: '650', fontSize: `${44 * u}px` }, this.node, cta), at + 0.8)
            }
            if (sound) {
                this.video.sound('reveal', this.at(at), 0.5)
            }

            return this
        }
    }

    /**
     * Builds the composition once its images are decoded, then exposes the
     * result as `window.video` for the renderer and the editor.
     */
    window.Video = {
        async compose (build, options = window.VIDEO_OPTIONS ?? {}) {
            const assets = {}
            await Promise.all(Object.entries(options.assets ?? {}).map(async ([name, url]) => {
                const img = new Image()
                img.src = url
                try {
                    await img.decode()
                    assets[name] = { url, width: img.naturalWidth, height: img.naturalHeight }
                } catch {
                    // A missing image leaves its brick empty rather than failing the video.
                }
            }))
            const video = new Video({ ...options, assets })
            build(video)
            // Only the fonts the theme picked, before the first frame is taken.
            await Promise.all([video.colors.display, video.colors.body, 'JetBrains Mono'].map(font => document.fonts.load(`650 40px "${font}"`)))
            await document.fonts.ready
            // A word too long for the frame in a wide font shrinks until it fits.
            for (const node of video.root.querySelectorAll('[data-fit]')) {
                for (let i = 0; i < 12 && Math.max(node.scrollWidth, node.clientWidth) > Number(node.dataset.fit); i++) {
                    node.style.fontSize = `${parseFloat(node.style.fontSize) * 0.9}px`
                }
            }
            video.background()
            for (const animation of video.root.getAnimations({ subtree: true })) {
                animation.pause()
            }
            video.seek(0)
            window.video = video
            window.dispatchEvent(new CustomEvent('video:ready', { detail: video.manifest() }))
            if (window.parent !== window) {
                player(video)
            }

            return video
        }
    }

    /**
     * The editor's side: the page sits in a sandboxed frame, so the editor
     * drives it by message (`play`, `pause`, `seek`) and hears back the
     * manifest and the time as it plays. Sounds play here too, from the
     * cues, which the renderer instead mixes with ffmpeg.
     */
    function player (video) {
        const post = message => window.parent.postMessage({ source: 'eveil-video', ...message }, '*')
        const fit = () => {
            const scale = Math.min(window.innerWidth / video.width, window.innerHeight / video.height)
            Object.assign(video.root.style, { transformOrigin: '0 0', transform: `translate(${(window.innerWidth - video.width * scale) / 2}px, ${(window.innerHeight - video.height * scale) / 2}px) scale(${scale})` })
        }
        fit()
        window.addEventListener('resize', fit)

        let time = 0
        let playing = null
        const audio = []
        const stopAudio = () => audio.splice(0).forEach(sound => sound.pause())
        const pause = () => {
            playing = null
            stopAudio()
            post({ type: 'pause', time })
        }
        const play = () => {
            if (time >= video.duration - 0.05) {
                time = 0
            }
            const startedAt = performance.now() - time * 1000
            const from = time
            const token = {}
            playing = token
            // The music and a voice line already under way pick up where the
            // video is; a line that has ended simply has nothing left to play.
            video.manifest().sounds.filter(cue => cue.src || cue.at >= from).forEach((cue) => {
                const sound = new Audio(cue.src ?? `sounds/${cue.name}.mp3`)
                sound.volume = Math.min(1, cue.volume)
                audio.push(sound)
                if (cue.at < from) {
                    sound.currentTime = from - cue.at
                    sound.play().catch(() => {})

                    return
                }
                setTimeout(() => playing === token && sound.play().catch(() => {}), (cue.at - from) * 1000)
            })
            const frame = () => {
                if (playing !== token) {
                    return
                }
                time = Math.min(video.duration, (performance.now() - startedAt) / 1000)
                video.seek(time)
                post({ type: 'time', time })
                if (time >= video.duration) {
                    pause()

                    return
                }
                requestAnimationFrame(frame)
            }
            post({ type: 'play', time })
            requestAnimationFrame(frame)
        }

        window.addEventListener('message', (event) => {
            const message = event.data ?? {}
            if (message.type === 'play') {
                play()
            } else if (message.type === 'pause') {
                pause()
            } else if (message.type === 'seek') {
                const resume = playing !== null
                pause()
                time = Math.max(0, Math.min(video.duration, Number(message.time) || 0))
                video.seek(time)
                post({ type: 'time', time })
                if (resume) {
                    play()
                }
            }
        })
        window.addEventListener('error', event => post({ type: 'error', message: String(event.message) }))
        post({ type: 'ready', manifest: video.manifest() })
    }
})()
