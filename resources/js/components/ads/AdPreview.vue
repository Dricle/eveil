<script setup lang="ts">
import { computed } from 'vue'
import type { Ad } from '@/types'

const props = defineProps<{
    ad: Ad
}>()

const host = computed(() => {
    try {
        return new URL(props.ad.final_url).host
    } catch {
        return props.ad.final_url
    }
})
</script>

<template>
    <!-- Laid out like the ad itself, so a person approving reads what people will see. -->
    <div class="overflow-hidden rounded-lg text-sm ring ring-default">
        <img
            v-if="ad.image_url"
            :src="ad.image_url"
            :alt="ad.angle"
            class="aspect-square w-full object-cover"
        >
        <div class="space-y-1 p-3">
            <p class="truncate text-xs text-muted">
                {{ host }}
            </p>
            <p class="font-medium text-primary">
                {{ ad.headlines.join(' | ') }}
            </p>
            <p class="text-default">
                {{ ad.descriptions.join(' ') }}
            </p>
            <p class="pt-1 text-xs text-dimmed">
                <UIcon
                    name="i-lucide-lightbulb"
                    class="mr-1 inline size-3 align-[-1px]"
                />{{ ad.angle }}
            </p>
        </div>
    </div>
</template>
