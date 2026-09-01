<script setup lang="ts">
import { nextTick, ref } from 'vue'

/**
 * A list of full sentences (search queries, trigger signals): one editable
 * row each, not a pill, because a query or a signal is read left to right,
 * not scanned as a label. Each row IS the form field (`name[]`), so nothing
 * extra has to run on submit.
 */
const props = defineProps<{
    modelValue: string[]
    name: string
    variant: 'numbered' | 'bulleted'
    addLabel: string
}>()

const emit = defineEmits<{ 'update:modelValue': [string[]] }>()

const rowRefs = ref<(HTMLInputElement | null)[]>([])

function set (index: number, value: string) {
    const next = [...props.modelValue]
    next[index] = value
    emit('update:modelValue', next)
}

function remove (index: number) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}

async function add () {
    emit('update:modelValue', [...props.modelValue, ''])
    await nextTick()
    rowRefs.value[props.modelValue.length]?.focus()
}

// An empty row left behind by a stray Enter is not a query: it disappears
// on blur rather than being submitted as a blank line.
function pruneIfEmpty (index: number) {
    if (props.modelValue[index] === '') {
        remove(index)
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-lg bg-default ring ring-default">
        <div
            v-for="(item, index) in modelValue"
            :key="index"
            class="group flex items-center gap-2.5 px-2.5 py-1.5"
            :class="index > 0 ? 'border-t border-default' : ''"
        >
            <span
                v-if="variant === 'numbered'"
                class="shrink-0 font-mono text-xs text-dimmed"
            >{{ index + 1 }}</span>
            <span
                v-else
                class="mt-2 size-1 shrink-0 rounded-full bg-primary"
            />

            <input
                :ref="el => { rowRefs[index] = el as HTMLInputElement | null }"
                :name="`${name}[]`"
                type="text"
                :value="item"
                class="min-w-0 flex-1 border-0 bg-transparent py-0.5 text-sm text-toned outline-none"
                :class="variant === 'numbered' ? 'font-mono text-xs' : ''"
                @input="set(index, ($event.target as HTMLInputElement).value)"
                @keydown.enter.prevent="add"
                @blur="pruneIfEmpty(index)"
            >

            <button
                type="button"
                class="shrink-0 rounded p-0.5 text-dimmed opacity-0 group-hover:opacity-100 hover:text-error"
                aria-label="Remove"
                @click="remove(index)"
            >
                <UIcon
                    name="i-lucide-x"
                    class="size-3.5"
                />
            </button>
        </div>

        <button
            type="button"
            class="w-full py-2 text-left text-xs text-dimmed"
            :class="variant === 'numbered' ? 'pl-8 font-mono' : 'pl-7'"
            @click="add"
        >
            {{ addLabel }}
        </button>
    </div>
</template>
