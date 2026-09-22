import { ref } from 'vue'

/**
 * The Evie chat panel's open state and a one-shot outgoing message, shared
 * across every page - not just `AppLayout.vue`/`ChatPanel.vue`, which used to
 * own `chatOpen` as a purely local ref. A page like the Dashboard needs to
 * open the panel and start a turn from a button click without reaching into
 * `ChatPanel`'s own `useEvieChat()` instance, so both live in a module-scoped
 * singleton instead: every importer shares the same refs.
 */
export const chatOpen = ref(false)

export const pendingMessage = ref<string | null>(null)

export function openEvieChat (message?: string) {
    chatOpen.value = true

    if (message) {
        pendingMessage.value = message
    }
}
