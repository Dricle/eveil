import type { Auth } from '@/types/auth'
import type { Organization, Project } from '@/types/project'

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string
        [key: string]: string | boolean | undefined
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string
            auth: Auth
            currentProject: Project | null
            projects: Project[]
            organizations: Organization[]
            /** Self-hosted never renders billing UI: nothing gated on it does more than check this. */
            edition: 'self' | 'cloud'
            /** Absent on self-hosted and while no project is selected - cloud, zero credits is `{ balance: 0 }`, not null. */
            wallet: { balance: number } | null
            registerUrl: string | null
            status: string | null
            /** What is missing before the instance can do anything. */
            setup: {
                provider: boolean
                mailbox: boolean
                /** Mailboxes this project cannot send from, with what the server said. */
                broken: { id: number, email: string, status: string, error: string | null }[]
            }
            sidebarOpen: boolean
            [key: string]: unknown
        }
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router
        $page: Page
        $headManager: ReturnType<typeof createHeadManager>
    }
}
