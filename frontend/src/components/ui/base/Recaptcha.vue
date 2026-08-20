<template>
    <div class="flex flex-col gap-1.5">
        <div ref="widget_container"></div>
        <span v-if="display_error" class="text-xs text-red-500">{{ display_error }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useField } from 'vee-validate'

declare global {
    interface Window {
        grecaptcha?: {
            render: (container: HTMLElement, params: {
                sitekey: string
                callback: (token: string) => void
                'expired-callback'?: () => void
            }) => number
            reset: (widgetId?: number) => void
        }
        __recaptchaOnApiLoad?: () => void
    }
}

interface Props {
    name: string
}

const props = defineProps<Props>()

const { value: field_value, errorMessage, meta: field_meta, setTouched } = useField<string | undefined>(() => props.name)

const display_error = computed(() => (field_meta.touched ? errorMessage.value : undefined))

const widget_container = ref<HTMLElement | null>(null)
let widget_id: number | null = null

let api_ready_promise: Promise<void> | null = null

function load_api(): Promise<void> {
    if (window.grecaptcha?.render) return Promise.resolve()
    if (api_ready_promise) return api_ready_promise

    api_ready_promise = new Promise((resolve) => {
        window.__recaptchaOnApiLoad = () => resolve()

        const script = document.createElement('script')
        script.src = 'https://www.google.com/recaptcha/api.js?onload=__recaptchaOnApiLoad&render=explicit'
        script.async = true
        script.defer = true
        document.head.appendChild(script)
    })

    return api_ready_promise
}

onMounted(async () => {
    await load_api()
    if (! widget_container.value || ! window.grecaptcha) return

    widget_id = window.grecaptcha.render(widget_container.value, {
        sitekey: import.meta.env.VITE_RECAPTCHA_SITE_KEY,
        callback: (token) => {
            field_value.value = token
        },
        'expired-callback': () => {
            field_value.value = undefined
            setTouched(true)
        },
    })
})

onBeforeUnmount(() => {
    if (widget_id !== null && window.grecaptcha) {
        window.grecaptcha.reset(widget_id)
    }
})
</script>
