<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { inbox } from '@/routes'
import { reply as replyRoute } from '@/routes/inbox'
import type { Conversation, Paginated } from '@/types'
import { CLASSIFICATIONS } from '@/types/inbox'

const props = defineProps<{
    conversations: Paginated<Conversation>
    campaigns: { id: number, name: string }[]
    filters: { campaign: number | null }
}>()

// `0` rather than an empty string: reka reserves '' for clearing a select, and
// a SelectItem carrying it throws on mount.
const campaign = ref(props.filters.campaign ?? 0)

const CAMPAIGN_OPTIONS = computed(() => [
    { label: 'Every campaign', value: 0 },
    ...props.campaigns.map(item => ({ label: item.name, value: item.id }))
])

function filter (value: number) {
    router.get(inbox.url(), value ? { campaign: value } : {}, { preserveState: true, preserveScroll: true })
}

// Open on the one that needs an answer, so the screen is useful on arrival
// rather than after a click.
const open = ref<number | null>(props.conversations.data.find(item => item.needs_attention)?.id ?? null)

function verdict (conversation: Conversation) {
    return conversation.classification ? CLASSIFICATIONS[conversation.classification] : null
}

function when (value: string | null) {
    return value === null ? '' : new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout>
        <Head title="Inbox" />

        <div class="space-y-4 p-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-medium">
                        Inbox
                    </h2>
                    <p class="text-sm text-muted">
                        Everyone who answered, across every mailbox. An agent read
                        each reply and did something about it. What it decided is
                        on the row. Nothing was answered on your behalf.
                    </p>
                </div>

                <USelect
                    v-model="campaign"
                    :items="CAMPAIGN_OPTIONS"
                    class="w-64"
                    @update:model-value="filter"
                />
            </div>

            <p
                v-if="!conversations.data.length"
                class="rounded-lg p-6 text-sm text-muted ring ring-default"
            >
                Nobody has replied yet. Only real answers land here: a lead that
                was written to and said nothing is a sequence still running, not
                an inbox entry.
            </p>

            <div
                v-for="conversation in conversations.data"
                :key="conversation.id"
                class="rounded-lg ring ring-default"
                :class="conversation.needs_attention ? 'ring-primary' : ''"
            >
                <button
                    type="button"
                    class="flex w-full flex-wrap items-center gap-3 p-4 text-left"
                    @click="open = open === conversation.id ? null : conversation.id"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ conversation.lead.name ?? conversation.lead.email }}
                            <span
                                v-if="conversation.lead.company"
                                class="text-muted"
                            >· {{ conversation.lead.company }}</span>
                        </p>
                        <p class="truncate text-sm text-muted">
                            {{ conversation.messages[conversation.messages.length - 1]?.body }}
                        </p>
                    </div>

                    <UBadge
                        v-if="verdict(conversation)"
                        :color="verdict(conversation)!.color"
                        variant="subtle"
                        :label="verdict(conversation)!.label"
                        :title="verdict(conversation)!.help"
                    />

                    <UBadge
                        color="neutral"
                        variant="outline"
                        :label="conversation.campaign.name"
                    />

                    <span class="text-sm text-dimmed">{{ when(conversation.replied_at) }}</span>
                </button>

                <div
                    v-if="open === conversation.id"
                    class="space-y-3 border-t border-default p-4"
                >
                    <div
                        v-for="message in conversation.messages"
                        :key="message.id"
                        class="rounded-lg p-3 text-sm"
                        :class="message.direction === 'inbound' ? 'bg-elevated' : 'ring ring-default'"
                    >
                        <p class="mb-1 text-xs text-dimmed">
                            {{ message.direction === 'inbound' ? 'Them' : 'You' }} · {{ when(message.at) }} · {{ message.subject }}
                        </p>
                        <p class="whitespace-pre-wrap">
                            {{ message.body }}
                        </p>
                    </div>

                    <!-- Answering by hand stops the sequence: somebody being
                         written to by a person must not also receive the
                         follow-up queued behind them. -->
                    <Form
                        v-slot="{ errors, processing }"
                        v-bind="replyRoute.form(conversation.id)"
                        class="space-y-2"
                        :options="{ preserveScroll: true }"
                    >
                        <UFormField
                            name="body"
                            :error="errors.body"
                            help="Sent from the same mailbox, in the same thread. Your signature is added if the mailbox has one."
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
                            label="Send reply"
                        />
                    </Form>
                </div>
            </div>

            <div
                v-if="conversations.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="conversations.meta.current_page"
                    :items-per-page="conversations.meta.per_page"
                    :total="conversations.meta.total"
                    @update:page="page => router.get(inbox.url({ query: { page } }), {}, { preserveState: true })"
                />
            </div>
        </div>
    </AppLayout>
</template>
