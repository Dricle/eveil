<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'
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
// criteria and its own searches, not a row in a list.
const items = computed<NavigationMenuItem[]>(() => profiles.value.map(profile => ({
    label: profile.name,
    icon: profile.type === 'partner' ? 'i-lucide-handshake' : 'i-lucide-crosshair',
    to: targets.show.url(profile.id),
    active: profile.id === props.current
})))

const confirmingReplace = ref(false)

// Adding is the default because it is the safe half. Replacing throws away
// profiles that are on screen right now, so it asks first — and even then it
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
</script>

<template>
    <AppLayout>
        <div class="flex h-full flex-1">
            <aside class="flex w-64 shrink-0 flex-col gap-2 border-e border-default p-4">
                <UNavigationMenu
                    :items="items"
                    orientation="vertical"
                    :ui="{ link: 'p-1.5 overflow-hidden' }"
                />

                <div class="mt-auto space-y-1">
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
                    searches — a search without its profile still shows what it
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
