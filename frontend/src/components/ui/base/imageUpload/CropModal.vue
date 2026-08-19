<template>
    <div v-if="crop_source" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
        <div class="flex w-full max-w-lg flex-col gap-4 rounded-lg bg-white p-4">
            <p class="text-sm font-medium text-neutral-700">Select the area to crop ({{ target_width }}&times;{{ target_height }})</p>

            <Cropper
                ref="cropper_ref"
                class="h-72 w-full bg-neutral-100"
                :src="crop_source"
                :stencil-props="{ aspectRatio: target_width / target_height }"
            />

            <div class="flex justify-end gap-2">
                <BaseButton variant="outline" @click="cancel">Cancel</BaseButton>
                <BaseButton variant="primary" @click="confirm">Crop &amp; attach</BaseButton>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'
import BaseButton from '@/components/ui/base/BaseButton.vue'

interface Props {
    targetWidth: number
    targetHeight: number
}

const { targetWidth: target_width, targetHeight: target_height } = defineProps<Props>()

const emit = defineEmits<{
    cropped: [data_url: string]
    cancelled: []
}>()

const cropper_ref = ref<InstanceType<typeof Cropper> | null>(null)
const crop_source = ref<string | null>(null)

function open(source: string) {
    crop_source.value = source
}

function cancel() {
    crop_source.value = null
    emit('cancelled')
}

function confirm() {
    const result = cropper_ref.value?.getResult()
    if (!result?.canvas) return

    const output = document.createElement('canvas')
    output.width = target_width
    output.height = target_height

    const ctx = output.getContext('2d')
    if (!ctx) return
    ctx.drawImage(result.canvas, 0, 0, target_width, target_height)

    emit('cropped', output.toDataURL('image/jpeg', 0.9))
    crop_source.value = null
}

defineExpose({ open })
</script>
