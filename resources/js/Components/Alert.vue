<script setup>
defineProps({
    type: { type: String, default: 'info' },
});
</script>

<template>
    <div class="alert" :class="`alert-${type}`" role="alert">
        <slot />
    </div>
</template>
