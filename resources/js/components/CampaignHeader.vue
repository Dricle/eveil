<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed, ref } from 'vue'
import CampaignSwitch from '@/components/CampaignSwitch.vue'
import { relativeUrl } from '@/lib/utils'
import campaignRoutes from '@/routes/campaigns'
import type { CampaignStatus } from '@/types'

const page = usePage()

// One campaign, two pages: the mails it sends, and what is happening to the
// people in it. The tabs stay inside the content area, because the bar at the
// top of the app belongs to the app rather than to whichever section is open.
const props = defineProps<{
    campaign: {
        id: number
        name: string
        status: CampaignStatus
        target_profile: { id: number | null, name: string | null, type: string | null } | null
    }
    tab: 'sequence' | 'delivery'
}>()

const name = ref(props.campaign.name)

const items = computed<NavigationMenuItem[]>(() => [
    {
        label: 'Sequence',
        icon: 'i-lucide-file-text',
        to: relativeUrl(campaignRoutes.show.url({ project: page.props.currentProject!.slug, campaign: props.campaign.id })),
        active: props.tab === 'sequence'
    },
    {
        label: 'Delivery',
        icon: 'i-lucide-send',
        to: relativeUrl(campaignRoutes.delivery.url({ project: page.props.currentProject!.slug, campaign: props.campaign.id })),
        active: props.tab === 'delivery'
    }
])
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-3">
            <UInput
                v-model="name"
                class="w-80"
                @blur="router.put(campaignRoutes.update.url({ project: page.props.currentProject!.slug, campaign: campaign.id }), { name }, { preserveScroll: true })"
            />

            <CampaignSwitch :campaign="campaign" />

            <UBadge
                v-if="campaign.target_profile?.name"
                color="neutral"
                variant="subtle"
                :label="campaign.target_profile.name"
                :icon="campaign.target_profile.type === 'partner' ? 'i-lucide-handshake' : 'i-lucide-crosshair'"
            />

            <UButton
                class="ms-auto"
                color="error"
                variant="ghost"
                icon="i-lucide-trash-2"
                label="Delete"
                @click="router.delete(campaignRoutes.destroy.url({ project: page.props.currentProject!.slug, campaign: campaign.id }))"
            />
        </div>

        <UNavigationMenu :items="items" />
    </div>
</template>
