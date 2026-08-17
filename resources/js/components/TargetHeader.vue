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
// looking. They stay inside the content area — the bar at the top of the app
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
    <div class="space-y-2">
        <div class="flex items-center gap-2">
            <h2 class="min-w-0 truncate font-medium">
                {{ profile?.name ?? 'New profile' }}
            </h2>

            <UBadge
                v-if="profile?.type === 'partner'"
                color="primary"
                variant="subtle"
                label="Partner"
            />
            <UBadge
                v-if="profile && !profile.is_active"
                color="neutral"
                variant="subtle"
                label="Paused"
            />
            <UBadge
                v-if="profile?.source === 'human'"
                color="neutral"
                variant="subtle"
                icon="i-lucide-pencil"
                label="Edited by you"
            />
        </div>

        <UNavigationMenu
            v-if="profile"
            :items="items"
        />
    </div>
</template>
