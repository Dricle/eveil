<script setup lang="ts">
defineProps<{ open: boolean }>()
defineEmits<{ 'update:open': [value: boolean] }>()
</script>

<template>
    <UButton
        icon="i-lucide-sparkles"
        color="primary"
        :variant="open ? 'solid' : 'subtle'"
        size="sm"
        aria-label="Toggle Evie chat"
        class="relative"
        @click="$emit('update:open', !open)"
    >
        Chat with Evie
    </UButton>
</template>
