import { router } from '@inertiajs/vue3'
import { reactive, ref, watch } from 'vue'

export type TableFilters = {
    search?: string | null
    filter?: Record<string, string>
    sort?: string | null
    direction?: string | null
}

/**
 * The query behind a list screen: free search, one filter per column, and one
 * sorted column.
 *
 * All three are server state, not browser state. The lists are paginated, so a
 * column sorted in the browser would only sort the twenty-five rows on screen
 * and a filter would hide rows from the page rather than from the result.
 */
export function useTableQuery (
    url: string,
    initial: TableFilters,
    only: string[],
    extra: () => Record<string, unknown> = () => ({})
) {
    const search = ref(initial.search ?? '')
    const filter = reactive<Record<string, string>>({ ...initial.filter })
    const sort = ref(initial.sort ?? null)
    const direction = ref(initial.direction ?? null)

    function reload (overrides: Record<string, unknown> = {}) {
        router.get(url, {
            ...extra(),
            search: search.value || undefined,
            filter: Object.fromEntries(Object.entries(filter).filter(([, value]) => value !== '')),
            sort: sort.value ?? undefined,
            direction: direction.value ?? undefined,
            ...overrides
        }, { preserveState: true, replace: true, only })
    }

    // Typing is not a request per keystroke, and the pause has to be long
    // enough to finish a word.
    let timer: ReturnType<typeof setTimeout> | undefined

    watch([search, filter], () => {
        clearTimeout(timer)
        timer = setTimeout(reload, 350)
    })

    /** Ascending, then descending, then back to whatever the list orders by. */
    function toggleSort (column: string) {
        if (sort.value !== column) {
            sort.value = column
            direction.value = 'asc'
        } else if (direction.value === 'asc') {
            direction.value = 'desc'
        } else {
            sort.value = null
            direction.value = null
        }

        reload()
    }

    function sortIcon (column: string) {
        if (sort.value !== column) {
            return 'i-lucide-chevrons-up-down'
        }

        return direction.value === 'asc' ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'
    }

    /** How many boxes are narrowing the list right now, free search included. */
    function activeCount () {
        return Object.values(filter).filter(value => value !== '').length + (search.value === '' ? 0 : 1)
    }

    function clear () {
        search.value = ''
        Object.keys(filter).forEach(key => filter[key] = '')
    }

    return { search, filter, sort, direction, reload, toggleSort, sortIcon, activeCount, clear }
}
