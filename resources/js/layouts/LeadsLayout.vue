<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import companies from '@/routes/companies'
import contacts from '@/routes/contacts'

const page = usePage()

// The companies found, and the people at them. One is the other's parent, and
// you move between them constantly.
const items = computed<NavigationMenuItem[]>(() =>
    [
        { label: 'Companies', icon: 'i-lucide-building-2', to: companies.index.url() },
        { label: 'Contacts', icon: 'i-lucide-users', to: contacts.index.url() }
    ].map(item => ({ ...item, active: page.url.startsWith(item.to) }))
)
</script>

<template>
    <AppLayout>
        <div class="space-y-4 p-4">
            <!-- Section navigation lives in the section, not in the app bar:
                 that bar belongs to the app. -->
            <UNavigationMenu :items="items" />

            <slot />
        </div>
    </AppLayout>
</template>
