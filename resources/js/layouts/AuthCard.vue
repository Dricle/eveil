<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppLogo from '@/components/AppLogo.vue'
import SignalOrbit from '@/components/SignalOrbit.vue'

defineProps<{
    title: string
    description?: string
}>()

/**
 * What Eveil does while nobody watches, cycled under the sunrise. Illustrative
 * on purpose: no numbers, so nothing here can go stale or overclaim.
 */
const ACTIVITY = [
    { icon: 'i-lucide-message-circle', text: 'Reading Reddit for people asking for a tool like yours' },
    { icon: 'i-lucide-mail', text: 'Following up with a lead who opened your email twice' },
    { icon: 'i-lucide-linkedin', text: 'Drafting a LinkedIn post from your latest release' },
    { icon: 'i-lucide-file-text', text: 'Writing an article your competitors rank for' },
    { icon: 'i-lucide-radar', text: 'Finding companies that match your best customers' }
]

const activityIndex = ref(0)
let timer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
    timer = setInterval(() => activityIndex.value = (activityIndex.value + 1) % ACTIVITY.length, 3200)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
    <!-- The one full-screen exception to the app's full-width rule: a form
         beside the brand panel, which hides below md. -->
    <div class="flex min-h-screen bg-default">
        <div class="flex w-full flex-col px-6 py-8 sm:px-10 md:w-1/2">
            <div class="auth-rise flex items-center gap-2">
                <AppLogo class="size-6" />
                <span class="text-[15px] font-semibold text-highlighted">Eveil</span>
            </div>

            <div class="mx-auto my-auto w-full max-w-sm py-12">
                <h1
                    class="auth-rise text-2xl font-semibold tracking-tight text-highlighted"
                    style="--delay: 80ms"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="auth-rise mt-1.5 text-sm text-muted"
                    style="--delay: 140ms"
                >
                    {{ description }}
                </p>

                <div
                    class="auth-rise mt-8"
                    style="--delay: 200ms"
                >
                    <slot />
                </div>
            </div>

            <p class="text-xs text-dimmed">
                © {{ new Date().getFullYear() }} Eveil
            </p>
        </div>

        <div class="relative hidden overflow-hidden bg-[oklch(14.2%_0.005_229)] md:block md:w-1/2">
            <SignalOrbit class="absolute inset-0" />

            <!-- Fades the art into the panel so the copy below stays readable. -->
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_30%,oklch(14.2%_0.005_229)_95%)]" />

            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[oklch(14.2%_0.005_229)] from-40% to-transparent p-10 pt-24">
                <p class="max-w-md text-3xl leading-tight font-semibold tracking-tight text-white">
                    Your marketing team, from a
                    <span class="bg-[linear-gradient(90deg,#6fd3ec,#a8b8ff)] bg-clip-text text-transparent">single URL.</span>
                </p>

                <div class="mt-6 flex h-9 items-center">
                    <Transition
                        mode="out-in"
                        enter-active-class="transition duration-500 ease-out"
                        enter-from-class="translate-y-2 opacity-0"
                        leave-active-class="transition duration-300 ease-in"
                        leave-to-class="-translate-y-2 opacity-0"
                    >
                        <div
                            :key="activityIndex"
                            class="inline-flex items-center gap-2.5 rounded-full bg-white/5 py-1.5 pr-4 pl-2 text-sm text-white/70 ring ring-white/10 backdrop-blur"
                        >
                            <span class="relative flex size-5 items-center justify-center rounded-full bg-[#6fd3ec]/15">
                                <span class="absolute inset-0 animate-ping rounded-full bg-[#6fd3ec]/20" />
                                <UIcon
                                    :name="ACTIVITY[activityIndex].icon"
                                    class="size-3 text-[#6fd3ec]"
                                />
                            </span>
                            {{ ACTIVITY[activityIndex].text }}
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-rise {
    animation: auth-rise 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: var(--delay, 0ms);
}

@keyframes auth-rise {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-rise {
        animation: none;
    }
}
</style>
