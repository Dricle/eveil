/**
 * Email Routing worker for the deliverability tester on the marketing site.
 *
 * Catch-all on the test domain. Anything not shaped like a test address is
 * rejected here, at the SMTP stage, so spam sent to the domain never reaches
 * the app. The rest is posted to the app raw, signed with a secret shared
 * with it (MAIL_TESTER_SECRET on both sides). The app answers 404 for an
 * address that is unknown, expired or already used, and that becomes a
 * rejection too: nothing is stored, forwarded or replied to.
 */
const TEST_ADDRESS = /^t-[a-z0-9]{16}@/
const MAX_BYTES = 2 * 1024 * 1024

export default {
    async email (message, env) {
        const to = message.to.toLowerCase()

        if (!TEST_ADDRESS.test(to)) {
            message.setReject('Unknown address')

            return
        }

        if (message.rawSize > MAX_BYTES) {
            message.setReject('Message too large for a test')

            return
        }

        const raw = new Uint8Array(await new Response(message.raw).arrayBuffer())
        const prefix = new TextEncoder().encode(to + '\n')
        const signed = new Uint8Array(prefix.length + raw.length)

        signed.set(prefix)
        signed.set(raw, prefix.length)

        const key = await crypto.subtle.importKey(
            'raw',
            new TextEncoder().encode(env.MAIL_TESTER_SECRET),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        )
        const signature = [...new Uint8Array(await crypto.subtle.sign('HMAC', key, signed))]
            .map(byte => byte.toString(16).padStart(2, '0'))
            .join('')

        const response = await fetch(env.INBOUND_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'message/rfc822',
                'X-Envelope-To': to,
                'X-Signature': signature
            },
            body: raw
        })

        if (!response.ok) {
            message.setReject('Unknown address')
        }
    }
}
