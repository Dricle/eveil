<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AdPreview from '@/components/ads/AdPreview.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { relativeUrl } from '@/lib/utils'
import adRoutes from '@/routes/ads'
import adPlatformRoutes from '@/routes/settings/ad-platforms'
import autonomyRoutes from '@/routes/settings/autonomy'
import type { AdAccount, AdBudget, AdCampaign, AdDecision } from '@/types'

defineOptions({ layout: AppLayout })

// Inline rather than a type alias imported through the barrel: an alias there
// silently declares no props at all.
const props = defineProps<{
    budget: AdBudget
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
usePoll(15000, { only: ['budget', 'accounts', 'campaigns', 'decisions', 'reviewing'] })

const waiting = computed(() => props.decisions.filter(decision => decision.status === 'proposed'))
const history = computed(() => props.decisions.filter(decision => decision.status !== 'proposed'))
const campaignById = computed(() => new Map(props.campaigns.map(campaign => [campaign.id, campaign])))
const live = computed(() => props.campaigns.filter(campaign => campaign.status !== 'draft'))
const running = computed(() => live.value.filter(campaign => campaign.status === 'active'))

function campaignOf (decision: AdDecision): AdCampaign | undefined {
    return decision.campaign_id === null ? undefined : campaignById.value.get(decision.campaign_id)
}

// A local draft synced from the prop, not `default-value`: see `.ai/rules/js.md`.
const monthlyBudget = ref(0)
const budgetCurrency = ref<string | undefined>(undefined)
watch(() => props.budget, (budget) => {
    monthlyBudget.value = budget.monthly
    budgetCurrency.value = budget.currency ?? budget.currencies[0]
}, { immediate: true, deep: true })

const editingBudget = ref(false)
const spentPercent = computed(() => props.budget.monthly > 0 ? Math.min(100, Math.round(props.budget.spent_this_month / props.budget.monthly * 100)) : 0)
const canReview = computed(() => props.budget.monthly > 0 && props.accounts.some(account => account.usable))

/**
 * What stands between this project and its first review, in order. Hidden
 * once everything is done.
 */
const setupSteps = computed(() => [
    {
        label: 'Connect an ad account',
        done: props.accounts.length > 0
    },
    {
        label: 'Switch on the accounts Eveil may spend on',
        done: props.accounts.some(account => account.enabled)
    },
    {
        label: 'Set the monthly budget',
        done: props.budget.monthly > 0
    }
])

const connectItems = computed(() => [
    props.platforms.filter(platform => platform.configured).map(platform => ({
        label: platform.label,
        icon: 'i-lucide-plug',
        // A full page load: the OAuth dance leaves the app.
        onSelect: () => window.location.assign(adRoutes.connect.url({ project: slug.value, platform: platform.value }))
    })),
    [{
        label: 'Set up another network',
        icon: 'i-lucide-settings',
        onSelect: () => router.visit(relativeUrl(adPlatformRoutes.index.url({ project: slug.value })))
    }]
])

/**
 * A campaign's lifetime numbers, its ads together.
 */
function totalsOf (campaign: AdCampaign) {
    const sum = (key: 'impressions' | 'clicks' | 'cost' | 'conversions') => campaign.ads.reduce((total, ad) => total + ad[key], 0)
    const [impressions, clicks, cost, conversions] = [sum('impressions'), sum('clicks'), sum('cost'), sum('conversions')]

    return {
        impressions,
        clicks,
        cost,
        conversions,
        ctr: impressions > 0 ? Math.round(clicks / impressions * 10000) / 100 : null,
        costPerConversion: conversions > 0 ? cost / conversions : null
    }
}

function scrollToWaiting () {
    document.getElementById('waiting')?.scrollIntoView({ behavior: 'smooth' })
}

const tab = ref('campaigns')
const tabs = computed(() => [
    { label: 'Campaigns', value: 'campaigns', icon: 'i-lucide-megaphone', badge: live.value.length || undefined },
    { label: 'Ad accounts', value: 'accounts', icon: 'i-lucide-wallet', badge: props.accounts.length || undefined },
    { label: 'What Eveil did', value: 'history', icon: 'i-lucide-history', badge: history.value.length || undefined }
])

const toggling = ref<number | null>(null)

function toggle (account: AdAccount, enabled: boolean) {
    toggling.value = account.id
    router.put(adRoutes.accounts.update.url({ project: slug.value, adAccount: account.id }), { enabled }, {
        preserveScroll: true,
        onFinish: () => toggling.value = null
    })
}

function pickIdentity (account: AdAccount, identityId: string) {
    toggling.value = account.id
    router.put(adRoutes.accounts.update.url({ project: slug.value, adAccount: account.id }), { identity_id: identityId }, {
        preserveScroll: true,
        onFinish: () => toggling.value = null
    })
}

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

function count (value: number) {
    return new Intl.NumberFormat().format(value)
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

const STATUS_ICON = {
    applied: 'i-lucide-circle-check',
    approved: 'i-lucide-circle-check',
    proposed: 'i-lucide-circle-dot',
    rejected: 'i-lucide-circle-x',
    blocked: 'i-lucide-shield-alert',
    failed: 'i-lucide-triangle-alert'
} as const
</script>

<template>
    <Head title="Ads" />

    <div class="space-y-6 p-6">
        <!-- Header: what this is, who decides, and the two actions. -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-medium">
                        Ads
                    </h2>
                    <UBadge
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-gauge"
                    >
                        <ULink :to="relativeUrl(autonomyRoutes.edit.url({ project: slug }))">
                            {{ AUTONOMY_LABEL[autonomyLevel] }}
                        </ULink>
                    </UBadge>
                </div>
                <p class="max-w-3xl text-sm text-muted">
                    Eveil spreads one monthly budget over your ad accounts, tests
                    ideas, keeps what brings users and cuts what does not. It reviews
                    every morning.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <UDropdownMenu :items="connectItems">
                    <UButton
                        icon="i-lucide-plug"
                        color="neutral"
                        variant="outline"
                        label="Connect an account"
                        trailing-icon="i-lucide-chevron-down"
                    />
                </UDropdownMenu>
                <UButton
                    icon="i-lucide-sparkles"
                    :label="reviewing ? 'Reviewing…' : 'Review now'"
                    :loading="reviewStarting || reviewing"
                    :disabled="!canReview"
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

        <!-- First run: the steps left before Eveil can spend anything. -->
        <section
            v-if="setupSteps.some(step => !step.done)"
            class="space-y-3 rounded-lg bg-elevated/50 p-4 ring ring-default"
        >
            <h3 class="font-medium">
                Get started
            </h3>
            <ol class="space-y-2 text-sm">
                <li
                    v-for="(step, index) in setupSteps"
                    :key="step.label"
                    class="flex items-center gap-2"
                    :class="step.done ? 'text-muted line-through' : ''"
                >
                    <UIcon
                        :name="step.done ? 'i-lucide-circle-check' : 'i-lucide-circle'"
                        class="size-4 shrink-0"
                        :class="step.done ? 'text-success' : 'text-dimmed'"
                    />
                    {{ index + 1 }}. {{ step.label }}
                </li>
            </ol>
            <p class="text-sm text-muted">
                Eveil spends nothing until all three are done.
            </p>
        </section>

        <!-- Key numbers. -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="space-y-2 rounded-lg p-4 ring ring-default sm:col-span-2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm text-muted">
                        Monthly budget
                    </p>
                    <UButton
                        v-if="budget.currencies.length"
                        :icon="editingBudget ? 'i-lucide-x' : 'i-lucide-pencil'"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        :label="editingBudget ? 'Cancel' : 'Edit'"
                        @click="editingBudget = !editingBudget"
                    />
                </div>

                <Form
                    v-if="editingBudget || (budget.currencies.length && budget.monthly === 0)"
                    v-slot="{ errors, processing }"
                    v-bind="adRoutes.budget.update.form({ project: slug })"
                    class="flex flex-wrap items-end gap-2"
                    :options="{ preserveScroll: true }"
                    @success="editingBudget = false; toast.add({ title: 'Budget saved', color: 'success' })"
                >
                    <UFormField
                        label="Per month"
                        :error="errors.monthly_budget"
                        :hint="budget.monthly === 0 ? 'Zero: Eveil spends nothing' : undefined"
                    >
                        <UInput
                            v-model="monthlyBudget"
                            name="monthly_budget"
                            type="number"
                            min="0"
                            step="1"
                            class="w-40"
                        />
                    </UFormField>
                    <UFormField
                        label="Currency"
                        :error="errors.currency"
                    >
                        <USelect
                            v-model="budgetCurrency"
                            :items="budget.currencies"
                            class="w-24"
                        />
                        <input
                            type="hidden"
                            name="currency"
                            :value="budgetCurrency"
                        >
                    </UFormField>
                    <UButton
                        type="submit"
                        label="Save"
                        :loading="processing"
                    />
                </Form>

                <template v-else-if="budget.currency && budget.monthly > 0">
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ money(budget.spent_this_month, budget.currency) }}
                        <span class="text-base font-normal text-muted">of {{ money(budget.monthly, budget.currency) }}</span>
                    </p>
                    <UProgress
                        :model-value="spentPercent"
                        :color="spentPercent >= 90 ? 'warning' : 'primary'"
                        size="sm"
                    />
                    <p class="text-xs text-muted">
                        {{ spentPercent }}% spent this month, every account together
                    </p>
                </template>

                <p
                    v-else
                    class="text-sm text-muted"
                >
                    Connect an ad account first: the budget is set in its currency.
                </p>
            </div>

            <div class="space-y-1 rounded-lg p-4 ring ring-default">
                <p class="text-sm text-muted">
                    Running now
                </p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ budget.currency ? money(budget.active_daily_budget, budget.currency) : '–' }}
                    <span class="text-base font-normal text-muted">/ day</span>
                </p>
                <p class="text-xs text-muted">
                    {{ running.length }} campaign{{ running.length === 1 ? '' : 's' }} live
                </p>
            </div>

            <button
                type="button"
                class="space-y-1 rounded-lg p-4 text-left ring transition"
                :class="waiting.length ? 'bg-primary/5 ring-primary' : 'ring-default'"
                @click="scrollToWaiting"
            >
                <p class="text-sm text-muted">
                    Waiting for you
                </p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ waiting.length }}
                </p>
                <p class="text-xs text-muted">
                    {{ waiting.length ? 'Decisions to approve or reject' : 'Nothing to approve' }}
                </p>
            </button>
        </div>

        <!-- Decisions waiting for a person: the one thing to do on this page. -->
        <section
            v-if="waiting.length"
            id="waiting"
            class="space-y-3"
        >
            <h3 class="flex items-center gap-2 font-medium">
                <UIcon
                    name="i-lucide-inbox"
                    class="size-4 text-primary"
                />
                Waiting for you
            </h3>

            <div
                v-for="decision in waiting"
                :key="decision.id"
                class="space-y-4 rounded-lg p-4 ring ring-primary/40"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <UBadge
                        :label="decision.type_label"
                        variant="subtle"
                    />
                    <span
                        v-if="campaignOf(decision)"
                        class="font-medium"
                    >{{ campaignOf(decision)!.name }}</span>
                    <span
                        v-if="campaignOf(decision)"
                        class="text-sm text-muted"
                    >
                        {{ campaignOf(decision)!.platform_label }}
                    </span>
                    <span class="ml-auto text-xs text-dimmed">
                        {{ new Date(decision.created_at).toLocaleString() }}
                    </span>
                </div>

                <div
                    v-if="decision.daily_budget !== null && campaignOf(decision)"
                    class="flex items-center gap-2 text-sm"
                >
                    <span class="tabular-nums text-muted line-through">{{ money(campaignOf(decision)!.daily_budget, campaignOf(decision)!.currency) }}</span>
                    <UIcon
                        name="i-lucide-arrow-right"
                        class="size-4 text-dimmed"
                    />
                    <span class="font-medium tabular-nums">{{ money(decision.daily_budget, campaignOf(decision)!.currency) }} a day</span>
                </div>

                <p class="text-sm">
                    {{ decision.reasoning }}
                </p>

                <template v-if="campaignOf(decision)">
                    <div
                        v-if="decision.type === 'launch_campaign'"
                        class="grid gap-x-6 gap-y-1 rounded-md bg-elevated/50 p-3 text-sm sm:grid-cols-[auto_1fr]"
                    >
                        <span class="text-muted">Tests</span>
                        <span>{{ campaignOf(decision)!.hypothesis }}</span>
                        <span class="text-muted">Budget</span>
                        <span class="tabular-nums">{{ money(campaignOf(decision)!.daily_budget, campaignOf(decision)!.currency) }} a day</span>
                        <span class="text-muted">Countries</span>
                        <span>{{ (campaignOf(decision)!.targeting.countries ?? []).join(', ') || 'Any' }}</span>
                        <template v-if="campaignOf(decision)!.targeting.keywords?.length">
                            <span class="text-muted">Keywords</span>
                            <span>{{ campaignOf(decision)!.targeting.keywords!.join(', ') }}</span>
                        </template>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <AdPreview
                            v-for="ad in campaignOf(decision)!.ads.filter(ad => decision.type === 'launch_campaign' || ad.id === decision.ad_id)"
                            :key="ad.id"
                            :ad="ad"
                        />
                    </div>
                </template>

                <div class="flex flex-wrap items-center gap-2 border-t border-default pt-4">
                    <UInput
                        v-model="reasons[decision.id]"
                        placeholder="Why not? (optional, Eveil reads it at its next review)"
                        class="min-w-64 flex-1"
                    />
                    <UButton
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-x"
                        label="Reject"
                        :loading="deciding === decision.id"
                        @click="decide(decision, 'reject')"
                    />
                    <UButton
                        icon="i-lucide-check"
                        label="Approve"
                        :loading="deciding === decision.id"
                        @click="decide(decision, 'approve')"
                    />
                </div>
            </div>
        </section>

        <UTabs
            v-model="tab"
            :items="tabs"
            variant="link"
            :unmount-on-hide="false"
        >
            <template #content="{ item }">
                <div class="pt-4">
                    <!-- Campaigns -->
                    <div
                        v-if="item.value === 'campaigns'"
                        class="space-y-4"
                    >
                        <div
                            v-for="campaign in live"
                            :key="campaign.id"
                            class="space-y-4 rounded-lg p-4 ring ring-default"
                        >
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ campaign.name }}</span>
                                        <UBadge
                                            :color="STATUS_COLOR[campaign.status]"
                                            variant="subtle"
                                            size="sm"
                                            :label="campaign.status"
                                        />
                                    </div>
                                    <p class="text-sm text-muted">
                                        {{ campaign.hypothesis }}
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <p class="font-medium tabular-nums">
                                        {{ money(campaign.daily_budget, campaign.currency) }} / day
                                    </p>
                                    <p class="text-muted">
                                        {{ campaign.platform_label }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                <div
                                    v-for="stat in [
                                        { label: 'Impressions', value: count(totalsOf(campaign).impressions) },
                                        { label: 'Clicks', value: count(totalsOf(campaign).clicks) },
                                        { label: 'CTR', value: totalsOf(campaign).ctr === null ? '–' : `${totalsOf(campaign).ctr}%` },
                                        { label: 'Spent', value: money(totalsOf(campaign).cost, campaign.currency) },
                                        { label: 'Per signup', value: totalsOf(campaign).costPerConversion === null ? '–' : money(totalsOf(campaign).costPerConversion!, campaign.currency) }
                                    ]"
                                    :key="stat.label"
                                    class="rounded-md bg-elevated/50 px-3 py-2"
                                >
                                    <p class="text-xs text-muted">
                                        {{ stat.label }}
                                    </p>
                                    <p class="font-medium tabular-nums">
                                        {{ stat.value }}
                                    </p>
                                </div>
                            </div>

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
                                                Spent
                                            </th>
                                            <th class="py-1 text-right font-normal">
                                                Signups
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
                                                <div class="flex items-center gap-3">
                                                    <img
                                                        v-if="ad.image_url"
                                                        :src="ad.image_url"
                                                        :alt="ad.angle"
                                                        class="size-10 shrink-0 rounded object-cover"
                                                    >
                                                    <div class="min-w-0">
                                                        <p class="truncate font-medium">
                                                            {{ ad.headlines[0] }}
                                                        </p>
                                                        <p class="truncate text-xs text-muted">
                                                            {{ ad.angle }}
                                                        </p>
                                                    </div>
                                                </div>
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
                                                {{ count(ad.impressions) }}
                                            </td>
                                            <td class="py-2 pr-3 text-right tabular-nums">
                                                {{ count(ad.clicks) }}
                                            </td>
                                            <td class="py-2 pr-3 text-right tabular-nums">
                                                {{ ad.ctr === null ? '–' : `${ad.ctr}%` }}
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

                        <div
                            v-if="!live.length"
                            class="flex flex-col items-center gap-2 rounded-lg p-10 text-center text-sm text-muted ring ring-default"
                        >
                            <UIcon
                                name="i-lucide-megaphone"
                                class="size-6 text-dimmed"
                            />
                            <p>No campaign yet.</p>
                            <p v-if="canReview">
                                Eveil proposes its first ones at the next review, or now with Review now.
                            </p>
                            <p v-else>
                                They come once an account is connected and the budget set.
                            </p>
                        </div>
                    </div>

                    <!-- Ad accounts -->
                    <div
                        v-else-if="item.value === 'accounts'"
                        class="space-y-3"
                    >
                        <div
                            v-for="account in accounts"
                            :key="account.id"
                            class="space-y-3 rounded-lg p-4 ring ring-default"
                            :class="account.enabled ? '' : 'opacity-80'"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium">
                                            {{ account.name }}
                                        </p>
                                        <UBadge
                                            :color="account.status === 'active' ? 'success' : 'error'"
                                            variant="subtle"
                                            size="sm"
                                            :label="account.status === 'active' ? 'Connected' : 'Reconnect needed'"
                                        />
                                    </div>
                                    <p class="text-sm text-muted">
                                        {{ account.platform_label }} · {{ account.external_id }} · {{ account.currency }}
                                    </p>
                                </div>

                                <p
                                    v-if="account.enabled"
                                    class="text-right text-sm"
                                >
                                    <span class="font-medium tabular-nums">{{ money(account.spent_this_month, account.currency) }}</span>
                                    <span class="text-muted"> this month</span>
                                    <br>
                                    <span class="text-muted tabular-nums">{{ money(account.active_daily_budget, account.currency) }} / day now</span>
                                </p>

                                <USwitch
                                    :model-value="account.enabled"
                                    :loading="toggling === account.id"
                                    label="Use for ads"
                                    @update:model-value="toggle(account, $event)"
                                />
                                <UButton
                                    icon="i-lucide-trash-2"
                                    color="error"
                                    variant="ghost"
                                    size="xs"
                                    aria-label="Disconnect"
                                    @click="disconnect(account)"
                                />
                            </div>

                            <div
                                v-if="account.identity_label"
                                class="flex flex-wrap items-center gap-2 text-sm"
                            >
                                <span class="text-muted">Ads posted as</span>
                                <USelect
                                    v-if="account.identities.length"
                                    :model-value="account.identity_id ?? undefined"
                                    :items="account.identities.map(identity => ({ label: identity.name, value: identity.id }))"
                                    :placeholder="`Pick a ${account.identity_label}`"
                                    :loading="toggling === account.id"
                                    :color="account.identity_id ? 'neutral' : 'warning'"
                                    :highlight="!account.identity_id"
                                    class="w-64"
                                    @update:model-value="pickIdentity(account, $event)"
                                />
                            </div>

                            <!-- Everything that keeps this account from working well, one line each. -->
                            <div
                                v-if="account.last_error
                                    || (account.identity_label && !account.identity_id)
                                    || (budget.currency && account.currency !== budget.currency)
                                    || !account.has_conversion_tracking"
                                class="space-y-1 text-sm"
                            >
                                <p
                                    v-if="account.last_error"
                                    class="flex gap-2 text-error"
                                >
                                    <UIcon
                                        name="i-lucide-circle-alert"
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    {{ account.last_error }}
                                </p>
                                <p
                                    v-if="account.identity_label && !account.identity_id"
                                    class="flex gap-2 text-warning"
                                >
                                    <UIcon
                                        name="i-lucide-circle-alert"
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    <template v-if="account.identities.length">
                                        Pick the {{ account.identity_label }} these ads are posted as before switching the account on.
                                    </template>
                                    <template v-else>
                                        No {{ account.identity_label }} reachable. Create one, then connect the account again.
                                    </template>
                                </p>
                                <p
                                    v-if="budget.currency && account.currency !== budget.currency"
                                    class="flex gap-2 text-muted"
                                >
                                    <UIcon
                                        name="i-lucide-info"
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    Billed in {{ account.currency }}, not the budget's {{ budget.currency }}: not used.
                                </p>
                                <p
                                    v-if="!account.has_conversion_tracking"
                                    class="flex gap-2 text-muted"
                                >
                                    <UIcon
                                        name="i-lucide-info"
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    No conversion tracking: Eveil aims at clicks rather than signups. Install the network's tag on your site.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="!accounts.length"
                            class="flex flex-col items-center gap-2 rounded-lg p-10 text-center text-sm text-muted ring ring-default"
                        >
                            <UIcon
                                name="i-lucide-wallet"
                                class="size-6 text-dimmed"
                            />
                            <p>No ad account connected yet.</p>
                            <UDropdownMenu :items="connectItems">
                                <UButton
                                    icon="i-lucide-plug"
                                    label="Connect an account"
                                    trailing-icon="i-lucide-chevron-down"
                                />
                            </UDropdownMenu>
                        </div>
                    </div>

                    <!-- History -->
                    <div v-else>
                        <ol
                            v-if="history.length"
                            class="space-y-4 border-l border-default pl-5"
                        >
                            <li
                                v-for="decision in history"
                                :key="decision.id"
                                class="relative space-y-1 text-sm"
                            >
                                <UIcon
                                    :name="STATUS_ICON[decision.status]"
                                    class="absolute top-0.5 -left-[1.95rem] size-4 bg-default"
                                    :class="{
                                        'text-success': STATUS_COLOR[decision.status] === 'success',
                                        'text-warning': STATUS_COLOR[decision.status] === 'warning',
                                        'text-error': STATUS_COLOR[decision.status] === 'error',
                                        'text-primary': STATUS_COLOR[decision.status] === 'primary',
                                        'text-dimmed': STATUS_COLOR[decision.status] === 'neutral'
                                    }"
                                />
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ decision.type_label }}</span>
                                    <span
                                        v-if="campaignOf(decision)"
                                        class="text-muted"
                                    >{{ campaignOf(decision)!.name }}</span>
                                    <UBadge
                                        :color="STATUS_COLOR[decision.status]"
                                        variant="subtle"
                                        size="sm"
                                        :label="decision.status"
                                    />
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
                            </li>
                        </ol>

                        <div
                            v-else
                            class="flex flex-col items-center gap-2 rounded-lg p-10 text-center text-sm text-muted ring ring-default"
                        >
                            <UIcon
                                name="i-lucide-history"
                                class="size-6 text-dimmed"
                            />
                            <p>Nothing yet. Every move Eveil makes shows here, with its reasoning.</p>
                        </div>
                    </div>
                </div>
            </template>
        </UTabs>
    </div>
</template>
