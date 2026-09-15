<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import linkedinAccountRoutes from '@/routes/linkedin/account'
import linkedinPostRoutes from '@/routes/linkedin/posts'

const props = defineProps<{
    tab: 'posts' | 'account'
}>()

// Two pages under one section: the approval queue and the connected
// account/cadence. Inside the content, not the app bar - `.ai/rules/js.md`'s
// reasoning, same pattern as Targets' Profile/Searches tabs.
const items = computed<NavigationMenuItem[]>(() => [
    {
        label: 'Posts',
        icon: 'i-lucide-inbox',
        to: linkedinPostRoutes.index.url(),
        active: props.tab === 'posts'
    },
    {
        label: 'Account',
        icon: 'i-lucide-plug',
        to: linkedinAccountRoutes.index.url(),
        active: props.tab === 'account'
    }
])
</script>

<template>
    <UNavigationMenu :items="items" />
</template>
