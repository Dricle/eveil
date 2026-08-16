<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const page = usePage()
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="font-medium">
                {{ page.props.currentProject?.name }}
            </h1>
        </template>

        <Head title="Dashboard" />

        <div class="p-4">
            <p class="text-sm text-muted">
                Nothing here yet — leads, campaigns and suggestions for this
                project land on this page.
            </p>
        </div>
    </AppLayout>
</template>
