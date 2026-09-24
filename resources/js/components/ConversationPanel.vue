<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import ActivityTimeline from '@/components/ActivityTimeline.vue'
import StatusSelect from '@/components/StatusSelect.vue'
import { OUTREACH_STATUSES } from '@/lib/status'
import { relativeUrl } from '@/lib/utils'
import contactRoutes from '@/routes/contacts'
import { attention as attentionRoute, reply as replyRoute } from '@/routes/inbox'
import type { Conversation } from '@/types'

// One conversation, opened: who they are, the thread, and answering it.
// Shared by `Inbox.vue` and the dashboard's to-review modal. `sent` is
// whether it was opened from the Sent folder, where writing by hand also
// stops a sequence still running.
const props = defineProps<{
    conversation: Conversation
    sent: boolean
}>()

const emit = defineEmits<{ close: [] }>()

const page = usePage()
const projectSlug = computed(() => page.props.currentProject!.slug)

// The user's own verdict, both directions: "I've seen this" and "actually,
// put it back". A status change or a fresh reply still move it on their own
// (`SetOutreachStatus`, `FetchReplies::pause()`); this is the one path that
// can also go from done back to todo.
const togglingAttention = ref(false)

function toggleAttention () {
    togglingAttention.value = true

    router.put(attentionRoute.url({ project: projectSlug.value, conversation: props.conversation.id }), { resolved: !props.conversation.resolved }, {
        preserveScroll: true,
        onFinish: () => { togglingAttention.value = false }
    })
}

function when (value: string | null) {
    return value === null ? '' : new Date(value).toLocaleString()
}

function initial (conversation: Conversation) {
    return (conversation.lead.name ?? conversation.lead.email ?? '?').charAt(0).toUpperCase()
}
</script>

<template>
    <div class="flex h-[85vh] max-h-[46rem] flex-col">
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-default p-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-elevated text-sm font-semibold text-toned">
                    {{ initial(conversation) }}
                </span>

                <ULink
                    :href="relativeUrl(contactRoutes.show.url({ project: projectSlug, contact: conversation.lead.id }))"
                    class="min-w-0 truncate font-medium text-highlighted"
                >{{ conversation.lead.name ?? conversation.lead.email }}</ULink>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <!-- The user's own verdict, separate from status:
                     whether THEY have looked at this one. -->
                <UButton
                    :icon="conversation.resolved ? 'i-lucide-rotate-ccw' : 'i-lucide-check'"
                    :color="conversation.resolved ? 'neutral' : 'primary'"
                    :variant="conversation.resolved ? 'outline' : 'solid'"
                    size="sm"
                    :label="conversation.resolved ? 'Mark as todo' : 'Mark as done'"
                    :loading="togglingAttention"
                    @click="toggleAttention"
                />
                <UButton
                    icon="i-lucide-x"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    @click="emit('close')"
                />
            </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[19rem_1fr]">
            <!-- Left: who they are and the timeline, next to the
                 thread rather than a click away, so a call logged
                 mid-conversation needs no context switch. -->
            <div class="flex min-h-0 flex-col gap-4 overflow-y-auto border-b border-default p-4 md:border-b-0 md:border-e">
                <dl class="space-y-2 text-sm">
                    <div v-if="conversation.lead.title || conversation.lead.company">
                        <dt class="text-xs text-dimmed">
                            Role
                        </dt>
                        <dd>{{ [conversation.lead.title, conversation.lead.company].filter(Boolean).join(' · ') }}</dd>
                    </div>
                    <div v-if="conversation.lead.email">
                        <dt class="text-xs text-dimmed">
                            Email
                        </dt>
                        <dd class="truncate font-mono text-xs">
                            {{ conversation.lead.email }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-dimmed">
                            Campaign
                        </dt>
                        <dd>{{ conversation.campaign.name }}</dd>
                    </div>
                    <div>
                        <dt class="mb-1 text-xs text-dimmed">
                            Status
                        </dt>
                        <StatusSelect
                            :status="conversation.lead.status"
                            :options="OUTREACH_STATUSES"
                            :url="contactRoutes.status.url({ project: projectSlug, contact: conversation.lead.id })"
                        />
                    </div>
                </dl>

                <div class="space-y-3 border-t border-default pt-4">
                    <h4 class="text-xs font-medium tracking-wider text-dimmed uppercase">
                        Timeline
                    </h4>

                    <ActivityTimeline
                        :notes="conversation.lead.notes"
                        :store-url="contactRoutes.notes.store.url({ project: projectSlug, contact: conversation.lead.id })"
                        :destroy-url="note => contactRoutes.notes.destroy.url({ project: projectSlug, contact: conversation.lead.id, note: note.id })"
                    />
                </div>
            </div>

            <!-- Right: the thread, and answering it. -->
            <div class="flex min-h-0 min-w-0 flex-col">
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <div
                        v-for="message in conversation.messages"
                        :key="message.id"
                        class="rounded-lg p-3 text-sm"
                        :class="message.direction === 'inbound' ? 'bg-elevated' : 'ring ring-default'"
                    >
                        <p class="mb-1 text-xs text-dimmed">
                            {{ message.direction === 'inbound' ? 'Them' : 'You' }} · {{ when(message.at) }} · {{ message.subject }}
                            <span
                                v-if="message.direction === 'outbound' && message.status && message.status !== 'sent'"
                                class="text-error"
                            >· never left: {{ message.status }}</span>
                        </p>
                        <p class="whitespace-pre-wrap break-words">
                            {{ message.body }}
                        </p>
                    </div>
                </div>

                <!-- Answering by hand stops the sequence: somebody
                     being written to by a person must not also
                     receive the follow-up queued behind them. -->
                <Form
                    v-slot="{ errors, processing }"
                    v-bind="replyRoute.form({ project: projectSlug, conversation: conversation.id })"
                    class="shrink-0 space-y-2 border-t border-default p-4"
                    :options="{ preserveScroll: true }"
                >
                    <UFormField
                        name="body"
                        :error="errors.body"
                        :help="sent
                            ? 'Sent from the same mailbox, in the same thread. Writing by hand stops the sequence: nobody should get your mail and the queued follow-up as well.'
                            : 'Sent from the same mailbox, in the same thread. Your signature is added if the mailbox has one.'"
                    >
                        <UTextarea
                            name="body"
                            :rows="4"
                            placeholder="Write back…"
                            class="w-full"
                        />
                    </UFormField>

                    <UButton
                        type="submit"
                        :loading="processing"
                        :label="sent ? 'Send and stop the sequence' : 'Send reply'"
                    />
                </Form>
            </div>
        </div>
    </div>
</template>
