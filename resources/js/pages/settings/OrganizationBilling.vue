<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import billingRoutes from '@/routes/settings/organization/billing'
import type { CreditTransactionRow, ProjectCreditRow } from '@/types'

const props = defineProps<{
    checkout: string | null
    onTrial: boolean
    balance: number
    creditsPerDollar: number
    hasPaymentMethod: boolean
    autoTopup: { threshold: number | null, amountCents: number | null }
    transactions: CreditTransactionRow[]
    creditsByProject: ProjectCreditRow[]
}>()

// The bar is relative to whichever project spent the most, not to the org's
// total: a two-project org where one spent 90 and the other 10 should show a
// nearly-empty bar for the second, not a 10% sliver next to a 90% one that
// both read as "small".
const highestSpend = computed(() => Math.max(1, ...props.creditsByProject.map(row => row.credits)))

const topUpDollars = ref(20)
const topUpCredits = computed(() => Math.floor(topUpDollars.value * props.creditsPerDollar))

// A draft synced from props, not a one-time `ref(props.x)`: Inertia keeps
// this component instance alive across the save redirect, so a plain ref
// would freeze at whatever was true on first mount (`.ai/rules/js.md`).
const autoTopupEnabled = ref(false)
const autoTopupThreshold = ref(500)
const autoTopupDollars = ref(20)

watch(() => props.autoTopup, (autoTopup) => {
    autoTopupEnabled.value = autoTopup.threshold !== null
    autoTopupThreshold.value = autoTopup.threshold ?? 500
    autoTopupDollars.value = autoTopup.amountCents ? autoTopup.amountCents / 100 : 20
}, { immediate: true, deep: true })

function describe (row: CreditTransactionRow): string {
    if (row.type === 'debit') {
        return row.agent ?? 'Agent call'
    }

    const labels: Record<string, string> = {
        grant_trial: 'Trial credits',
        grant_purchase: 'Credits purchased'
    }

    return labels[row.type] ?? row.type
}
</script>

