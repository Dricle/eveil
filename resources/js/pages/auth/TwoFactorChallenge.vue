<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthCard from '@/layouts/AuthCard.vue';
import { store } from '@/routes/two-factor/login';

const useRecoveryCode = ref(false);
</script>

<template>
    <AuthCard
        title="Two-factor authentication"
        :description="
            useRecoveryCode
                ? 'Enter one of your recovery codes.'
                : 'Enter the code from your authenticator app.'
        "
    >
        <Head title="Two-factor authentication" />

        <Form
            v-bind="store.form()"
            v-slot="{ errors, processing }"
            class="space-y-4"
        >
            <UFormField
                v-if="!useRecoveryCode"
                label="Code"
                name="code"
                :error="errors.code"
            >
                <UInput
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-else
                label="Recovery code"
                name="recovery_code"
                :error="errors.recovery_code"
            >
                <UInput
                    name="recovery_code"
                    autocomplete="one-time-code"
                    autofocus
                    class="w-full"
                />
            </UFormField>

            <UButton
                type="submit"
                :loading="processing"
                block
                label="Continue"
            />

            <UButton
                variant="link"
                block
                :label="
                    useRecoveryCode
                        ? 'Use an authenticator code'
                        : 'Use a recovery code'
                "
                @click="useRecoveryCode = !useRecoveryCode"
            />
        </Form>
    </AuthCard>
</template>
