<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import hostRoutes from '@/routes/app-settings/hosts'
import type { Paginated } from '@/types'

type Host = {
    id: number
    host: string
    kind: string
    reason: string | null
    harvest_status: string | null
    pages_harvested: number
    businesses_found: number
    is_locked: boolean
    last_verified_at: string
    last_harvested_at: string | null
}

const props = defineProps<{
    hosts: Paginated<Host>
    filters: { kind: string, search: string }
    kinds: string[]
}>()

const kind = ref(props.filters.kind || 'all')
const search = ref(props.filters.search)

// Reka reserves the empty string for clearing a selection, so "everything" is a
// sentinel the query simply omits.
watch([kind, search], () => router.get(hostRoutes.index.url(), {
    kind: kind.value === 'all' ? undefined : kind.value,
    search: search.value || undefined
}, { preserveState: true, replace: true, only: ['hosts', 'filters'] }))

const KIND_LABELS: Record<string, string> = {
    index: 'Lists businesses, harvest it',
    entity: 'One organisation, a possible lead',
    social: 'Social platform, never a lead',
    other: 'Neither: search, reference, forum, docs'
}

function correct (host: Host, kind: string) {
    router.put(hostRoutes.update.url(host.id), {
        kind,
        reason: host.reason,
        // Correcting settles the matter: no re-triage may overwrite it.
        is_locked: true
    }, { preserveScroll: true })
}
</script>

<template>
    <AppSettingsLayout title="Host registry">
        <Head title="Host registry" />

        <div class="max-w-4xl space-y-4">
            <p class="text-sm text-muted">
                What this install has worked out about hosts on the open web,
                shared by every project. A wrong verdict is cached with exactly
                the same confidence as a right one. A real prospect filed as
                "neither" is invisible everywhere at once. Correcting one locks
                it, and a locked verdict is never rewritten by a model.
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search a host"
                    class="w-64"
                />

                <USelect
                    v-model="kind"
                    :items="[{ label: 'Every kind', value: 'all' }, ...kinds.map(value => ({ label: value, value }))]"
                    class="w-56"
                />
            </div>

            <p
                v-if="!hosts.data.length"
                class="text-sm text-muted"
            >
                Nothing learned yet. Hosts land here the first time a search
                returns them.
            </p>

            <div
                v-for="host in hosts.data"
                :key="host.id"
                class="space-y-2 rounded-lg p-4 ring ring-default"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium">
                            <span class="truncate">{{ host.host }}</span>

                            <UBadge
                                v-if="host.is_locked"
                                color="primary"
                                variant="subtle"
                                icon="i-lucide-lock"
                                label="Set by a human"
                            />
                        </p>

                        <p class="truncate text-sm text-muted">
                            {{ KIND_LABELS[host.kind] ?? host.kind }}
                            <span v-if="host.reason">: {{ host.reason }}</span>
                        </p>
                    </div>

                    <span
                        v-if="host.harvest_status"
                        class="text-sm text-dimmed"
                    >
                        {{ host.harvest_status }} ·
                        {{ host.businesses_found }} found over
                        {{ host.pages_harvested }} pages
                    </span>

                    <USelect
                        :model-value="host.kind"
                        :items="kinds"
                        class="w-36"
                        @update:model-value="value => correct(host, value)"
                    />

                    <UButton
                        v-if="host.is_locked"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        icon="i-lucide-unlock"
                        aria-label="Let the model judge this host again"
                        @click="router.put(hostRoutes.update.url(host.id), {
                            kind: host.kind,
                            reason: host.reason,
                            is_locked: false
                        }, { preserveScroll: true })"
                    />
                </div>
            </div>

            <div
                v-if="hosts.meta.last_page > 1"
                class="flex justify-center"
            >
                <UPagination
                    :default-page="hosts.meta.current_page"
                    :items-per-page="hosts.meta.per_page"
                    :total="hosts.meta.total"
                    @update:page="page => router.get(hostRoutes.index.url(), { ...filters, page }, { preserveState: true })"
                />
            </div>
        </div>
    </AppSettingsLayout>
</template>