<template>
    <SettingsLayout title="Billing">
        <Head title="Billing" />

        <div class="max-w-2xl space-y-6">
            <UAlert
                v-if="checkout === 'success'"
                color="success"
                variant="subtle"
                icon="i-lucide-check"
                description="Payment received. Credits are on their way and will show up here in a moment."
            />

            <!-- Stripe confirms via webhook, not the redirect itself, so
                 `hasPaymentMethod` below can still lag this by a moment. -->
            <UAlert
                v-if="checkout === 'payment-method-saved'"
                color="success"
                variant="subtle"
                icon="i-lucide-check"
                description="Payment method saved. This can take a few seconds to show below."
            />

            <UCard>
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-medium">
                            Credit balance
                        </h2>

                        <!-- Not shown on trial: nothing has been invoiced yet,
                             since no top-up has gone through Stripe. -->
                        <UButton
                            v-if="!onTrial"
                            :href="billingRoutes.portal.url()"
                            external
                            size="sm"
                            variant="link"
                            color="neutral"
                            icon="i-lucide-receipt"
                            label="Invoices"
                        />
                    </div>
                </template>

                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-semibold">{{ balance.toLocaleString() }}</span>
                    <span class="text-sm text-muted">credits</span>
                </div>
                <p class="mt-1 text-sm text-dimmed">
                    ${{ (1 / creditsPerDollar).toFixed(4) }} per credit. Credits never expire.
                </p>

                <UAlert
                    v-if="onTrial"
                    class="mt-4"
                    color="info"
                    variant="subtle"
                    icon="i-lucide-sparkles"
                    title="You're on the trial"
                    description="One project, a cap on leads found per day, and no export until your first top-up. Buying credits below lifts all three."
                />
            </UCard>

            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Top up
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Pay-as-you-go: choose any amount, no plan to pick.
                    </p>
                </template>

                <Form
                    v-slot="{ processing }"
                    v-bind="billingRoutes.checkout.form()"
                    class="flex flex-wrap items-end gap-3"
                >
                    <UFormField label="Amount ($)">
                        <UInput
                            v-model.number="topUpDollars"
                            type="number"
                            min="1"
                            step="1"
                            class="w-32"
                        />
                    </UFormField>

                    <input
                        type="hidden"
                        name="amount_cents"
                        :value="Math.round(topUpDollars * 100)"
                    >

                    <p class="text-sm text-muted">
                        ≈ {{ topUpCredits.toLocaleString() }} credits
                    </p>

                    <UButton
                        type="submit"
                        :loading="processing"
                        :disabled="topUpDollars < 1"
                        label="Buy credits"
                        class="ml-auto"
                    />
                </Form>
            </UCard>

            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Auto top-up
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Recharge automatically instead of running dry mid-search. Charges the
                        card on file, no confirmation prompt, since nobody is at the
                        keyboard when the wallet crosses the threshold.
                    </p>
                </template>

                <div
                    v-if="!hasPaymentMethod"
                    class="flex items-center justify-between gap-3 rounded-lg bg-elevated p-3"
                >
                    <p class="text-sm text-muted">
                        Add a payment method to turn this on.
                    </p>
                    <UButton
                        :href="billingRoutes.paymentMethod.create.url()"
                        external
                        variant="soft"
                        label="Add payment method"
                    />
                </div>

                <Form
                    v-else
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="billingRoutes.autoTopup.form()"
                    class="space-y-4"
                >
                    <UCheckbox
                        v-model="autoTopupEnabled"
                        label="Recharge automatically"
                    />

                    <div
                        v-if="autoTopupEnabled"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <UFormField
                            label="When balance drops below (credits)"
                            name="auto_topup_threshold"
                            :error="errors.auto_topup_threshold"
                        >
                            <UInput
                                v-model.number="autoTopupThreshold"
                                name="auto_topup_threshold"
                                type="number"
                                min="0"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            label="Charge this much ($)"
                            :error="errors.auto_topup_amount_cents"
                        >
                            <UInput
                                v-model.number="autoTopupDollars"
                                type="number"
                                min="1"
                                class="w-full"
                            />
                        </UFormField>

                        <input
                            type="hidden"
                            name="auto_topup_amount_cents"
                            :value="Math.round(autoTopupDollars * 100)"
                        >
                    </div>

                    <template v-if="!autoTopupEnabled">
                        <input
                            type="hidden"
                            name="auto_topup_threshold"
                            value=""
                        >
                        <input
                            type="hidden"
                            name="auto_topup_amount_cents"
                            value=""
                        >
                    </template>

                    <div class="flex items-center gap-3">
                        <UButton
                            type="submit"
                            :loading="processing"
                            label="Save"
                        />
                        <span
                            v-if="recentlySuccessful"
                            class="text-sm text-muted"
                        >Saved.</span>
                    </div>
                </Form>

                <UButton
                    v-if="hasPaymentMethod"
                    :href="billingRoutes.paymentMethod.create.url()"
                    external
                    class="mt-3"
                    color="neutral"
                    variant="link"
                    label="Change payment method"
                />
            </UCard>

            <UCard v-if="creditsByProject.length > 1">
                <template #header>
                    <h2 class="font-medium">
                        Spend by project
                    </h2>
                </template>

                <div class="space-y-3">
                    <div
                        v-for="project in creditsByProject"
                        :key="project.id"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted">{{ project.name }}</span>
                            <span>{{ project.credits.toLocaleString() }} credits</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full rounded-full bg-elevated">
                            <div
                                class="h-1.5 rounded-full bg-primary"
                                :style="{ width: `${(project.credits / highestSpend) * 100}%` }"
                            />
                        </div>
                    </div>
                </div>
            </UCard>

            <UCard v-if="transactions.length">
                <template #header>
                    <h2 class="font-medium">
                        Recent activity
                    </h2>
                </template>

                <div class="space-y-1">
                    <div
                        v-for="(row, index) in transactions"
                        :key="index"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-muted">{{ describe(row) }}</span>
                        <span :class="row.credits >= 0 ? 'text-success' : 'text-dimmed'">
                            {{ row.credits >= 0 ? '+' : '' }}{{ row.credits.toLocaleString() }}
                        </span>
                    </div>
                </div>
            </UCard>
        </div>
    </SettingsLayout>
</template>
