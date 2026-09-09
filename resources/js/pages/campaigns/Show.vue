<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { regenerate as regenerateVariant, store as generateVariant } from '@/actions/App/Http/Controllers/StepVariantGenerationController'
import CampaignHeader from '@/components/CampaignHeader.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import campaignRoutes from '@/routes/campaigns'
import stepRoutes from '@/routes/campaigns/steps'
import stepVariantRoutes from '@/routes/campaigns/steps/variants'
import type { CampaignStatus } from '@/types'

type VariantStats = { sent: number, positive: number, unsubscribed: number }

type Variant = {
    id: number
    subject: string
    body: string
    weight: number
    stats: VariantStats
}

type Step = {
    id: number
    position: number
    type: string
    delay_hours: number | null
    intent: string | null
    variants: Variant[]
}

type Sample = {
    step_id: number | null
    messages: { lead: string, company: string | null, subject: string, body: string }[]
}

// A single resource arrives unwrapped too, with no `data` envelope on either.
const props = defineProps<{
    campaign: {
        id: number
        name: string
        status: CampaignStatus
        target_profile: { id: number | null, name: string | null, type: string | null } | null
        steps: Step[]
    }
    sample?: Sample
    writingVariant: boolean
    writingVariantError: string | null
}>()

const previewing = ref<number | null>(null)
const savingVariant = ref<number | null>(null)

// Writing an alternate mail takes a model call, so the page watches for it
// rather than leaving the user staring at a button that looks like it did
// nothing.
const poll = usePoll(
    3000,
    { only: ['campaign', 'writingVariant', 'writingVariantError'] },
    { autoStart: props.writingVariant }
)

watch(() => props.writingVariant, busy => busy ? poll.start() : poll.stop())

function saveStep (step: Step) {
    router.put(stepRoutes.update.url([props.campaign.id, step.id]), {
        type: step.type,
        delay_hours: step.delay_hours,
        intent: step.intent
    }, { preserveScroll: true })
}

function saveVariant (step: Step, variant: Variant) {
    // The weight input can be blurred empty or at 0; the backend requires at
    // least 1, so clamp here rather than let that 422 silently.
    variant.weight = Math.max(1, Math.round(variant.weight) || 1)

    savingVariant.value = variant.id

    router.put(stepVariantRoutes.update.url([props.campaign.id, step.id, variant.id]), {
        subject: variant.subject,
        body: variant.body,
        weight: variant.weight
    }, {
        preserveScroll: true,
        onFinish: () => { savingVariant.value = null }
    })
}

// Which step the "add a variant" modal is open for, and the optional steer
// typed into it. A left-blank guidance still generates something: the agent
// just picks its own angle rather than testing what the user had in mind.
const variantPrompt = ref<{ step: Step, guidance: string } | null>(null)

const variantModalOpen = computed({
    get: () => variantPrompt.value !== null,
    set: (open: boolean) => {
        if (!open) {
            variantPrompt.value = null
        }
    }
})

function openVariantModal (step: Step) {
    variantPrompt.value = { step, guidance: '' }
}

function generateVariantFromModal () {
    if (!variantPrompt.value) {
        return
    }

    const { step, guidance } = variantPrompt.value

    router.post(generateVariant.url([props.campaign.id, step.id]), {
        guidance: guidance.trim() || null
    }, {
        preserveScroll: true,
        onSuccess: () => { variantPrompt.value = null }
    })
}

// Which variant is being rewritten in place, and the instruction typed into
// the modal. Left blank, the agent still rewrites it, just picking its own
// angle rather than following a steer.
const regeneratePrompt = ref<{ step: Step, variant: Variant, guidance: string } | null>(null)

const regenerateModalOpen = computed({
    get: () => regeneratePrompt.value !== null,
    set: (open: boolean) => {
        if (!open) {
            regeneratePrompt.value = null
        }
    }
})

function openRegenerateModal (step: Step, variant: Variant) {
    regeneratePrompt.value = { step, variant, guidance: '' }
}

function regenerateVariantFromModal () {
    if (!regeneratePrompt.value) {
        return
    }

    const { step, variant, guidance } = regeneratePrompt.value

    router.post(regenerateVariant.url([props.campaign.id, step.id, variant.id]), {
        guidance: guidance.trim() || null
    }, {
        preserveScroll: true,
        onSuccess: () => { regeneratePrompt.value = null }
    })
}

function removeVariant (step: Step, variant: Variant) {
    router.delete(stepVariantRoutes.destroy.url([props.campaign.id, step.id, variant.id]), { preserveScroll: true })
}

