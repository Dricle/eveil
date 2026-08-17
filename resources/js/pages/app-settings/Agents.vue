<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import agentRoutes from '@/routes/app-settings/agents'

type AgentLine = {
    slug: string
    provider: string
    model: string | null
    timeout: number
    overridden: boolean
    strict: boolean
    calls: number
    tokens_in: number
    tokens_out: number
}

const props = defineProps<{
    agents: AgentLine[]
    labs: string[]
    configured: Record<string, boolean>
    models: Record<string, string[]>
}>()

// Edited in place, so the row keeps what was typed until it is saved.
// An empty box means the provider's own default, which the server stores as
// null — the input itself only ever holds a string.
const asDraft = (agents: AgentLine[]) => agents.map(agent => ({ ...agent, model: agent.model ?? '' }))

const draft = ref(asDraft(props.agents))

watch(() => props.agents, agents => draft.value = asDraft(agents))

function save (line: { slug: string, provider: string, model: string, timeout: number }) {
    router.put(agentRoutes.update.url(line.slug), {
        provider: line.provider,
        model: line.model || null,
        timeout: line.timeout
    }, { preserveScroll: true })
}

function formatTokens (count: number) {
    return count.toLocaleString()
}
</script>

<template>
    <AppSettingsLayout title="Agents">
        <Head title="Agents" />

        <div class="max-w-5xl space-y-4">
            <p class="text-sm text-muted">
                One line per agent, discovered from the code. A fresh install
                works without touching this page — the defaults ship with the
                schema. Changing a model here takes effect on the next call, with
                no deploy.
            </p>

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
                        label="Save"
                        @click="save(line)"
                    />

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
                    </p>
                </div>
            </div>
        </div>
    </AppSettingsLayout>
</template>
