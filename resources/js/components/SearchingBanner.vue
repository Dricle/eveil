<script setup lang="ts">
import type { Activity } from '@/types'

// Shown on both lead lists. A list that is still filling up must not read as an
// empty result: "your market is small" and "wait thirty seconds" look identical
// otherwise, and only one of them is true.
const props = defineProps<{ activity: Activity }>()

// What it is actually doing, said plainly rather than as one vague "working".
const summary = () => {
    const parts: string[] = []

    if (props.activity.runs > 0) {
        parts.push(props.activity.runs === 1 ? 'one search running' : `${props.activity.runs} searches running`)
    }

    if (props.activity.contact_searches > 0) {
        parts.push(props.activity.contact_searches === 1
            ? 'reading one company for contacts'
            : `reading ${props.activity.contact_searches} companies for contacts`)
    }

    return parts.join(', ')
}
</script>

<template>
    <div
        v-if="activity.searching"
        class="flex flex-wrap items-center gap-3 rounded-lg bg-elevated p-3"
    >
        <span class="flex size-8 shrink-0 items-center justify-center">
            <UIcon
                name="i-lucide-search"
                class="animate-sweep size-5 text-primary"
            />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium">
                Still looking: {{ summary() }}
            </p>
            <p class="text-sm text-muted">
                <template v-if="activity.candidates > 0">
                    {{ activity.candidates }} companies seen, {{ activity.qualified }} kept so far.
                </template>
                <template v-else>
                    Nothing found yet. The first results take a minute or two.
                </template>
                This page refreshes itself.
            </p>
        </div>
    </div>
</template>
