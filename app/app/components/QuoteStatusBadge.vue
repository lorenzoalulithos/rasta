<script setup lang="ts">
import type { QuoteStatus } from '~/types/api'

const props = defineProps<{ status: QuoteStatus }>()

const status = computed(() => QUOTE_STATUSES[props.status])
</script>

<template>
    <UBadge
        :label="status.label"
        :icon="status.icon"
        :color="status.color"
        :variant="status.variant"
        size="md"
        class="shrink-0 rounded-full px-2.5 font-bold"
    />
</template>
