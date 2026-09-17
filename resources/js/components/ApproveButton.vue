<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import companyRoutes from '@/routes/companies'

// The go-ahead on one company. Saying yes is also what starts the search for
// people there, so the label promises what actually happens rather than the
// state it writes.
const props = defineProps<{
    company: { id: number, approved: boolean }
    size?: 'xs' | 'sm' | 'md'
}>()

const page = usePage()

function flip () {
    router.put(
        companyRoutes.approval.url({ project: page.props.currentProject!.slug }),
        { companies: [props.company.id], approved: !props.company.approved },
        { preserveScroll: true, preserveState: true }
    )
}
</script>

<template>
    <UButton
        :icon="company.approved ? 'i-lucide-check' : 'i-lucide-thumbs-up'"
        :color="company.approved ? 'success' : 'neutral'"
        :variant="company.approved ? 'subtle' : 'outline'"
        :size="size ?? 'xs'"
        :label="company.approved ? 'Approved' : 'Approve'"
        @click.stop.prevent="flip"
    />
</template>
