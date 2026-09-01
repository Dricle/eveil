<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import agentRoutes from '@/routes/app-settings/agents'

type AgentLine = {
    slug: string
    provider: string
    model: string | null
    timeout: number
    overridden: boolean
    strict: boolean
    smallModelOk: boolean
    calls: number
    tokens_in: number
    tokens_out: number
    avg_tokens_in: number
    avg_tokens_out: number
    /** Present on every edition; only cloud ever reads it as a real price. */
    credit_price: number | null
}

const props = defineProps<{
    agents: AgentLine[]
    labs: string[]
    configured: Record<string, boolean>
    models: Record<string, string[]>
}>()

const page = usePage()

// Edited in place, so the row keeps what was typed until it is saved.
// An empty box means the provider's own default, which the server stores as
// null, since the input itself only ever holds a string.
const asDraft = (agents: AgentLine[]) => agents.map(agent => ({
    ...agent,
    model: agent.model ?? '',
    creditPriceDraft: agent.credit_price ?? 0
}))

const draft = ref(asDraft(props.agents))

watch(() => props.agents, agents => draft.value = asDraft(agents))

// Keyed the same way as the draft, so a row's dirtiness is one lookup rather
// than a re-scan of the original props on every keystroke.
const original = computed(() => new Map(props.agents.map(agent => [agent.slug, agent])))

function isDirty (line: { slug: string, provider: string, model: string, timeout: number }) {
    const source = original.value.get(line.slug)

    return source !== undefined && (
        line.provider !== source.provider
        || (line.model || null) !== source.model
        || line.timeout !== source.timeout
    )
}

const dirty = computed(() => draft.value.filter(isDirty))

const saving = ref(false)

// One request for every changed line, so a bulk remap costs one click and one
// redirect instead of one per agent. `preserveScroll` keeps the page from
// jumping back to the top while it saves.
function saveAll () {
    if (!dirty.value.length || saving.value) {
        return
    }

    router.put(agentRoutes.updateMany.url(), {
        agents: dirty.value.map(line => ({
            slug: line.slug,
            provider: line.provider,
            model: line.model || null,
            timeout: line.timeout
        }))
    }, {
        preserveScroll: true,
        onStart: () => { saving.value = true },
        onFinish: () => { saving.value = false }
    })
}

function saveCreditPrice (line: { slug: string, creditPriceDraft: number }) {
    // A new versioned row, never an update - the server enforces this,
    // the client just posts what was typed.
    router.post(agentRoutes.creditPrice.url(line.slug), {
        credits: line.creditPriceDraft
    }, { preserveScroll: true })
}

function formatTokens (count: number) {
    return count.toLocaleString()
}

// The first move for anybody not using the provider that ships. Eight lines
// changed one at a time, each needing a model id looked up somewhere else, is
// where a setup screen loses people.
//
// Only providers with a key: a mapping pointing at one without looks configured
// here and fails in a job an hour later.
const usable = computed(() => props.labs.filter(lab => props.configured[lab]))

// Nothing to offer when every agent already runs on the only provider that can
// be called.
const elsewhere = computed(() => usable.value.filter(
    lab => props.agents.some(agent => agent.provider !== lab)
))

function switchAll (provider: string) {
    router.put(agentRoutes.provider.url(), { provider }, { preserveScroll: true })
}
</script>

