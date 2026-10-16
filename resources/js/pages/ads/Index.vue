<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
import adRoutes from '@/routes/ads'
import adPlatformRoutes from '@/routes/settings/ad-platforms'
import autonomyRoutes from '@/routes/settings/autonomy'
import type { AdAccount, AdCampaign, AdDecision } from '@/types'

defineOptions({ layout: AppLayout })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
const props = defineProps<{
    accounts: AdAccount[]
    campaigns: AdCampaign[]
    decisions: AdDecision[]
    platforms: { value: string, label: string, configured: boolean }[]
    autonomyLevel: 'supervised' | 'semi_auto' | 'autonomous'
    reviewing: boolean
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

// Decisions get applied in the background and the review runs on a queue,
// so this screen rereads.
usePoll(15000, { only: ['accounts', 'campaigns', 'decisions', 'reviewing'] })

const waiting = computed(() => props.decisions.filter(decision => decision.status === 'proposed'))
const history = computed(() => props.decisions.filter(decision => decision.status !== 'proposed'))
const campaignById = computed(() => new Map(props.campaigns.map(campaign => [campaign.id, campaign])))
const live = computed(() => props.campaigns.filter(campaign => campaign.status !== 'draft'))

// A local draft synced from the prop, not `default-value`: see `.ai/rules/js.md`.
const budgets = ref<Record<number, number>>({})
watch(() => props.accounts, (accounts) => {
    budgets.value = Object.fromEntries(accounts.map(account => [account.id, account.monthly_budget]))
}, { immediate: true, deep: true })

const reasons = ref<Record<number, string>>({})
const deciding = ref<number | null>(null)

function decide (decision: AdDecision, answer: 'approve' | 'reject') {
    deciding.value = decision.id
    router.put(adRoutes.decisions.update.url({ project: slug.value, adDecision: decision.id }), {
        decision: answer,
        reason: reasons.value[decision.id] ?? null
    }, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: answer === 'approve' ? 'Approved' : 'Rejected', color: answer === 'approve' ? 'success' : 'neutral' }),
        onFinish: () => deciding.value = null
    })
}

const reviewStarting = ref(false)

function review () {
    reviewStarting.value = true
    router.post(adRoutes.reviews.store.url({ project: slug.value }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Review started', color: 'success' }),
        onFinish: () => reviewStarting.value = false
    })
}

function disconnect (account: AdAccount) {
    router.delete(adRoutes.accounts.destroy.url({ project: slug.value, adAccount: account.id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Account disconnected', color: 'neutral' })
    })
}

function money (amount: number, currency: string) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
}

function spentPercent (account: AdAccount) {
    return account.monthly_budget > 0 ? Math.min(100, Math.round(account.spent_this_month / account.monthly_budget * 100)) : 0
}

const AUTONOMY_LABEL = { supervised: 'Supervised', semi_auto: 'Semi-auto', autonomous: 'Autonomous' }

const STATUS_COLOR = {
    draft: 'neutral',
    active: 'success',
    paused: 'warning',
    removed: 'neutral',
    proposed: 'primary',
    approved: 'primary',
    applied: 'success',
    rejected: 'neutral',
    blocked: 'warning',
    failed: 'error'
} as const
</script>

