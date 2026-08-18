<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import campaignRoutes from '@/routes/campaigns'
import stepRoutes from '@/routes/campaigns/steps'

type Step = {
    id: number
    position: number
    type: string
    delay_hours: number | null
    intent: string | null
    subject: string | null
    body: string | null
}

type Sample = {
    step_id: number | null
    messages: { lead: string, company: string | null, subject: string, body: string }[]
}

// A single resource arrives unwrapped too — no `data` envelope on either.
const props = defineProps<{
    campaign: {
        id: number
        name: string
        status: string
        target_profile: { id: number | null, name: string | null, type: string | null } | null
        steps: Step[]
    }
    sample?: Sample
}>()

const STATUSES = [
    { label: 'Draft — nothing sends', value: 'draft' },
    { label: 'Active', value: 'active' },
    { label: 'Paused', value: 'paused' }
]

const name = ref(props.campaign.name)
const status = ref(props.campaign.status)
const previewing = ref<number | null>(null)

function save (step: Step) {
    router.put(stepRoutes.update.url([props.campaign.id, step.id]), {
        type: step.type,
        delay_hours: step.delay_hours,
        subject: step.subject,
        body: step.body,
        intent: step.intent
    }, { preserveScroll: true })
}

function add (type: 'email' | 'wait') {
    router.post(stepRoutes.store.url(props.campaign.id), {
        type,
        delay_hours: type === 'wait' ? 72 : null,
        subject: type === 'email' ? 'follow-up' : null,
        body: type === 'email' ? '' : null
    }, { preserveScroll: true })
}

// The whole order travels, never one moved row: positions are unique per
// campaign, so renumbering one at a time collides with itself halfway through.
function move (index: number, by: number) {
    const ids = props.campaign.steps.map(step => step.id)
    const target = index + by

    if (target < 0 || target >= ids.length) {
        return
    }

    ;[ids[index], ids[target]] = [ids[target], ids[index]]

    router.put(campaignRoutes.stepOrder.url(props.campaign.id), { steps: ids }, { preserveScroll: true })
}

// A partial reload: personalising costs a model call per lead, so it happens
// when this button is pressed and at no other moment.
function preview (step: Step) {
    previewing.value = step.id

    router.reload({
        only: ['sample'],
        data: { preview_step: step.id },
        onFinish: () => previewing.value = null
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="campaign.name" />

        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center gap-3">
                <UInput
                    v-model="name"
                    class="w-80"
                    @blur="router.put(campaignRoutes.update.url(campaign.id), { name, status }, { preserveScroll: true })"
                />

                <USelect
                    v-model="status"
                    :items="STATUSES"
                    class="w-56"
                    @update:model-value="value => router.put(campaignRoutes.update.url(campaign.id), { name, status: value }, { preserveScroll: true })"
                />

                <UBadge
                    v-if="campaign.target_profile?.name"
                    color="neutral"
                    variant="subtle"
                    :label="campaign.target_profile.name"
                    :icon="campaign.target_profile.type === 'partner' ? 'i-lucide-handshake' : 'i-lucide-crosshair'"
                />

                <UButton
                    class="ms-auto"
                    color="error"
                    variant="ghost"
                    icon="i-lucide-trash-2"
                    label="Delete"
                    @click="router.delete(campaignRoutes.destroy.url(campaign.id))"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-3">
                    <div
                        v-for="(step, index) in campaign.steps"
                        :key="step.id"
                        class="space-y-3 rounded-lg p-4 ring ring-default"
                    >
                        <div class="flex items-center gap-2">
                            <UBadge
                                color="neutral"
                                variant="subtle"
                                :icon="step.type === 'wait' ? 'i-lucide-clock' : 'i-lucide-mail'"
                                :label="step.type"
                            />

                            <span
                                v-if="step.intent"
                                class="min-w-0 flex-1 truncate text-sm text-muted"
                            >{{ step.intent }}</span>

                            <UButton
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                icon="i-lucide-arrow-up"
                                aria-label="Move earlier"
                                :disabled="index === 0"
                                @click="move(index, -1)"
                            />
                            <UButton
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                icon="i-lucide-arrow-down"
                                aria-label="Move later"
                                :disabled="index === campaign.steps.length - 1"
                                @click="move(index, 1)"
                            />
                            <UButton
                                color="error"
                                variant="ghost"
                                size="xs"
                                icon="i-lucide-x"
                                aria-label="Remove this step"
                                @click="router.delete(stepRoutes.destroy.url([campaign.id, step.id]), { preserveScroll: true })"
                            />
                        </div>

                        <UFormField
                            v-if="step.type === 'wait'"
                            label="Wait"
                            help="Hours before the next step. Same day reads as automation."
                        >
                            <UInput
                                v-model.number="step.delay_hours"
                                type="number"
                                class="w-32"
                                @blur="save(step)"
                            />
                        </UFormField>

                        <template v-else>
                            <UInput
                                :model-value="step.subject ?? ''"
                                placeholder="Subject"
                                class="w-full"
                                @update:model-value="value => step.subject = String(value)"
                                @blur="save(step)"
                            />

                            <UTextarea
                                :model-value="step.body ?? ''"
                                :rows="8"
                                autoresize
                                class="w-full"
                                @update:model-value="value => step.body = String(value)"
                                @blur="save(step)"
                            />

                            <UButton
                                color="neutral"
                                variant="subtle"
                                size="xs"
                                icon="i-lucide-eye"
                                :loading="previewing === step.id"
                                label="Preview on real leads"
                                @click="preview(step)"
                            />
                        </template>
                    </div>

                    <div class="flex gap-2">
                        <UButton
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-mail-plus"
                            label="Add a mail"
                            @click="add('email')"
                        />
                        <UButton
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-clock"
                            label="Add a wait"
                            @click="add('wait')"
                        />
                    </div>
                </div>

                <div class="space-y-3">
                    <p class="text-sm text-muted">
                        What actually goes out, written for leads you have already found.
                        The opener comes from what the qualifier observed about them —
                        nobody researches a prospect by hand here.
                    </p>

                    <p
                        v-if="sample && !sample.messages.length"
                        class="text-sm text-dimmed"
                    >
                        No lead with an address yet, so there is nothing to write to.
                    </p>

                    <div
                        v-for="(message, index) in sample?.messages ?? []"
                        :key="index"
                        class="space-y-2 rounded-lg p-4 ring ring-default"
                    >
                        <p class="text-sm font-medium">
                            {{ message.company ?? message.lead }}
                            <span class="text-dimmed">· {{ message.lead }}</span>
                        </p>
                        <p class="text-sm font-medium">
                            {{ message.subject }}
                        </p>
                        <p class="whitespace-pre-wrap text-sm text-muted">
                            {{ message.body }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
