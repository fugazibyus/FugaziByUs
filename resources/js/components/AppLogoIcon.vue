<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>

<template>
    <!-- FugaziByUs mark: an "F" built from three stacked bars, hinting at a pipeline. -->
    <svg
        viewBox="0 0 32 32"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        v-bind="$attrs"
    >
        <rect width="32" height="32" rx="9" fill="url(#fbu-mark)" />
        <rect x="9" y="8" width="15" height="4" rx="2" fill="white" />
        <rect
            x="9"
            y="14"
            width="10"
            height="4"
            rx="2"
            fill="white"
            fill-opacity="0.85"
        />
        <rect
            x="9"
            y="20"
            width="4"
            height="4"
            rx="2"
            fill="white"
            fill-opacity="0.7"
        />
        <defs>
            <linearGradient
                id="fbu-mark"
                x1="0"
                y1="0"
                x2="32"
                y2="32"
                gradientUnits="userSpaceOnUse"
            >
                <stop stop-color="#8b5cf6" />
                <stop offset="1" stop-color="#6d28d9" />
            </linearGradient>
        </defs>
    </svg>
</template>
