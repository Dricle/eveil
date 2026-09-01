<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import targets from '@/routes/targets'
import type { TargetProfile } from '@/types'

const props = defineProps<{
    profile: TargetProfile | null
    tab: 'profile' | 'searches'
}>()

// Two pages about one profile: what we are looking for, and what came of
// looking. They stay inside the content area, because the bar at the top of the app
// belongs to the app, not to whichever section is open.
const items = computed<NavigationMenuItem[]>(() => props.profile === null
    ? []
    : [
            {
                label: 'Profile',
                icon: 'i-lucide-file-text',
                to: targets.show.url(props.profile.id),
                active: props.tab === 'profile'
            },
            {
                label: 'Searches',
                icon: 'i-lucide-radar',
                to: targets.searches.url(props.profile.id),
                active: props.tab === 'searches'
            }
        ])
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="mb-1.5 flex items-center gap-2">
                    <UBadge
                        :color="profile?.type === 'partner' ? 'primary' : 'neutral'"
                        variant="subtle"
                        :label="profile?.type === 'partner' ? 'Partner' : 'Customer'"
                    />
                    <UBadge
                        v-if="profile && !profile.is_active"
                        color="neutral"
                        variant="subtle"
                        label="Paused"
                    />
                    <span
                        v-if="profile?.source === 'agent'"
                        class="inline-flex items-center gap-1.5 text-xs text-dimmed"
                    >
                        <UIcon
                            name="i-lucide-sparkles"
                            class="size-3"
                        />
                        Derived by an agent
                    </span>
                    <UBadge
                        v-else-if="profile?.source === 'human'"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-pencil"
                        label="Edited by you"
                    />
                </div>
                <h2 class="truncate text-lg font-semibold text-highlighted">
                    {{ profile?.name ?? 'New profile' }}
                </h2>
            </div>

            <div
                v-if="profile?.confidence !== null && profile?.confidence !== undefined"
                class="shrink-0 pt-0.5 text-right"
            >
                <div
                    class="font-mono text-xl leading-none"
                    :class="(profile?.confidence ?? 0) >= 70 ? 'text-success' : (profile?.confidence ?? 0) >= 40 ? 'text-warning' : 'text-dimmed'"
                >
                    {{ profile?.confidence }}%
                </div>
                <div class="mt-0.5 text-xs text-dimmed">
                    confidence
                </div>
            </div>
        </div>

        <UNavigationMenu
            v-if="profile"
            :items="items"
        />
    </div>
</template>
