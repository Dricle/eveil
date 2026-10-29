<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { PLATFORM_LABEL } from '@/lib/social'
import { relativeUrl } from '@/lib/utils'
import socialPostRoutes from '@/routes/social/posts'
import type { SocialPost } from '@/types'

defineOptions({ layout: AppLayout })

// The video of one draft: the live preview on the left, the back and forth
// with the director on the right, and a box to say what should change at the
// second the video is paused on. The preview runs the same script the
// renderer films, in a sandboxed frame driven by messages.
const props = defineProps<{
    post: SocialPost
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

// A version being made: reread until it shows.
const poll = usePoll(4000, { only: ['post'] }, { autoStart: false })
watch(() => props.post.making_video, making => making ? poll.start() : poll.stop(), { immediate: true })

const frame = ref<HTMLIFrameElement | null>(null)
const duration = ref(0)
const time = ref(0)
const playing = ref(false)
const previewError = ref<string | null>(null)

function send (message: { type: 'play' | 'pause' | 'seek', time?: number }) {
    frame.value?.contentWindow?.postMessage(message, '*')
}

function onMessage (event: MessageEvent) {
    if (event.source !== frame.value?.contentWindow || event.data?.source !== 'eveil-video') {
        return
    }

    const message = event.data as { type: string, time?: number, manifest?: { duration: number }, message?: string }

    if (message.type === 'ready') {
        duration.value = message.manifest?.duration ?? 0
        previewError.value = null
    } else if (message.type === 'time') {
        time.value = message.time ?? 0
    } else if (message.type === 'play') {
        playing.value = true
    } else if (message.type === 'pause') {
        playing.value = false
        time.value = message.time ?? time.value
    } else if (message.type === 'error') {
        previewError.value = message.message ?? 'The preview could not play this version.'
    }
}

onMounted(() => window.addEventListener('message', onMessage))
onBeforeUnmount(() => window.removeEventListener('message', onMessage))

// A new version reloads the frame: start over from the top.
watch(() => props.post.video_preview_url, () => {
    duration.value = 0
    time.value = 0
    playing.value = false
})

function seek (value: number) {
    time.value = value
    send({ type: 'seek', time: value })
}

function clock (seconds: number): string {
    const whole = Math.max(0, seconds)

    return `${Math.floor(whole / 60)}:${(whole % 60).toFixed(1).padStart(4, '0')}`
}

const feedback = ref('')
// On by default once the video has been paused somewhere: "here" is usually
// what the user means.
const atThisMoment = ref(true)
const sending = ref(false)

function submit () {
    sending.value = true
    router.post(socialPostRoutes.video.store.url({ project: slug.value, social_post: props.post.id }), {
        feedback: feedback.value,
        at: props.post.video_preview_url && atThisMoment.value && time.value > 0 ? Number(time.value.toFixed(2)) : null
    }, {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = ''
            toast.add({ title: 'Making a new version', description: 'It plays here in a minute or two.', color: 'success' })
        },
        onError: errors => toast.add({ title: errors.feedback ?? 'Could not ask for a new version', color: 'error' }),
        onFinish: () => sending.value = false
    })
}
</script>

