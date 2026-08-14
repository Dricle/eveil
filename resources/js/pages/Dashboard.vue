<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <UDashboardPanel id="dashboard">
            <template #header>
                <UDashboardNavbar title="Dashboard" />
            </template>

            <template #body>
                <Head title="Dashboard" />

                <p class="text-sm text-neutral-500">
                    Nothing here yet — projects land next.
                </p>
            </template>
        </UDashboardPanel>
    </AppLayout>
</template>
