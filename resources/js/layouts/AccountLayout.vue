<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { deleteMethod, password, profile, twoFactor } from '@/routes/account';

defineProps<{
    title: string;
}>();

const page = usePage();

const items = computed(() => [
    [
        { label: 'Profile', icon: 'i-lucide-user', to: profile.url() },
        { label: 'Password', icon: 'i-lucide-key-round', to: password.url() },
        {
            label: 'Two-factor authentication',
            icon: 'i-lucide-shield-check',
            to: twoFactor.url(),
        },
        {
            label: 'Delete account',
            icon: 'i-lucide-trash-2',
            to: deleteMethod.url(),
        },
    ].map((item) => ({ ...item, active: page.url.startsWith(item.to) })),
]);
</script>

<template>
    <AppLayout>
        <UDashboardPanel
            id="account-nav"
            :default-size="20"
            :min-size="15"
            :max-size="30"
            resizable
        >
            <template #header>
                <UDashboardNavbar title="Account" />
            </template>

            <template #body>
                <UNavigationMenu :items="items" orientation="vertical" />
            </template>
        </UDashboardPanel>

        <UDashboardPanel id="account-content">
            <template #header>
                <UDashboardNavbar :title="title" />
            </template>

            <template #body>
                <slot />
            </template>
        </UDashboardPanel>
    </AppLayout>
</template>