<template>
    <Head :title="`Video for post #${post.id}`" />

    <div class="flex h-full flex-col gap-6 p-6 lg:flex-row">
        <section class="flex min-w-0 flex-1 flex-col items-center gap-4">
            <div class="flex w-full items-center gap-3">
                <UButton
                    icon="i-lucide-arrow-left"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    :label="`${PLATFORM_LABEL[post.platform]} posts`"
                    :to="relativeUrl(socialPostRoutes.index.url({ project: slug, platform: post.platform }))"
                />
                <h2 class="font-medium">
                    Video for post #{{ post.id }}
                </h2>
                <UBadge
                    v-if="post.making_video"
                    color="primary"
                    variant="subtle"
                    label="Making a new version…"
                    class="ml-auto"
                />
            </div>

            <div class="relative aspect-[9/16] h-[70vh] max-w-full overflow-hidden rounded-xl bg-black ring ring-default">
                <iframe
                    v-if="post.video_preview_url"
                    ref="frame"
                    :key="post.video_preview_url"
                    :src="post.video_preview_url"
                    sandbox="allow-scripts"
                    title="Video preview"
                    class="size-full"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-3 p-6 text-center text-sm text-muted"
                >
                    <template v-if="post.making_video">
                        <UIcon
                            name="i-lucide-loader-circle"
                            class="size-5 animate-spin"
                        />
                        Making the first version…
                    </template>
                    <template v-else>
                        No video yet.
                        <UButton
                            icon="i-lucide-clapperboard"
                            label="Make the video"
                            :loading="sending"
                            @click="submit"
                        />
                    </template>
                </div>
            </div>

            <div
                v-if="post.video_preview_url"
                class="flex w-full max-w-md items-center gap-3"
            >
                <UButton
                    :icon="playing ? 'i-lucide-pause' : 'i-lucide-play'"
                    color="neutral"
                    variant="subtle"
                    :aria-label="playing ? 'Pause' : 'Play'"
                    @click="send({ type: playing ? 'pause' : 'play' })"
                />
                <input
                    type="range"
                    min="0"
                    :max="duration"
                    step="0.05"
                    :value="time"
                    aria-label="Position in the video"
                    class="flex-1 accent-(--ui-primary)"
                    @input="seek(Number(($event.target as HTMLInputElement).value))"
                >
                <span class="w-24 text-right font-mono text-xs text-muted">{{ clock(time) }} / {{ clock(duration) }}</span>
            </div>

            <p
                v-if="previewError"
                class="text-sm text-error"
            >
                {{ previewError }}
            </p>
            <p
                v-if="post.last_error"
                class="text-sm text-error"
            >
                {{ post.last_error }}
            </p>
        </section>

        <aside class="flex w-full flex-col gap-4 lg:w-96">
            <div class="space-y-1">
                <h3 class="text-sm font-medium">
                    The post
                </h3>
                <p class="line-clamp-4 whitespace-pre-line text-sm text-muted">
                    {{ post.body }}
                </p>
            </div>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto">
                <h3 class="text-sm font-medium">
                    Versions
                </h3>
                <p
                    v-if="!post.video_revisions.length"
                    class="text-sm text-muted"
                >
                    {{ post.video_brief ?? 'Each version you ask for shows here, with what changed.' }}
                </p>
                <div
                    v-for="(revision, index) in post.video_revisions"
                    :key="revision.created_at"
                    class="space-y-2"
                >
                    <div
                        v-if="revision.feedback"
                        class="ml-8 rounded-lg bg-elevated p-3 text-sm"
                    >
                        <UBadge
                            v-if="revision.at !== null"
                            color="neutral"
                            variant="outline"
                            size="sm"
                            :label="`at ${clock(revision.at)}`"
                            class="mb-1"
                        />
                        <p class="whitespace-pre-line">
                            {{ revision.feedback }}
                        </p>
                    </div>
                    <div class="mr-8 rounded-lg p-3 text-sm ring ring-default">
                        <p class="mb-1 text-xs text-dimmed">
                            Version {{ index + 1 }}
                        </p>
                        {{ revision.summary }}
                    </div>
                </div>
            </div>

            <form
                v-if="post.status === 'draft' && post.video_preview_url"
                class="space-y-2"
                @submit.prevent="submit"
            >
                <UTextarea
                    v-model="feedback"
                    :rows="3"
                    autoresize
                    placeholder="What should change? Pause where it happens, then say it."
                    class="w-full"
                    :disabled="post.making_video"
                />
                <div class="flex items-center gap-3">
                    <UCheckbox
                        v-model="atThisMoment"
                        :label="`About ${clock(time)}`"
                        :disabled="time === 0"
                    />
                    <UButton
                        type="submit"
                        label="Make a new version"
                        icon="i-lucide-sparkles"
                        class="ml-auto"
                        :loading="sending || post.making_video"
                        :disabled="!feedback.trim() || post.making_video"
                    />
                </div>
            </form>
        </aside>
    </div>
</template>
