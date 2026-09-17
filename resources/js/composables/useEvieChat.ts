import type { UIMessage } from 'ai'
import { useChat } from '@ai-sdk/vue'
import { DefaultChatTransport, isToolUIPart } from 'ai'
import { usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import chatRoutes from '@/routes/chat'

/**
 * Laravel's own CSRF cookie: this endpoint is a plain fetch, not an Inertia
 * visit, so nothing sets this header automatically the way Inertia's router
 * does for every other write in the app.
 */
function xsrfToken (): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)

    return match ? decodeURIComponent(match[1]) : ''
}

function toUiMessage (message: { role: string, content: string }, index: number): UIMessage {
    return {
        id: `history-${index}`,
        role: message.role as UIMessage['role'],
        parts: [{ type: 'text', text: message.content }]
    }
}

/**
 * One conversation with Evie per project - the backend already owns all of
 * the continuity (`EvieChatController` always continues the project's latest
 * conversation; "clear" makes a fresh, empty one the latest), so the client
 * only ever tracks the messages it is currently showing, never a
 * conversation id.
 */
export function useEvieChat () {
    const page = usePage()

    const transport = new DefaultChatTransport({
        api: chatRoutes.store.url({ project: page.props.currentProject!.slug }),
        headers: () => ({ 'X-XSRF-TOKEN': xsrfToken() }),
        prepareSendMessagesRequest ({ messages, body }) {
            // A resume (Approve/Deny) calls regenerate({ body: { decisions } })
            // with no new user message: pass that straight through instead of
            // extracting a message from a transcript that did not change.
            if (body && 'decisions' in body) {
                return { body }
            }

            const last = messages.at(-1)
            const text = last?.parts.filter(part => part.type === 'text').map(part => part.text).join('') ?? ''

            return { body: { message: text } }
        }
    })

    const chat = useChat({ transport })

    const loadingHistory = ref(false)

    async function loadHistory () {
        loadingHistory.value = true

        try {
            const response = await fetch(chatRoutes.show.url({ project: page.props.currentProject!.slug }), {
                headers: { Accept: 'application/json' }
            })

            const data: { messages: Array<{ role: string, content: string }> } = await response.json()

            chat.messages.value = data.messages.map(toUiMessage)
        } finally {
            loadingHistory.value = false
        }
    }

    // Clears the local transcript right away, and tells the backend to make
    // a fresh, empty conversation the project's latest - not deferred to the
    // next message, so a reload before then shows nothing rather than
    // whatever the previous conversation still holds.
    function clear () {
        chat.messages.value = []
        pendingDecisions.value = {}

        void fetch(chatRoutes.destroy.url({ project: page.props.currentProject!.slug }), {
            method: 'DELETE',
            headers: { 'X-XSRF-TOKEN': xsrfToken() }
        })
    }

    // The backend validates a resume against EVERY pending approval-gated
    // tool call in the turn at once (`TextGenerationLoop::validateApproval`):
    // a decision missing for even one of them throws
    // `ApprovalMismatchException` and fails the whole run, undoing nothing
    // (the mismatch is caught before any tool executes) but breaking the
    // chat's local state. A turn proposing several approvable actions -
    // e.g. deleting five target profiles - renders one card per tool call,
    // so a single click here must not resume by itself. Decisions accumulate
    // locally and only resume once every pending card in the turn has one.
    const pendingDecisions = ref<Record<string, boolean>>({})

    function pendingApprovalToolCallIds (): string[] {
        const last = chat.messages.value.at(-1)

        if (!last) {
            return []
        }

        return last.parts
            .filter(part => isToolUIPart(part) && part.state === 'approval-requested')
            .map(part => (part as { toolCallId: string }).toolCallId)
    }

    // regenerate() is built around replaying a full turn, and its own
    // bookkeeping trims local messages in a way that does not match this
    // app's approval protocol (a resume with no new user message, against a
    // conversation the server already fully owns). Rather than fight that
    // local optimistic state, treat the server's own record as the only
    // truth once the resume settles.
    async function approve (toolCallId: string, approved: boolean) {
        pendingDecisions.value = { ...pendingDecisions.value, [toolCallId]: approved }

        const stillWaiting = pendingApprovalToolCallIds()
            .some(id => !(id in pendingDecisions.value))

        if (stillWaiting) {
            return
        }

        const decisions = pendingDecisions.value
        pendingDecisions.value = {}

        await chat.regenerate({ body: { decisions } })
        await loadHistory()
    }

    // The panel is mounted once, inside the persistent AppLayout, and stays
    // mounted across every project switch: reload whichever project's
    // conversation is now current rather than keep showing the last one's.
    watch(() => page.props.currentProject?.id, () => {
        pendingDecisions.value = {}
        void loadHistory()
    })

    void loadHistory()

    return { ...chat, loadingHistory, clear, approve, pendingDecisions }
}
