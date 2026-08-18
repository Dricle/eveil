<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import type { StatusOption } from '@/lib/status'

const props = defineProps<{
    status: string
    options: StatusOption[]
    url: string
}>()

// The row is the form: saying where somebody stands is a click in the list, not
// a screen of its own. Nothing is sent when the value did not change — a select
// emits on open-and-pick-the-same-thing too.
function save (status: string) {
    if (status !== props.status) {
        router.put(props.url, { status }, { preserveScroll: true })
    }
}
</script>

<template>
    <!-- `content` gets a floor of its own: the menu otherwise inherits the
         trigger's width, which is one table column, and "Already a client"
         arrived as "Already …". -->
    <USelect
        :model-value="status"
        :items="options"
        variant="ghost"
        size="xs"
        class="w-full"
        :ui="{ content: 'min-w-48' }"
        @update:model-value="save"
    />
</template>
