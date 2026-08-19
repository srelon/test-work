export interface SortOption<TValue extends string = string> {
    title: string
    selects: readonly [TValue, TValue]
}

export const SORT_FIELD_OPTIONS = {
    user_name: {
        title: 'User Name',
        selects: ['user_name_asc', 'user_name_desc'],
    },
    email: {
        title: 'Email',
        selects: ['email_asc', 'email_desc'],
    },
    date: {
        title: 'Date',
        selects: ['oldest', 'newest'],
    },
} as const satisfies Record<string, SortOption>

export type SortFieldKey = keyof typeof SORT_FIELD_OPTIONS

export type SortKey = (typeof SORT_FIELD_OPTIONS)[SortFieldKey]['selects'][number]

export const SORT_VALUES: SortKey[] = Object.values(SORT_FIELD_OPTIONS).flatMap((option) => [...option.selects])
