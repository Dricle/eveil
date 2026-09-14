<script setup lang="ts">
import type { TimelineItem } from '@nuxt/ui'
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import type { Note } from '@/types/company'
import { CLASSIFICATIONS, type ConversationMessage } from '@/types/inbox'

// A CRM-style activity feed: notes typed by hand, interleaved with what
// actually went out and came back, newest first. Routing-agnostic on
// purpose - a lead and a company are two different tables underneath
// (`LeadNote`/`CompanyNote`), so the caller passes whichever
// `notes.store`/`notes.destroy` URLs apply. `messages` is optional: a
// company has none of its own, only the people at it do.
const props = defineProps<{
    notes: Note[]
    messages?: ConversationMessage[]
    storeUrl: string
    destroyUrl: (note: Note) => string
}>()

type TimelineEntry = TimelineItem & {
    value: string
    at: string | null
    kind: 'note' | 'message'
    note?: Note
    message?: ConversationMessage
}

const items = computed<TimelineEntry[]>(() => {
    const notes: TimelineEntry[] = props.notes.map(note => ({
        value: `note-${note.id}`,
        at: note.at,
        date: note.at ? new Date(note.at).toLocaleString() : '',
        icon: 'i-lucide-sticky-note',
        kind: 'note',
        note
    }))

    const messages: TimelineEntry[] = (props.messages ?? []).map(message => ({
        value: `message-${message.id}`,
        at: message.at,
        date: message.at ? new Date(message.at).toLocaleString() : '',
        icon: message.direction === 'inbound' ? 'i-lucide-corner-up-left' : 'i-lucide-send',
        kind: 'message',
        message
    }))

    return [...notes, ...messages].sort((a, b) => (b.at ?? '').localeCompare(a.at ?? ''))
})

// Truncated by default, expanded on click - independently, so reading one
// long reply does not collapse another already open.
const expanded = reactive(new Set<string>())

function toggle (value: string) {
    if (expanded.has(value)) {
        expanded.delete(value)
    } else {
        expanded.add(value)
    }
}

const body = ref('')
const submitting = ref(false)
const deletingId = ref<number | null>(null)

function submit () {
    if (!body.value.trim() || submitting.value) {
        return
    }

    submitting.value = true

    router.post(props.storeUrl, { body: body.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { body.value = '' },
        onFinish: () => { submitting.value = false }
    })
}

// A separate helper rather than indexing `CLASSIFICATIONS` inline: the
// truthiness check and the lookup need to be the SAME expression for
// TypeScript to narrow `Classification | null` down to `Classification`.
function classification (message?: ConversationMessage) {
    return message?.classification ? CLASSIFICATIONS[message.classification] : null
}

function destroy (note: Note) {
    deletingId.value = note.id

    router.delete(props.destroyUrl(note), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { deletingId.value = null }
    })
}
</script>

<template>
    <div class="space-y-3">
        <div class="space-y-2">
            <UTextarea
                v-model="body"
                :rows="2"
                placeholder="Called today, booked a meeting for…"
                class="w-full"
                @keydown.meta.enter="submit"
            />
            <div class="flex justify-end">
                <UButton
                    label="Add note"
                    icon="i-lucide-send"
                    size="xs"
                    :loading="submitting"
                    :disabled="!body.trim()"
                    @click="submit"
                />
            </div>
        </div>

        <p
            v-if="!items.length"
            class="text-sm text-muted"
        >
            Nothing logged yet.
        </p>

        <UTimeline
            v-else
            :items="items"
            size="sm"
            color="neutral"
            :ui="{ separator: 'bg-default' }"
        >
            <template #title="{ item }">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-medium text-highlighted">
                        {{ item.kind === 'note' ? (item.note!.author ?? 'Note') : (item.message!.direction === 'inbound' ? 'Them' : 'You') }}
                    </span>
                    <span
                        v-if="item.kind === 'message'"
                        class="min-w-0 truncate text-muted"
                    >{{ item.message!.subject }}</span>
                    <UBadge
                        v-if="classification(item.message)"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        :label="classification(item.message)!.label"
                    />
                    <UBadge
                        v-if="item.kind === 'message' && item.message!.status === 'bounced'"
                        color="error"
                        variant="subtle"
                        size="sm"
                        label="Bounced"
                    />
                </div>
            </template>

            <template #description="{ item }">
                <div class="group/note flex items-start justify-between gap-2">
                    <p
                        class="min-w-0 flex-1 cursor-pointer whitespace-pre-wrap"
                        :class="expanded.has(item.value) ? '' : 'line-clamp-2'"
                        @click="toggle(item.value)"
                    >
                        {{ item.kind === 'note' ? item.note!.body : item.message!.body }}
                    </p>

                    <UButton
                        v-if="item.kind === 'note'"
                        icon="i-lucide-trash-2"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        class="opacity-0 group-hover/note:opacity-100"
                        :loading="deletingId === item.note!.id"
                        @click.stop="destroy(item.note!)"
                    />
                </div>
            </template>
        </UTimeline>
    </div>
</template>
