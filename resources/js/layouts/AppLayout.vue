<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { NavigationMenuItem } from '@nuxt/ui';
import { computed, ref } from 'vue';
import { dashboard, logout } from '@/routes';
import { profile } from '@/routes/account';

const page = usePage();

const open = ref(true);

const items = computed<NavigationMenuItem[]>(() => [
    {
        label: 'Dashboard',
        icon: 'i-lucide-house',
        to: dashboard.url(),
        active: page.url === dashboard.url(),
    },
]);

const userMenu = computed(() => [
    [
        { label: 'Account', icon: 'i-lucide-user', to: profile.url() },
        {
            label: 'Log out',
            icon: 'i-lucide-log-out',
            onSelect: () => router.post(logout.url()),
        },
    ],
]);
</script>

<template>
    <!-- h-screen rather than the docs' flex-1: this layout IS the page frame,
         there is no outer flex parent to grow inside of. -->
    <div class="flex h-screen w-full">
        <USidebar
            v-model:open="open"
            collapsible="icon"
            :ui="{ container: 'h-full' }"
        >
            <template #header>
                <span class="truncate font-semibold">{{
                    page.props.name
                }}</span>
            </template>

            <UNavigationMenu
                :items="items"
                orientation="vertical"
                :ui="{ link: 'p-1.5 overflow-hidden' }"
            />

            <template #footer>
                <UDropdownMenu :items="userMenu" class="w-full">
                    <UButton
                        :label="page.props.auth.user.name"
                        icon="i-lucide-user"
                        color="neutral"
                        variant="ghost"
                        block
                        class="justify-start overflow-hidden"
                    />
                </UDropdownMenu>
            </template>
        </USidebar>

        <div class="flex flex-1 flex-col overflow-hidden bg-default">
            <div
                class="flex h-(--ui-header-height) shrink-0 items-center gap-2 border-b border-default px-4"
            >
                <UButton
                    icon="i-lucide-panel-left"
                    color="neutral"
                    variant="ghost"
                    aria-label="Toggle sidebar"
                    @click="open = !open"
                />

                <slot name="header" />
            </div>

            <div class="flex-1 overflow-y-auto">
                <slot />
            </div>
        </div>
    </div>
</template>
