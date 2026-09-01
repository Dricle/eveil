<script setup lang="ts">
import { ref } from 'vue'

/**
 * A short list of labels (sectors, geography, job titles, technologies):
 * shown as pills, typed one at a time. Deliberately simpler than a full tags
 * input (click to edit, drag to reorder): type and press Enter to add, click
 * a pill to remove it. The array travels as `name[]` hidden-free native
 * inputs, so it reaches the server as a real array with no JS on submit.
 */
const props = defineProps<{
    modelValue: string[]
    name: string
    placeholder?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [string[]] }>()

const draft = ref('')

function add () {
    const value = draft.value.trim()

    if (value && !props.modelValue.includes(value)) {
        emit('update:modelValue', [...props.modelValue, value])
    }

    draft.value = ''
}

function remove (index: number) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}

function backspace () {
    if (draft.value === '' && props.modelValue.length) {
        remove(props.modelValue.length - 1)
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5 rounded-lg bg-default p-2 ring ring-default">
        <input
            v-for="(item, index) in modelValue"
            :key="index"
            type="hidden"
            :name="`${name}[]`"
            :value="item"
        >

        <button
            v-for="(item, index) in modelValue"
            :key="`chip-${index}`"
            type="button"
            class="rounded-md bg-elevated px-2 py-0.5 text-xs text-toned"
            title="Click to remove"
            @click="remove(index)"
        >
            {{ item }}
        </button>

        <input
            v-model="draft"
            type="text"
            :placeholder="placeholder ?? 'Add…'"
            class="min-w-24 flex-1 border-0 bg-transparent px-1 py-0.5 text-xs text-dimmed outline-none placeholder:text-dimmed"
            @keydown.enter.prevent="add"
            @keydown.tab="add"
            @keydown.delete="backspace"
            @blur="add"
        >
    </div>
</template>
