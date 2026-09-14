<script setup lang="ts">
import { isTextUIPart, isToolUIPart, getToolName } from 'ai'
import DOMPurify from 'dompurify'
import { marked } from 'marked'
import { computed, ref } from 'vue'
import ChatJobChip from '@/components/chat/ChatJobChip.vue'
import { useEvieChat } from '@/composables/useEvieChat'

defineProps<{ open: boolean }>()

const input = ref('')

const { messages, status, error, loadingHistory, sendMessage, regenerate, stop, clear, approve } = useEvieChat()

// useChat() mutates its messages array in place (push/index-assign) rather
// than replacing it, so the array's own reference never changes across a
// turn. UChatMessages receives `messages` as a prop, and Vue's prop-diffing
// skips re-rendering a child when a prop's reference is unchanged - so the
// panel would only repaint on the rare status transitions (submitted ->
// streaming -> ready) instead of per token. Spreading into a fresh array on
// every read forces a real reference change Vue's diffing can see.
const liveMessages = computed(() => [...messages.value])

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
                             the suggestions, rendered as buttons instead of the
                             usual collapsible tool card. It has no approval
                             gate, so it runs through to `output-available`
                             almost immediately - input alone is enough to
                             render, and never falls through to UChatTool
                             below once the output lands too. -->
                        <div
                            v-else-if="isToolUIPart(part) && getToolName(part) === 'ProposeSuggestedReplies' && part.state !== 'input-streaming'"
                            class="flex flex-wrap gap-1.5"
                        >
                            <UButton
                                v-for="reply in (part.input as { replies?: string[] })?.replies ?? []"
                                :key="reply"
                                :label="reply"
                                color="neutral"
                                variant="outline"
                                size="xs"
                                @click="sendSuggestion(reply)"
                            />
                        </div>

                        <UChatTool
                            v-else-if="isToolUIPart(part)"
                            :text="getToolName(part)"
                            variant="card"
                            :streaming="part.state === 'input-streaming'"
                            :actions="part.state === 'approval-requested' ? [
                                { label: 'Approve', size: 'xs', onClick: () => approve(part.toolCallId, true) },
                                { label: 'Deny', size: 'xs', color: 'neutral', variant: 'soft', onClick: () => approve(part.toolCallId, false) }
                            ] : undefined"
                        />
                    </template>
                </template>
            </UChatMessages>

            <div class="p-4">
                <UChatPrompt
                    v-model="input"
                    :error="error"
                    :disabled="loadingHistory"
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
