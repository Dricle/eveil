<script setup lang="ts">
import { usePage, usePoll } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const page = usePage()

const jobs = computed(() => page.props.chatJobs ?? [])

// Same house convention as every other in-flight job screen (`discovery/Run.vue`
// and friends): a partial-reload poll, started/stopped as there is/isn't
// anything to watch. Lives here rather than one of those pages because the
// panel is the one place that should say so regardless of which page is open.
const poll = usePoll(2000, { only: ['chatJobs'] }, { autoStart: jobs.value.length > 0 })

watch(jobs, (value) => {
    if (value.length > 0) {
        poll.start()
    } else {
        poll.stop()
    }
})

const labels: Record<string, string> = {
    pending: 'queued',
    planning: 'planning',
    running: 'running'
}
</script>

<template>
    <div
        v-if="jobs.length > 0"
        class="flex flex-col gap-1 border-b border-default px-4 py-2"
    >
        <div
            v-for="job in jobs"
            :key="`${job.type}-${job.id}`"
            class="flex items-center gap-1.5 text-xs text-muted"
        >
            <span class="size-1.5 shrink-0 animate-pulse rounded-full bg-primary" />
            Discovery run #{{ job.id }} {{ labels[job.status] ?? job.status }}
        </div>
    </div>
</template>
