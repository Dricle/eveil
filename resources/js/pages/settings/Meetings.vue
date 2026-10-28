<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import meetingRoutes from '@/routes/settings/meetings'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'Meetings' }]] })

const props = defineProps<{
    durationMinutes: number
    timezone: string | null
    hours: { day: number, start: string, end: string }[]
    location: string | null
    timezones: string[]
}>()

const page = usePage()
const toast = useToast()

const DAYS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' }
]

const DURATIONS = [15, 20, 30, 45, 60, 90].map(minutes => ({ value: minutes, label: `${minutes} minutes` }))

// Nothing set yet: start from the zone this browser is in, which is right
// far more often than UTC would be.
const form = useForm({
    meeting_duration_minutes: props.durationMinutes,
    meeting_timezone: props.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
    meeting_hours: props.hours.map(window => ({ ...window })),
    meeting_location: props.location ?? ''
})

function addWindow () {
    const last = form.meeting_hours.at(-1)

    form.meeting_hours.push({
        day: last === undefined ? 1 : Math.min(last.day + 1, 7),
        start: last?.start ?? '10:00',
        end: last?.end ?? '12:00'
    })
}

function removeWindow (index: number) {
    form.meeting_hours.splice(index, 1)
}

function save () {
    form.put(meetingRoutes.update.url({ project: page.props.currentProject!.slug }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Meeting hours saved', color: 'success' })
    })
}
</script>

<template>
    <Head title="Meetings" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    When you take calls
                </h2>
                <p class="mt-1 text-sm text-muted">
                    When somebody answers that they are interested, the inbox can
                    draft your reply with three times from these hours, and send
                    the invite for the one they pick. No calendar is connected:
                    leave out the hours you are never free.
                </p>
            </template>

            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <UFormField
                        label="Time zone"
                        :error="form.errors.meeting_timezone"
                    >
                        <USelectMenu
                            v-model="form.meeting_timezone"
                            :items="timezones"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="A call lasts"
                        :error="form.errors.meeting_duration_minutes"
                    >
                        <USelect
                            v-model="form.meeting_duration_minutes"
                            :items="DURATIONS"
                            class="w-full"
                        />
                    </UFormField>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">
                        Hours
                    </p>

                    <p
                        v-if="form.meeting_hours.length === 0"
                        class="text-sm text-muted"
                    >
                        No hours yet, so no times can be offered.
                    </p>

                    <div
                        v-for="(window, index) in form.meeting_hours"
                        :key="index"
                        class="flex flex-wrap items-start gap-2"
                    >
                        <USelect
                            v-model="window.day"
                            :items="DAYS"
                            class="w-36"
                        />
                        <UFormField :error="form.errors[`meeting_hours.${index}.start` as keyof typeof form.errors]">
                            <UInput
                                v-model="window.start"
                                type="time"
                            />
                        </UFormField>
                        <span class="pt-1.5 text-sm text-muted">to</span>
                        <UFormField :error="form.errors[`meeting_hours.${index}.end` as keyof typeof form.errors]">
                            <UInput
                                v-model="window.end"
                                type="time"
                            />
                        </UFormField>
                        <UButton
                            icon="i-lucide-trash-2"
                            color="neutral"
                            variant="ghost"
                            @click="removeWindow(index)"
                        />
                    </div>

                    <UButton
                        icon="i-lucide-plus"
                        color="neutral"
                        variant="outline"
                        size="sm"
                        label="Add hours"
                        @click="addWindow"
                    />
                </div>

                <UFormField
                    label="Where"
                    help="A video link, a phone number or an address. It goes into the invite, never into the mail itself."
                    :error="form.errors.meeting_location"
                >
                    <UInput
                        v-model="form.meeting_location"
                        placeholder="https://meet.example.com/your-room"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <template #footer>
                <UButton
                    label="Save"
                    :loading="form.processing"
                    @click="save"
                />
            </template>
        </UCard>
    </div>
</template>
