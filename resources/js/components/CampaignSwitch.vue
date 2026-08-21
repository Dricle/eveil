<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import campaignRoutes from '@/routes/campaigns'
import type { CampaignStatus } from '@/types'

// The one switch that makes mail leave, shown wherever a campaign is: in the
// list as much as on its own page. Starting a draft is also what puts people
// into the sequence, so the label says so rather than saying "active".
const props = defineProps<{
    campaign: { id: number, status: CampaignStatus }
}>()

const CONTROLS = {
    draft: { label: 'Start sending', icon: 'i-lucide-play', color: 'primary', next: 'active' },
    paused: { label: 'Resume', icon: 'i-lucide-play', color: 'primary', next: 'active' },
    active: { label: 'Pause', icon: 'i-lucide-pause', color: 'warning', next: 'paused' }
} as const

const control = computed(() => CONTROLS[props.campaign.status as keyof typeof CONTROLS] ?? null)

function flip () {
    if (control.value) {
        router.put(
            campaignRoutes.status.url(props.campaign.id),
            { status: control.value.next },
            { preserveScroll: true }
        )
    }
}
</script>

<template>
    <UButton
        v-if="control"
        :color="control.color"
        :icon="control.icon"
        :label="control.label"
        variant="subtle"
        @click.stop.prevent="flip"
    />

    <!-- Finished or archived: nothing left to switch, and a dead button reads
         as one that failed. -->
    <UBadge
        v-else
        color="neutral"
        variant="subtle"
        :label="campaign.status"
    />
</template>
