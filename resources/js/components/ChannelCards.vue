<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import channelRoutes from '@/routes/settings/channels'
import type { ChannelToggle } from '@/types'

// Every way of reaching buyers, one card each, with why the site analysis
// thinks it fits or not. Clicking a card switches it and saves on the spot:
// the card repainting is the confirmation, so there is no save button and no
// toast.
defineProps<{ channels: ChannelToggle[] }>()

const page = usePage()
const toggling = ref<string | null>(null)

function toggle (channel: ChannelToggle) {
    if (toggling.value !== null) {
        return
    }

    toggling.value = channel.key
    router.put(channelRoutes.update.url({ project: page.props.currentProject!.slug, channel: channel.key }), { enabled: !channel.enabled }, {
        preserveScroll: true,
        onFinish: () => toggling.value = null
    })
}
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <button
            v-for="channel in channels"
            :key="channel.key"
            type="button"
            :aria-pressed="channel.enabled"
            class="flex min-h-48 flex-col gap-3 rounded-lg p-4 text-left ring transition hover:bg-elevated/60"
            :class="channel.enabled ? 'bg-primary/5 ring-primary' : 'ring-default'"
            @click="toggle(channel)"
        >
            <div class="flex items-start justify-between gap-2">
                <div
                    class="flex size-12 items-center justify-center rounded-lg"
                    :class="channel.enabled ? 'bg-primary text-inverted' : 'bg-elevated text-muted'"
                >
                    <UIcon
                        :name="channel.icon"
                        class="size-6"
                    />
                </div>

                <!-- Display only: the whole card is the control. -->
                <USwitch
                    :model-value="channel.enabled"
                    :loading="toggling === channel.key"
                    tabindex="-1"
                    class="pointer-events-none"
                />
            </div>

            <div class="space-y-1">
                <p class="font-medium">
                    {{ channel.label }}
                </p>
                <UBadge
                    v-if="channel.reason"
                    :color="channel.recommended ? 'success' : 'neutral'"
                    variant="subtle"
                    size="sm"
                    :icon="channel.recommended ? 'i-lucide-thumbs-up' : 'i-lucide-minus'"
                    :label="channel.recommended ? 'Recommended' : 'Not recommended'"
                />
            </div>

            <p
                v-if="channel.reason"
                class="text-sm text-muted"
            >
                {{ channel.reason }}
            </p>

            <p class="mt-auto text-xs text-dimmed">
                <template v-if="channel.key === 'email' && !channel.enabled">
                    Lead search paused. Target profiles stay.
                </template>
                <template v-else-if="channel.enabled && channel.requirement">
                    {{ channel.requirement }}
                </template>
            </p>
        </button>
    </div>
</template>
