import axios from 'axios'
import { useToast } from 'vue-toastification'
import router from '@/router'

declare module 'axios' {
    export interface AxiosRequestConfig {
        silent?: boolean
    }
}

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? '/api',
    headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

function extract_error_message(error: unknown): string | null {
    if (axios.isAxiosError(error)) {
        const errors = error.response?.data?.errors

        if (typeof errors === 'string') {
            return errors
        }

        if (errors && typeof errors === 'object') {
            return null
        }

        if (error.response?.data?.message) {
            return error.response.data.message
        }

        if (!error.response) {
            return 'Network error. Please try again.'
        }
    }

    return 'Something went wrong. Please try again.'
}

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 404) {
            router.push({ name: 'error_404' })
        } else if (!error.config?.silent) {
            const message = extract_error_message(error)
            if (message) {
                useToast().error(message)
            }
        }

        return Promise.reject(error)
    },
)

export default api
