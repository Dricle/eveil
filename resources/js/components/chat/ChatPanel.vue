<script setup lang="ts">
import { isTextUIPart, isToolUIPart, getToolName } from 'ai'
import DOMPurify from 'dompurify'
import { marked } from 'marked'
import { computed, ref, watch } from 'vue'
import ChatJobChip from '@/components/chat/ChatJobChip.vue'
import { pendingMessage } from '@/composables/useChatPanel'
import { useEvieChat } from '@/composables/useEvieChat'

defineProps<{ open: boolean }>()

const input = ref('')

const { messages, status, error, loadingHistory, sendMessage, regenerate, stop, clear, approve, pendingDecisions } = useEvieChat()

// A trigger sent from outside the panel (the Dashboard's "Discuss with
// Evie" button, via `openEvieChat()`): dispatched here rather than at the
// call site because this is the one place that owns `sendMessage`.
watch(pendingMessage, (text) => {
    if (!text) {
        return
    }

    sendMessage({ text })
    pendingMessage.value = null
})

// useChat() mutates its messages array in place (push/index-assign) rather
// than replacing it, so the array's own reference never changes across a
// turn. UChatMessages receives `messages` as a prop, and Vue's prop-diffing
// skips re-rendering a child when a prop's reference is unchanged - so the
// panel would only repaint on the rare status transitions (submitted ->
// streaming -> ready) instead of per token. Spreading into a fresh array on
// every read forces a real reference change Vue's diffing can see.
const liveMessages = computed(() => [...messages.value])

// Only the LAST message's own suggestions, never an older turn's: the
// moment the user sends anything - a suggestion or their own text - that
// message becomes the last one instead, and these disappear immediately
// rather than lingering stale above the prompt.
const suggestedReplies = computed((): string[] => {
    const last = messages.value.at(-1)

    if (last?.role !== 'assistant') {
        return []
    }

    const part = last.parts.find(messagePart => isToolUIPart(messagePart)
        && getToolName(messagePart) === 'ProposeSuggestedReplies'
        && messagePart.state !== 'input-streaming')

    return (part as { input?: { replies?: string[] } } | undefined)?.input?.replies ?? []
})

function onSubmit () {
    if (!input.value.trim()) {
        return
    }

    sendMessage({ text: input.value })
    input.value = ''
}

function sendSuggestion (text: string) {
    sendMessage({ text })
}

// The only tools that ever reach `approval-requested` and start with
// "Delete" (DeleteTargetProfile, DeleteAllCompanies, DeleteAllLeads):
// DeleteCompanyNote/DeleteLeadNote are plain CRUD, never gated - see
// `app/Ai/Agents/Evie.php`. Cheap enough to name-match rather than carry a
// second "is destructive" flag through the wire for three tools.
function isDestructiveTool (name: string): boolean {
    return name.startsWith('Delete')
}

// `reason` is the exact string the tool itself passed to
// `requireApproval('...')` server-side (`Laravel\Ai`'s Vercel protocol
// bridge maps it to `approval.requestReason` on the client) - shown as-is
// rather than a generic "this cannot be undone" line so each tool explains
// its own blast radius.
function approvalReason (part: unknown): string | undefined {
    return (part as { approval?: { requestReason?: string } }).approval?.requestReason
}

marked.setOptions({ breaks: true, gfm: true })

function renderMarkdown (text: string): string {
    return DOMPurify.sanitize(marked.parse(text, { async: false }))
}
</script>

