<script setup lang="ts">
import type { QuoteLine } from '~/types/api'

// Card di una riga (docs/flows.md §2.3): swipe a sinistra per eliminare su touch, cestino su desktop
const props = defineProps<{
    line: QuoteLine
    editable: boolean
}>()

const emit = defineEmits<{
    open: []
    remove: []
}>()

const REVEAL = 104

const offset = ref(0)
const dragging = ref(false)
let startX = 0
let startY = 0
let startOffset = 0
let horizontal: boolean | null = null

function onPointerDown(event: PointerEvent): void {
    if (!props.editable || event.pointerType !== 'touch') {
        return
    }

    startX = event.clientX
    startY = event.clientY
    startOffset = offset.value
    horizontal = null
    dragging.value = true
}

function onPointerMove(event: PointerEvent): void {
    if (!dragging.value) {
        return
    }

    const dx = event.clientX - startX
    const dy = event.clientY - startY

    // Si decide al primo movimento se è uno swipe o lo scroll della pagina
    if (horizontal === null && Math.abs(dx) + Math.abs(dy) > 8) {
        horizontal = Math.abs(dx) > Math.abs(dy)
    }

    if (horizontal) {
        offset.value = Math.min(0, Math.max(-REVEAL - 24, startOffset + dx))
    }
}

function onPointerUp(): void {
    if (!dragging.value) {
        return
    }

    dragging.value = false
    offset.value = offset.value < -REVEAL / 2 ? -REVEAL : 0
}

function onClick(): void {
    if (offset.value !== 0) {
        offset.value = 0

        return
    }

    emit('open')
}

const unit = computed(() => JOB_UNITS[props.line.unit].short)

// "2 mani · Tempera Wallcream 3 × bidone 14 lt": solo i materiali spuntati
const details = computed(() => {
    const parts: string[] = []

    if (props.line.coats) {
        parts.push(props.line.coats === 1 ? '1 mano' : `${props.line.coats} mani`)
    }

    for (const material of props.line.materials ?? []) {
        if (material.selected && material.material) {
            parts.push(`${material.material.name} ${formatQuantity(material.required_packages)} × ${material.material.package_label}`)
        }
    }

    return parts
})
</script>

<template>
    <div class="relative overflow-hidden rounded-2xl">
        <button
            v-if="editable"
            v-show="offset < 0"
            type="button"
            class="absolute inset-y-0 right-0 flex w-32 bg-error pl-6 flex-col items-center justify-center gap-1 text-sm font-bold text-white"
            :tabindex="offset === 0 ? -1 : 0"
            @click="emit('remove')"
        >
            <UIcon
                name="i-lucide-trash-2"
                class="size-6"
            />
            Elimina
        </button>

        <div
            class="relative flex touch-pan-y items-stretch bg-white"
            :class="dragging ? '' : 'transition-transform duration-200'"
            :style="{ transform: `translateX(${offset}px)` }"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
        >
            <button
                type="button"
                class="flex min-w-0 flex-1 flex-col gap-1 p-4 text-left focus-visible:outline-3 focus-visible:-outline-offset-3 focus-visible:outline-primary"
                @click="onClick"
            >
                <span class="text-lg leading-snug font-bold text-cemento-900">{{ line.description }}</span>
                <span class="flex items-baseline justify-between gap-3">
                    <span class="text-base text-cemento-700 tabular-nums">{{ formatQuantity(line.quantity) }} {{ unit }} × {{ formatEuro(line.unit_price) }}</span>
                    <span class="shrink-0 text-lg font-extrabold text-cemento-900 tabular-nums">{{ formatEuro(line.amount) }}</span>
                </span>
                <span
                    v-if="details.length > 0"
                    class="text-sm font-semibold text-cemento-700"
                >{{ details.join(' · ') }}</span>
            </button>

            <UButton
                v-if="editable"
                icon="i-lucide-trash-2"
                color="neutral"
                variant="ghost"
                size="lg"
                aria-label="Elimina riga"
                class="my-auto mr-2 hidden text-cemento-500 hover:text-error lg:inline-flex"
                @click="emit('remove')"
            />
        </div>
    </div>
</template>
