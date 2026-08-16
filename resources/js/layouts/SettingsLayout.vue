<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import knowledgeBase from '@/routes/settings/knowledge-base'
import project from '@/routes/settings/project'

defineProps<{
    title: string
}>()

const page = usePage()

const items = computed<NavigationMenuItem[]>(() =>
    [
        { label: 'Project', icon: 'i-lucide-folder-cog', to: project.edit.url() },
        {
            label: 'Project knowledge',
            icon: 'i-lucide-book-open',
            to: knowledgeBase.edit.url()
        }
    ].map(item => ({ ...item, active: page.url.startsWith(item.to) }))
)
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="font-medium">
                {{ title }}
            </h1>
        </template>

        <div class="flex h-full flex-1">
            <aside class="w-64 shrink-0 border-e border-default p-4">
                <UNavigationMenu
                    :items="items"
                    orientation="vertical"
                    :ui="{ link: 'p-1.5 overflow-hidden' }"
                />
            </aside>

            <div class="min-w-0 flex-1 p-4">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
