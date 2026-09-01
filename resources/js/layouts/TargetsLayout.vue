<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { DropdownMenuItem } from '@nuxt/ui'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import targets from '@/routes/targets'
import type { TargetProfile } from '@/types'

const props = defineProps<{
    /** The profile whose pages are being shown, so the list can mark it. */
    current?: number | null
}>()

const page = usePage()

const profiles = computed(() => page.props.profiles as TargetProfile[])
const deriving = computed(() => page.props.deriving as boolean)
const analyzed = computed(() => page.props.analyzed as boolean)
const derivationError = computed(() => page.props.derivationError as string | null)

// The profiles ARE the navigation here: each one is a place with its own
// criteria and its own searches, not a row in a list. Grouped by type because
// a customer and a partner are read differently: one is a segment, the other
// is a channel.
const GROUPS = [
    { type: 'customer' as const, label: 'Customer' },
    { type: 'partner' as const, label: 'Partner' }
]

const groups = computed(() => GROUPS
    .map(group => ({ ...group, profiles: profiles.value.filter(profile => profile.type === group.type) }))
    .filter(group => group.profiles.length))

const confirmingReplace = ref(false)

// Adding is the default because it is the safe half. Replacing throws away
// profiles that are on screen right now, so it asks first, and even then it
// only removes what the agent wrote: anything the user touched is theirs.
const deriveOptions = computed<DropdownMenuItem[][]>(() => [[
    {
        label: 'Derive more profiles',
        icon: 'i-lucide-plus',
        onSelect: () => derive(false)
    },
    {
        label: 'Replace the derived ones',
        icon: 'i-lucide-refresh-cw',
        color: 'error' as const,
        onSelect: () => {
            confirmingReplace.value = true
        }
    }
]])

function derive (replace: boolean): void {
    confirmingReplace.value = false

    router.post(targets.derive.url(), { replace }, { preserveScroll: true })
}

const derivedCount = computed(() => profiles.value.filter(profile => profile.source === 'agent').length)

const subtitle = computed(() => {
    const customer = profiles.value.filter(profile => profile.type === 'customer').length
    const partner = profiles.value.filter(profile => profile.type === 'partner').length

    return [
        customer ? `${customer} customer` : null,
        partner ? `${partner} partner` : null
    ].filter(Boolean).join(' · ')
})
</script>

<template>
    <AppLayout>
        <div class="flex h-full flex-1">
            <aside class="flex w-72 shrink-0 flex-col overflow-y-auto border-e border-default">
                <div class="p-3 pb-1">
                    <h3 class="font-semibold text-highlighted">
                        Target profiles
                    </h3>
                    <p
                        v-if="subtitle"
                        class="text-xs text-dimmed"
                    >
                        {{ subtitle }}
                    </p>
                </div>

                <div class="flex flex-1 flex-col gap-1 overflow-y-auto p-3 pt-2">
                    <template
                        v-for="group in groups"
                        :key="group.type"
                    >
                        <div class="px-2 pt-2 pb-1 font-mono text-[10px] font-medium tracking-wider text-dimmed uppercase">
                            {{ group.label }}
                        </div>

                        <a
                            v-for="profile in group.profiles"
                            :key="profile.id"
                            href="#"
                            class="relative block rounded-lg p-2.5 text-sm"
                            :class="profile.id === props.current
                                ? 'bg-primary/10 ring ring-primary/25'
                                : 'hover:bg-elevated'"
                            @click.prevent="router.visit(targets.show.url(profile.id))"
                        >
                            <span
                                v-if="profile.id === props.current"
                                class="absolute inset-y-0 left-0 w-0.5 rounded-full bg-primary"
                            />
                            <p
                                class="mb-1.5 line-clamp-2 font-medium"
                                :class="profile.id === props.current ? 'text-highlighted' : 'text-toned'"
                            >
                                {{ profile.name }}
                            </p>
                            <span class="flex items-center gap-2 text-xs">
                                <span
                                    class="inline-flex items-center gap-1.5"
                                    :class="profile.is_active ? 'text-success' : 'text-dimmed'"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="profile.is_active ? 'bg-success' : 'bg-dimmed'"
                                    />
                                    {{ profile.is_active ? 'Searching' : 'Paused' }}
                                </span>
                                <span
                                    v-if="profile.confidence !== null"
                                    class="ms-auto font-mono text-dimmed"
                                >{{ profile.confidence }}% fit</span>
                            </span>
                        </a>
                    </template>

                    <p
                        v-if="!profiles.length"
                        class="p-2 text-sm text-muted"
                    >
                        No profile yet.
                    </p>
                </div>

                <div class="space-y-1 border-t border-default p-3">
                    <UButton
                        icon="i-lucide-plus"
                        color="neutral"
                        variant="ghost"
                        block
                        class="justify-start"
                        label="New profile"
                        :to="targets.create.url()"
                    />

                    <!-- Last in the list, because the ordinary reason to open
                         this section is to read a profile, not to rewrite them
                         all. -->
                    <UDropdownMenu
                        v-if="analyzed"
                        :items="deriveOptions"
                        class="w-full"
                    >
                        <UButton
                            icon="i-lucide-sparkles"
                            color="neutral"
                            variant="ghost"
                            block
                            class="justify-start"
                            :loading="deriving"
                            :disabled="deriving"
                            :label="deriving ? 'Reading your product…' : 'Derive again'"
                        />
                    </UDropdownMenu>
                </div>
            </aside>

            <div class="min-w-0 flex-1 space-y-4 overflow-y-auto p-4">
                <UAlert
                    v-if="derivationError"
                    color="error"
                    variant="subtle"
                    icon="i-lucide-triangle-alert"
                    title="The last derivation failed"
                    :description="derivationError"
                />

                <slot />
            </div>
        </div>

        <UModal
            v-model:open="confirmingReplace"
            title="Replace the derived profiles"
        >
            <template #body>
                <p class="text-sm text-muted">
                    The <strong>{{ derivedCount }}</strong> profile(s) the agent
                    wrote will be deleted and worked out again from your product.
                    Anything you wrote or corrected yourself is kept.
                </p>

                <p class="mt-2 text-sm text-muted">
                    The scores and reasons those profiles gave to companies go
                    with them. The companies themselves stay, and so do their
                    searches. A search without its profile still shows what it
                    did.
                </p>
            </template>

            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        label="Cancel"
                        color="neutral"
                        variant="ghost"
                        @click="confirmingReplace = false"
                    />
                    <UButton
                        label="Replace them"
                        color="error"
                        @click="derive(true)"
                    />
                </div>
            </template>
        </UModal>
    </AppLayout>
</template>