<template>
    <div
        class="flex h-full flex-col overflow-hidden border-l border-default bg-default transition-[width] duration-200"
        :class="open ? 'w-96' : 'w-0'"
    >
        <div class="flex w-96 min-w-96 flex-1 flex-col overflow-hidden text-sm">
            <div class="flex h-[52px] shrink-0 items-center gap-2 border-b border-default px-4">
                <UIcon
                    name="i-lucide-sparkles"
                    class="size-4 text-primary"
                />
                <span class="text-[13.5px] font-medium text-highlighted">Evie</span>

                <UButton
                    label="Clear"
                    icon="i-lucide-rotate-ccw"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    class="ml-auto"
                    :disabled="messages.length === 0"
                    @click="clear"
                />
            </div>

            <ChatJobChip />

            <UChatMessages
                :messages="liveMessages"
                :status="status"
                should-auto-scroll
                class="min-h-0 flex-1 overflow-y-auto"
                :ui="{ viewport: 'p-4' }"
            >
                <template #content="{ message }">
                    <template
                        v-for="(part, index) in message.parts"
                        :key="`${message.id}-${index}`"
                    >
                        <div
                            v-if="isTextUIPart(part)"
                            class="prose prose-sm dark:prose-invert max-w-none"
                            v-html="renderMarkdown(part.text)"
                        />

                        <!-- The one tool with no side effect: its arguments ARE
                             the suggestions. Rendered as buttons above the
                             prompt (`suggestedReplies`), not here in the
                             transcript - this branch only keeps it from
                             falling through to the generic UChatTool card
                             below, which would show it as a raw tool call. -->
                        <template v-else-if="isToolUIPart(part) && getToolName(part) === 'ProposeSuggestedReplies'" />

                        <UChatTool
                            v-else-if="isToolUIPart(part)"
                            :text="getToolName(part)"
                            variant="card"
                            :streaming="part.state === 'input-streaming'"
                            :ui="part.state === 'approval-requested' && isDestructiveTool(getToolName(part))
                                ? { root: 'border-error/60 bg-error/5' }
                                : undefined"
                            :suffix="part.state === 'approval-requested' && pendingDecisions[part.toolCallId] !== undefined
                                ? (pendingDecisions[part.toolCallId] ? 'Approved — waiting on the rest' : 'Denied — waiting on the rest')
                                : undefined"
                            :actions="part.state === 'approval-requested' && pendingDecisions[part.toolCallId] === undefined ? [
                                isDestructiveTool(getToolName(part))
                                    ? { label: 'Approve', size: 'xs', color: 'error', variant: 'solid', icon: 'i-lucide-triangle-alert', onClick: () => approve(part.toolCallId, true) }
                                    : { label: 'Approve', size: 'xs', onClick: () => approve(part.toolCallId, true) },
                                { label: 'Deny', size: 'xs', color: 'neutral', variant: 'soft', onClick: () => approve(part.toolCallId, false) }
                            ] : undefined"
                        >
                            <!-- `v-if` on the slot's own `<template>`, not on
                                 the element inside it: UChatTool only shows its
                                 expand chevron and body region when a default
                                 slot was actually PASSED (`!!slots.default`),
                                 not when it was passed-but-empty. Nesting the
                                 `v-if` one level in would give every tool call
                                 card - not just a destructive approval with a
                                 reason - a chevron toggling an empty body. -->
                            <template
                                v-if="part.state === 'approval-requested' && isDestructiveTool(getToolName(part)) && approvalReason(part)"
                                #default
                            >
                                <p class="flex items-start gap-1.5 text-sm text-error">
                                    <UIcon
                                        name="i-lucide-triangle-alert"
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    {{ approvalReason(part) }}
                                </p>
                            </template>
                        </UChatTool>
                    </template>
                </template>
            </UChatMessages>

            <div
                v-if="suggestedReplies.length > 0"
                class="flex flex-wrap gap-1.5 border-t border-default px-4 pt-3"
            >
                <UButton
                    v-for="reply in suggestedReplies"
                    :key="reply"
                    :label="reply"
                    color="neutral"
                    variant="outline"
                    size="xs"
                    @click="sendSuggestion(reply)"
                />
            </div>

            <div class="p-4">
                <UChatPrompt
                    v-model="input"
                    :error="error"
                    :disabled="loadingHistory"
                    variant="soft"
                    placeholder="Ask Evie to find, qualify or write something..."
                    @submit="onSubmit"
                >
                    <UChatPromptSubmit
                        :status="status"
                        @stop="stop()"
                        @reload="regenerate()"
                    />
                </UChatPrompt>
            </div>
        </div>
    </div>
</template>