<template>
    <AppSettingsLayout title="Agents">
        <Head title="Agents" />

        <div class="max-w-5xl space-y-4">
            <p class="text-sm text-muted">
                One line per agent, discovered from the code. A fresh install
                works without touching this page. The defaults ship with the
                schema. Changing a model here takes effect on the next call, with
                no deploy.
            </p>

            <!-- One request for every changed row: editing several agents
                 before saving used to mean clicking Save once per row. -->
            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 rounded-lg bg-elevated p-4 ring ring-default">
                <p class="text-sm text-muted">
                    <template v-if="dirty.length">
                        {{ dirty.length }} agent{{ dirty.length === 1 ? '' : 's' }} changed
                    </template>
                    <template v-else>
                        No changes to save
                    </template>
                </p>

                <UButton
                    label="Save changes"
                    icon="i-lucide-save"
                    :loading="saving"
                    :disabled="! dirty.length"
                    @click="saveAll"
                />
            </div>

            <!-- One click for the whole mapping. Every agent keeps its timeout
                 and lands on the new provider's equivalent model: the one that
                 was on the smartest stays on the smartest, the one that was on
                 the cheapest stays on the cheapest. -->
            <div
                v-if="elsewhere.length"
                class="flex flex-wrap items-center gap-3 rounded-lg bg-elevated p-4"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">
                        Move every agent to one provider
                    </p>
                    <p class="text-sm text-muted">
                        Each keeps its timeout and lands on that provider's equivalent
                        model, the smartest for the ones that write, the cheapest for
                        the ones that read a page and return fields. Change any of them
                        below afterwards.
                    </p>
                </div>

                <UButton
                    v-for="lab in elsewhere"
                    :key="lab"
                    color="primary"
                    variant="subtle"
                    icon="i-lucide-replace-all"
                    :label="`Everything on ${lab}`"
                    @click="switchAll(lab)"
                />
            </div>

            <div
                v-for="line in draft"
                :key="line.slug"
                class="space-y-3 rounded-lg p-4 ring ring-default"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <p class="flex-1 font-medium">
                        {{ line.slug }}
                    </p>

                    <UBadge
                        v-if="line.strict"
                        color="warning"
                        variant="subtle"
                        icon="i-lucide-braces"
                        label="Needs reliable structured output"
                    />

                    <UBadge
                        v-if="line.smallModelOk"
                        color="success"
                        variant="subtle"
                        icon="i-lucide-piggy-bank"
                        label="Small model is fine"
                    />

                    <UBadge
                        :color="line.overridden ? 'primary' : 'neutral'"
                        variant="subtle"
                        :label="line.overridden ? 'Changed here' : 'Shipped default'"
                    />

                    <UBadge
                        v-if="configured[line.provider] === false"
                        color="error"
                        variant="subtle"
                        label="No key for this provider"
                    />
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <UFormField
                        label="Provider"
                        :name="`${line.slug}-provider`"
                    >
                        <USelect
                            v-model="line.provider"
                            :items="labs"
                            class="w-44"
                        />
                    </UFormField>

                    <UFormField
                        label="Model"
                        :name="`${line.slug}-model`"
                    >
                        <!-- Suggestions, not the allowed set: no list of model
                             ids exists to validate against, so anything typed
                             is kept. -->
                        <UInputMenu
                            v-model="line.model"
                            :items="models[line.provider] ?? []"
                            create-item="always"
                            placeholder="Empty means the provider's own default"
                            class="w-72"
                            @create="(value: string) => line.model = value"
                        />
                    </UFormField>

                    <UFormField
                        label="Timeout (s)"
                        :name="`${line.slug}-timeout`"
                    >
                        <UInput
                            v-model.number="line.timeout"
                            type="number"
                            min="5"
                            max="900"
                            class="w-28"
                        />
                    </UFormField>

                    <UButton
                        v-if="line.overridden"
                        color="neutral"
                        variant="ghost"
                        label="Reset"
                        @click="router.delete(agentRoutes.destroy.url(line.slug), { preserveScroll: true })"
                    />

                    <p class="flex-1 text-right text-sm text-muted">
                        {{ line.calls }} calls ·
                        {{ formatTokens(line.tokens_in) }} in /
                        {{ formatTokens(line.tokens_out) }} out
                        <template v-if="line.calls">
                            · avg {{ formatTokens(line.avg_tokens_in) }} in /
                            {{ formatTokens(line.avg_tokens_out) }} out per call
                        </template>
                    </p>
                </div>

                <!-- Cloud's calibration target for the per-agent credit
                     price grid. The row exists on every edition (harmless
                     where nothing reads it), so only the DISPLAY is gated
                     here. -->
                <div
                    v-if="page.props.edition === 'cloud'"
                    class="flex items-end gap-3 border-t border-default pt-3"
                >
                    <UFormField
                        label="Credit price"
                        :name="`${line.slug}-credit-price`"
                    >
                        <UInput
                            v-model.number="line.creditPriceDraft"
                            type="number"
                            min="1"
                            class="w-28"
                        />
                    </UFormField>

                    <UButton
                        color="neutral"
                        variant="soft"
                        label="Save price"
                        @click="saveCreditPrice(line)"
                    />

                    <p
                        v-if="line.credit_price === null"
                        class="flex-1 text-right text-sm text-warning"
                    >
                        Not priced yet: every call is refused.
                    </p>
                </div>
            </div>
        </div>
    </AppSettingsLayout>
</template>
