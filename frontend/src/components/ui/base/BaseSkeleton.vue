<template>
    <span
        class="block animate-pulse bg-neutral-200"
        :style="{
            width,
            height,
            borderRadius: circle ? '50%' : radius,
        }"
    ></span>
</template>

<script setup lang="ts">
interface Props {
    width?: string
    height?: string
    radius?: string
    circle?: boolean
}

withDefaults(defineProps<Props>(), {
    width: '100%',
    height: '100%',
    radius: '6px',
    circle: false,
})
</script>
