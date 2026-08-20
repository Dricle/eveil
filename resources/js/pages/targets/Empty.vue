<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import TargetsLayout from '@/layouts/TargetsLayout.vue'
import targets from '@/routes/targets'

const page = usePage()

const deriving = computed(() => page.props.deriving as boolean)
const analyzed = computed(() => page.props.analyzed as boolean)

// A full visit rather than a partial one: the moment the first profile exists,
// this page redirects to it, and only the server can decide that.
const poll = usePoll(3000, {}, { autoStart: deriving.value })

watch(deriving, busy => busy ? poll.start() : poll.stop())
</script>

<template>
    <TargetsLayout>
        <Head title="Targets" />

        <div class="flex h-full items-center justify-center">
            <div class="max-w-md space-y-4 text-center">
                <UIcon
                    name="i-lucide-crosshair"
                    class="size-8 text-dimmed"
                />

                <h2 class="font-medium">
                    Nobody to go after yet
                </h2>

                <p class="text-sm text-muted">
                    A target profile says who to look for, and the agent works it
                    out from your product. You should not have to fill in a
                    targeting form.
                </p>

                <div class="flex justify-center gap-2">
                    <UButton
                        v-if="analyzed"
                        icon="i-lucide-sparkles"
                        :loading="deriving"
                        :disabled="deriving"
                        :label="deriving ? 'Reading your product…' : 'Derive from my product'"
                        @click="router.post(targets.derive.url())"
                    />

                    <UButton
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-plus"
                        label="Write one myself"
                        :to="targets.create.url()"
                    />
                </div>

                <p
                    v-if="!analyzed"
                    class="text-sm text-muted"
                >
                    The site has not been read yet, so there is nothing to derive
                    from. It can still be written by hand.
                </p>
            </div>
        </div>
    </TargetsLayout>
</template>