<template>
    <Head title="Ads" />

    <div class="space-y-8 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    Ads
                </h2>
                <p class="text-sm text-muted">
                    Eveil runs your ads inside the monthly budget you set on each
                    account: it launches tests, keeps what brings users, cuts what
                    does not, and tries winners on other networks. Autonomy:
                    <ULink
                        :to="relativeUrl(autonomyRoutes.edit.url({ project: slug }))"
                        class="text-primary"
                    >
                        {{ AUTONOMY_LABEL[autonomyLevel] }}
                    </ULink>.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <template
                    v-for="platform in platforms"
                    :key="platform.value"
                >
                    <UButton
                        v-if="platform.configured"
                        :href="adRoutes.connect.url({ project: slug, platform: platform.value })"
                        external
                        icon="i-lucide-plug"
                        color="neutral"
                        variant="outline"
                        :label="`Connect ${platform.label}`"
                    />
                    <UButton
                        v-else
                        :to="relativeUrl(adPlatformRoutes.index.url({ project: slug }))"
                        icon="i-lucide-settings"
                        color="neutral"
                        variant="outline"
                        :label="`Set up ${platform.label}`"
                    />
                </template>

                <UButton
                    icon="i-lucide-sparkles"
                    label="Review now"
                    :loading="reviewStarting || reviewing"
                    :disabled="!accounts.some(account => account.monthly_budget > 0)"
                    @click="review"
                />
            </div>
        </div>

        <UAlert
            v-if="page.props.status"
            color="success"
            variant="subtle"
            icon="i-lucide-check"
            :description="String(page.props.status)"
        />

        <UAlert
            v-for="(error, key) in page.props.errors"
            :key="key"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            :description="String(error)"
        />

        <section
            v-if="waiting.length"
            class="space-y-3"
        >
            <h3 class="text-sm font-medium text-muted">
                Waiting for you
            </h3>

            <UCard
                v-for="decision in waiting"
                :key="decision.id"
            >
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <UBadge
                            :label="decision.type_label"
                            variant="subtle"
                        />
                        <span
                            v-if="decision.campaign_id && campaignById.get(decision.campaign_id)"
                            class="font-medium"
                        >
                            {{ campaignById.get(decision.campaign_id)!.name }}
                        </span>
                        <span
                            v-if="decision.daily_budget !== null && decision.campaign_id && campaignById.get(decision.campaign_id)"
                            class="text-sm text-muted"
                        >
                            {{ money(campaignById.get(decision.campaign_id)!.daily_budget, campaignById.get(decision.campaign_id)!.currency) }}
                            to {{ money(decision.daily_budget, campaignById.get(decision.campaign_id)!.currency) }} a day
                        </span>
                    </div>

                    <p class="text-sm">
                        {{ decision.reasoning }}
                    </p>

                    <template v-if="decision.campaign_id && campaignById.get(decision.campaign_id)">
                        <div
                            v-if="decision.type === 'launch_campaign'"
                            class="space-y-1 text-sm text-muted"
                        >
                            <p>{{ campaignById.get(decision.campaign_id)!.hypothesis }}</p>
                            <p>
                                {{ campaignById.get(decision.campaign_id)!.platform_label }},
                                {{ money(campaignById.get(decision.campaign_id)!.daily_budget, campaignById.get(decision.campaign_id)!.currency) }} a day,
                                {{ (campaignById.get(decision.campaign_id)!.targeting.countries ?? []).join(', ') }}
                            </p>
                            <p v-if="campaignById.get(decision.campaign_id)!.targeting.keywords?.length">
                                Keywords: {{ campaignById.get(decision.campaign_id)!.targeting.keywords!.join(', ') }}
                            </p>
                        </div>

                        <div class="grid gap-2 md:grid-cols-2">
                            <div
                                v-for="ad in campaignById.get(decision.campaign_id)!.ads.filter(ad => decision.type === 'launch_campaign' || ad.id === decision.ad_id)"
                                :key="ad.id"
                                class="space-y-1 rounded-md p-3 text-sm ring ring-default"
                            >
                                <p class="text-xs text-dimmed">
                                    {{ ad.angle }}
                                </p>
                                <p class="font-medium text-primary">
                                    {{ ad.headlines.join(' | ') }}
                                </p>
                                <p>{{ ad.descriptions.join(' ') }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ ad.final_url }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-2">
                        <UInput
                            v-model="reasons[decision.id]"
                            placeholder="Why not? (optional, the agent reads it)"
                            class="min-w-64 flex-1"
                        />
                        <UButton
                            color="neutral"
                            variant="outline"
                            label="Reject"
                            :loading="deciding === decision.id"
                            @click="decide(decision, 'reject')"
                        />
                        <UButton
                            label="Approve"
                            :loading="deciding === decision.id"
                            @click="decide(decision, 'approve')"
                        />
                    </div>
                </div>
            </UCard>
        </section>

        <section class="space-y-3">
            <h3 class="text-sm font-medium text-muted">
                Ad accounts
            </h3>

            <div
                v-for="account in accounts"
                :key="account.id"
                class="space-y-3 rounded-lg p-4 ring ring-default"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ account.name }}
                        </p>
                        <p class="text-sm text-muted">
                            {{ account.platform_label }} {{ account.external_id }}
                        </p>
                    </div>

                    <UBadge
                        v-if="!account.has_conversion_tracking"
                        color="warning"
                        variant="subtle"
                        label="No conversion tracking"
                    />
                    <UBadge
                        :color="account.status === 'active' ? 'success' : 'error'"
                        variant="subtle"
                        :label="account.status === 'active' ? 'Connected' : 'Reconnect needed'"
                    />
                    <UButton
                        icon="i-lucide-trash-2"
                        color="error"
                        variant="ghost"
                        size="xs"
                        @click="disconnect(account)"
                    />
                </div>

                <p
                    v-if="account.last_error"
                    class="text-sm text-error"
                >
                    {{ account.last_error }}
                </p>

                <p
                    v-if="!account.has_conversion_tracking"
                    class="text-sm text-muted"
                >
                    Without the network's tag on your site, Eveil optimizes for clicks
                    rather than signups. Install it to let it aim at real users.
                </p>

                <div class="space-y-1">
                    <UProgress :model-value="spentPercent(account)" />
                    <p class="text-sm text-muted">
                        {{ money(account.spent_this_month, account.currency) }} spent of
                        {{ money(account.monthly_budget, account.currency) }} this month,
                        {{ money(account.active_daily_budget, account.currency) }} a day running now.
                    </p>
                </div>

                <Form
                    v-slot="{ errors, processing }"
                    v-bind="adRoutes.accounts.update.form({ project: slug, adAccount: account.id })"
                    class="flex flex-wrap items-end gap-3"
                    :options="{ preserveScroll: true }"
                    @success="toast.add({ title: 'Budget saved', color: 'success' })"
                >
                    <UFormField
                        :label="`Monthly budget (${account.currency})`"
                        :error="errors.monthly_budget"
                        :hint="account.monthly_budget === 0 ? 'Zero: Eveil spends nothing here' : undefined"
                    >
                        <UInput
                            v-model="budgets[account.id]"
                            name="monthly_budget"
                            type="number"
                            min="0"
                            step="1"
                            class="w-44"
                        />
                    </UFormField>
                    <UButton
                        type="submit"
                        label="Save"
                        :loading="processing"
                    />
                </Form>
            </div>

            <p
                v-if="!accounts.length"
                class="rounded-lg p-6 text-sm text-muted ring ring-default"
            >
                No ad account connected yet. Connect one above, then give it a
                monthly budget: Eveil spends nothing until you do.
            </p>
        </section>

        <section
            v-if="live.length"
            class="space-y-3"
        >
            <h3 class="text-sm font-medium text-muted">
                Campaigns
            </h3>

            <UCard
                v-for="campaign in live"
                :key="campaign.id"
            >
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ campaign.name }}</span>
                        <UBadge
                            :color="STATUS_COLOR[campaign.status]"
                            variant="subtle"
                            :label="campaign.status"
                        />
                        <span class="text-sm text-muted">
                            {{ campaign.platform_label }}, {{ money(campaign.daily_budget, campaign.currency) }} a day
                        </span>
                    </div>

                    <p class="text-sm text-muted">
                        {{ campaign.hypothesis }}
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-muted">
                                <tr>
                                    <th class="py-1 pr-3 font-normal">
                                        Ad
                                    </th>
                                    <th class="py-1 pr-3 font-normal">
                                        Status
                                    </th>
                                    <th class="py-1 pr-3 text-right font-normal">
                                        Impressions
                                    </th>
                                    <th class="py-1 pr-3 text-right font-normal">
                                        Clicks
                                    </th>
                                    <th class="py-1 pr-3 text-right font-normal">
                                        CTR
                                    </th>
                                    <th class="py-1 pr-3 text-right font-normal">
                                        Cost
                                    </th>
                                    <th class="py-1 text-right font-normal">
                                        Conversions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="ad in campaign.ads"
                                    :key="ad.id"
                                    class="border-t border-default"
                                >
                                    <td class="py-2 pr-3">
                                        <p class="font-medium">
                                            {{ ad.headlines[0] }}
                                        </p>
                                        <p class="text-xs text-muted">
                                            {{ ad.angle }}
                                        </p>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <UBadge
                                            :color="STATUS_COLOR[ad.status]"
                                            variant="subtle"
                                            size="sm"
                                            :label="ad.status"
                                        />
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        {{ ad.impressions }}
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        {{ ad.clicks }}
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        {{ ad.ctr === null ? '' : `${ad.ctr}%` }}
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums">
                                        {{ money(ad.cost, campaign.currency) }}
                                    </td>
                                    <td class="py-2 text-right tabular-nums">
                                        {{ ad.conversions }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </UCard>
        </section>

        <section
            v-if="history.length"
            class="space-y-3"
        >
            <h3 class="text-sm font-medium text-muted">
                What Eveil did
            </h3>

            <div class="divide-y divide-default rounded-lg ring ring-default">
                <div
                    v-for="decision in history"
                    :key="decision.id"
                    class="space-y-1 p-4 text-sm"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <UBadge
                            :color="STATUS_COLOR[decision.status]"
                            variant="subtle"
                            size="sm"
                            :label="decision.status"
                        />
                        <span class="font-medium">{{ decision.type_label }}</span>
                        <span
                            v-if="decision.campaign_id && campaignById.get(decision.campaign_id)"
                            class="text-muted"
                        >
                            {{ campaignById.get(decision.campaign_id)!.name }}
                        </span>
                        <span class="ml-auto text-xs text-dimmed">
                            {{ new Date(decision.created_at).toLocaleString() }}
                            <template v-if="decision.decided_by">, by {{ decision.decided_by }}</template>
                        </span>
                    </div>
                    <p>{{ decision.reasoning }}</p>
                    <p
                        v-if="decision.outcome"
                        class="text-muted"
                    >
                        {{ decision.outcome }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
