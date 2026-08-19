<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="emit('close')">
            <div class="flex w-full max-w-sm flex-col gap-3 rounded-lg bg-white p-5">
                <h3 class="text-sm font-semibold text-neutral-900">Leaving this site</h3>
                <p class="text-sm text-neutral-600">Are you sure you want to visit this link?</p>
                <p class="break-all rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-500">{{ url }}</p>
                <div class="mt-1 flex justify-end gap-2">
                    <BaseButton variant="outline" @click="emit('close')">No</BaseButton>
                    <BaseButton variant="primary" @click="proceed">Yes</BaseButton>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import BaseButton from '@/components/ui/base/BaseButton.vue'

interface Props {
    url: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
    close: []
}>()

function proceed() {
    window.open(props.url, '_blank', 'noopener,noreferrer')
    emit('close')
}
</script>
