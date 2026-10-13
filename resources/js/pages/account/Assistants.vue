<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AccountLayout from '@/layouts/AccountLayout.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import assistantRoutes from '@/routes/account/assistants'

defineOptions({ layout: [AppLayout, [AccountLayout, { title: 'Connected assistants' }]] })

defineProps<{
    assistants: { id: string, name: string, connected_at: string }[]
}>()

const toast = useToast()
const disconnecting = ref<string | null>(null)

function disconnect (id: string) {
    disconnecting.value = id
    router.delete(assistantRoutes.destroy.url({ client: id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Disconnected', color: 'success' }),
        onFinish: () => { disconnecting.value = null }
    })
}
</script>

<template>
    <Head title="Connected assistants" />

    <UCard>
        <template #header>
            <h2 class="font-medium">
                Connected assistants
            </h2>
            <p class="mt-1 text-sm text-neutral-500">
                AI assistants you connected to Eveil by signing in. They act as you, in every project you can open.
            </p>
        </template>

        <div class="space-y-2">
            <div
                v-for="assistant in assistants"
                :key="assistant.id"
                class="flex items-center justify-between gap-4 rounded-lg p-4 ring ring-default"
            >
                <div class="min-w-0">
                    <div class="font-medium">
                        {{ assistant.name }}
                    </div>
                    <div class="text-sm text-muted">
                        Connected on {{ new Date(assistant.connected_at).toLocaleDateString() }}
                    </div>
                </div>
                <UButton
                    color="error"
                    variant="ghost"
                    label="Disconnect"
                    :loading="disconnecting === assistant.id"
                    @click="disconnect(assistant.id)"
                />
            </div>

            <p
                v-if="assistants.length === 0"
                class="text-sm text-muted"
            >
                None connected.
            </p>
        </div>
    </UCard>
</template>