function add (type: 'email' | 'wait') {
    router.post(stepRoutes.store.url(props.campaign.id), {
        type,
        delay_hours: type === 'wait' ? 72 : null,
        subject: type === 'email' ? 'follow-up' : null,
        // Same empty-string-becomes-null trap as `addVariant`: `body` is
        // required for an email step, so a blank draft here 422s silently.
        body: type === 'email' ? '…' : null
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
            <CampaignHeader
                :campaign="campaign"
                tab="sequence"
            />

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
                                @blur="saveStep(step)"
                            />
                        </UFormField>

                        <template v-else>
                            <div
                                v-for="(variant, variantIndex) in step.variants"
                                :key="variant.id"
                                class="space-y-2"
                                :class="{ 'border-t border-default pt-3': variantIndex > 0 }"
                            >
                                <div
                                    v-if="step.variants.length > 1"
                                    class="flex items-center gap-2 text-xs text-muted"
                                >
                                    <span class="font-medium">{{ String.fromCharCode(65 + variantIndex) }}</span>

                                    <span>{{ variant.stats.sent }} sent</span>
                                    <span v-if="variant.stats.sent > 0">· {{ variant.stats.positive }} interested</span>
                                    <span v-if="variant.stats.sent > 0">· {{ variant.stats.unsubscribed }} unsubscribed</span>

                                    <UFormField
                                        label="Weight"
                                        class="ml-auto flex items-center gap-1"
                                    >
                                        <UInput
                                            v-model.number="variant.weight"
                                            type="number"
                                            min="1"
                                            class="w-16"
                                            size="xs"
                                            @blur="saveVariant(step, variant)"
                                        />
                                    </UFormField>

                                    <UButton
                                        color="error"
                                        variant="ghost"
                                        size="xs"
                                        icon="i-lucide-x"
                                        aria-label="Remove this variant"
                                        @click="removeVariant(step, variant)"
                                    />
                                </div>

                                <UInput
                                    :model-value="variant.subject"
                                    placeholder="Subject"
                                    class="w-full"
                                    @update:model-value="value => variant.subject = String(value)"
                                />

                                <UTextarea
                                    :model-value="variant.body"
                                    :rows="8"
                                    autoresize
                                    class="w-full"
                                    @update:model-value="value => variant.body = String(value)"
                                />

                                <div class="flex gap-2">
                                    <UButton
                                        color="neutral"
                                        variant="subtle"
                                        size="xs"
                                        icon="i-lucide-save"
                                        :loading="savingVariant === variant.id"
                                        label="Save"
                                        @click="saveVariant(step, variant)"
                                    />
                                    <UButton
                                        color="neutral"
                                        variant="ghost"
                                        size="xs"
                                        icon="i-lucide-sparkles"
                                        :loading="writingVariant"
                                        :disabled="writingVariant"
                                        label="Regenerate"
                                        @click="openRegenerateModal(step, variant)"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <UButton
                                    color="neutral"
                                    variant="subtle"
                                    size="xs"
                                    icon="i-lucide-eye"
                                    :loading="previewing === step.id"
                                    label="Preview on real leads"
                                    @click="preview(step)"
                                />
                                <UButton
                                    color="neutral"
                                    variant="ghost"
                                    size="xs"
                                    icon="i-lucide-split"
                                    :loading="writingVariant"
                                    :disabled="writingVariant"
                                    :label="writingVariant ? 'Writing…' : 'Add a variant to A/B test'"
                                    @click="openVariantModal(step)"
                                />
                            </div>
                        </template>
                    </div>

                    <UAlert
                        v-if="writingVariantError"
                        color="error"
                        variant="subtle"
                        icon="i-lucide-triangle-alert"
                        title="The last variant was not written"
                        :description="writingVariantError"
                    />

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
                        The opener comes from what the qualifier observed about them,
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

        <UModal
            v-model:open="variantModalOpen"
            title="Add a variant to A/B test"
            description="Tell the agent what this version should test, or leave it blank and it picks its own angle."
        >
            <template #body>
                <UFormField
                    label="What should this version test?"
                    help="e.g. a shorter version, a more casual tone, leading with price instead of the pitch"
                >
                    <UTextarea
                        v-if="variantPrompt"
                        v-model="variantPrompt.guidance"
                        :rows="3"
                        placeholder="Optional"
                        class="w-full"
                        autofocus
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="variantModalOpen = false"
                />
                <UButton
                    icon="i-lucide-sparkles"
                    label="Generate"
                    @click="generateVariantFromModal"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="regenerateModalOpen"
            title="Regenerate this mail"
            description="Tell the agent what to change, or leave it blank and it rewrites the mail its own way."
        >
            <template #body>
                <UFormField
                    label="What should change?"
                    help="e.g. make it shorter, lead with the price, sound more casual"
                >
                    <UTextarea
                        v-if="regeneratePrompt"
                        v-model="regeneratePrompt.guidance"
                        :rows="3"
                        placeholder="Optional"
                        class="w-full"
                        autofocus
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="regenerateModalOpen = false"
                />
                <UButton
                    icon="i-lucide-sparkles"
                    label="Regenerate"
                    @click="regenerateVariantFromModal"
                />
            </template>
        </UModal>
    </AppLayout>
</template>
