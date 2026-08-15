<script setup lang="ts">
defineProps<{
    title: string
    description?: string
}>()
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-6">
        <UCard class="w-full max-w-sm">
            <template #header>
                <h1 class="text-lg font-semibold">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-neutral-500"
                >
                    {{ description }}
                </p>
            </template>

            <slot />
        </UCard>
    </div>
</template>
