<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium text-neutral-700">Image</label>

        <input ref="file_input_ref" type="file" accept="image/png,image/jpeg,image/gif" class="hidden" @change="on_file_selected">

        <div v-if="!model_value" class="flex items-center gap-3">
            <BaseButton variant="outline" @click="file_input_ref?.click()">
                Attach image
            </BaseButton>
            <span class="text-xs text-neutral-500">Will be cropped to {{ target_width }}&times;{{ target_height }}</span>
        </div>

        <div v-else class="relative inline-flex max-w-full" :style="{ width: `${target_width}px` }">
            <button type="button" title="View original image" class="block w-full cursor-zoom-in" @click="lightbox_open = true">
                <img
                    :src="model_value.cropped"
                    alt="Attached image preview"
                    class="w-full rounded-md border border-neutral-300 object-cover"
                    :style="{ aspectRatio: `${target_width} / ${target_height}` }"
                >
            </button>
            <button
                type="button"
                title="Remove image"
                class="absolute -right-2 -top-2 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full bg-neutral-900 text-white shadow hover:bg-neutral-700"
                @click="remove_image"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <ImageLightbox v-if="model_value" v-model:open="lightbox_open" :src="model_value.original" alt="Original attached image" />

        <CropModal
            ref="crop_modal_ref"
            :target-width="target_width"
            :target-height="target_height"
            @cropped="on_cropped"
            @cancelled="reset_file_input"
        />
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from '@/components/ui/base/BaseButton.vue'
import ImageLightbox from '@/components/ui/base/ImageLightbox.vue'
import CropModal from './CropModal.vue'

interface Props {
    targetWidth?: number
    targetHeight?: number
}

const { targetWidth: target_width = 320, targetHeight: target_height = 240 } = defineProps<Props>()

export interface CommentImage {
    original: string
    cropped: string
}

const model_value = defineModel<CommentImage | null>({ default: null })

const file_input_ref = ref<HTMLInputElement | null>(null)
const crop_modal_ref = ref<InstanceType<typeof CropModal> | null>(null)
const lightbox_open = ref(false)
const pending_original = ref<string | null>(null)

function reset_file_input() {
    if (file_input_ref.value) file_input_ref.value.value = ''
}

function on_file_selected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => {
        pending_original.value = reader.result as string
        crop_modal_ref.value?.open(reader.result as string)
    }
    reader.readAsDataURL(file)
}

function on_cropped(data_url: string) {
    if (!pending_original.value) return
    model_value.value = { original: pending_original.value, cropped: data_url }
    pending_original.value = null
    reset_file_input()
}

function remove_image() {
    model_value.value = null
    reset_file_input()
}
</script>
