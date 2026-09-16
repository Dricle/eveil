<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import { relativeUrl } from '@/lib/utils'
import { deleteMethod, password, profile, twoFactor } from '@/routes/account'

defineProps<{
    title: string
}>()

const page = usePage()

const items = computed<NavigationMenuItem[]>(() =>
    [
        { label: 'Profile', icon: 'i-lucide-user', to: relativeUrl(profile.url()) },
        { label: 'Password', icon: 'i-lucide-key-round', to: relativeUrl(password.url()) },
        {
            label: 'Two-factor authentication',
            icon: 'i-lucide-shield-check',
            to: relativeUrl(twoFactor.url())
        },
        {
            label: 'Delete account',
            icon: 'i-lucide-trash-2',
            to: relativeUrl(deleteMethod.url())
        }
    ].map(item => ({ ...item, active: page.url.startsWith(item.to) }))
)
</script>

<template>
    <div class="flex h-full flex-1">
        <aside class="w-64 shrink-0 border-e border-default p-4">
            <UNavigationMenu
                :items="items"
                orientation="vertical"
                :ui="{ link: 'p-1.5 overflow-hidden' }"
            />
        </aside>

        <div class="min-w-0 flex-1 space-y-4 overflow-y-auto p-4">
            <h2 class="font-medium">
                {{ title }}
            </h2>

            <slot />
        </div>
    </div>
</template>
