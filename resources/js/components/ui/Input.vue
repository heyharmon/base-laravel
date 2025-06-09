<script setup>
import { useVModel } from '@vueuse/core'

const props = defineProps({
  defaultValue: [String, Number],
  modelValue: [String, Number],
  class: [String, Object, Array]
})

const emits = defineEmits(['update:modelValue'])

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    class="flex h-9 w-full min-w-0 rounded-md border outline-none bg-transparent px-3 py-1 text-base shadow-xs  
           selection:bg-primary selection:text-primary-foreground 
           disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50  
           focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[1px] 
           aria-invalid:ring-destructive/20 aria-invalid:border-destructive
           placeholder:text-muted-foreground 
           transition-[color,box-shadow] 
           md:text-base"
  >
</template>
