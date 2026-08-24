<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthCard from '@/layouts/AuthCard.vue'
</script>

<template>
    <AuthCard title="This invitation is no longer valid">
        <Head title="Invitation expired" />

        <p class="text-sm text-muted">
            The link has expired or was already used. Ask whoever invited you
            to send a new one.
        </p>
    </AuthCard>
</template>
