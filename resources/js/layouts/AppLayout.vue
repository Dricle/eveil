<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard, logout } from '@/routes';
import { profile } from '@/routes/account';

const page = usePage();

const navigation = computed(() => [
    [
        {
            label: 'Dashboard',
            icon: 'i-lucide-layout-dashboard',
            to: dashboard.url(),
            active: page.url === dashboard.url(),
        },
    ],
]);

const userMenu = computed(() => [
    [
        {
            label: 'Account',
            icon: 'i-lucide-user',
            to: profile.url(),
        },
        {
            label: 'Log out',
            icon: 'i-lucide-log-out',
            onSelect: () => router.post(logout.url()),
        },
    ],
]);
</script>

<template>
    <UDashboardGroup>
        <UDashboardSidebar collapsible resizable>
            <template #header="{ collapsed }">
                <span v-if="!collapsed" class="font-semibold">{{
                    page.props.name
                }}</span>
            </template>

            <template #default="{ collapsed }">
                <UNavigationMenu
                    :collapsed="collapsed"
                    :items="navigation"
                    orientation="vertical"
                />
            </template>

            <template #footer="{ collapsed }">
                <UDropdownMenu :items="userMenu" class="w-full">
                    <UButton
                        :label="
                            collapsed ? undefined : page.props.auth.user.name
                        "
                        icon="i-lucide-user"
                        color="neutral"
                        variant="ghost"
                        block
                        :square="collapsed"
                    />
                </UDropdownMenu>
            </template>
        </UDashboardSidebar>

        <slot />
    </UDashboardGroup>
</template>
