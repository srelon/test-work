import axios from 'axios'
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

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const response = error.response

        if (!response) {
            console.error(error)
            return Promise.reject(error)
        }

        if (response.status === 401) {
            console.error('Validation errors', response.data)
        } else if (response.status === 404) {
            router.push({ name: 'error_404' })
        } else if (response.data?.errors) {
            console.error('Validation errors', response.data.errors)
        } else if (response.data?.message) {
            console.error('Validation errors', response.data.message)
        }

        return Promise.reject(error)
    },
)

export default api
