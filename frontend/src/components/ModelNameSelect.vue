<template>
  <select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)" v-bind="$attrs">
    <slot name="empty-option">
      <option value="">{{ emptyLabel }}</option>
    </slot>
    <optgroup v-for="group in MODEL_NAME_GROUPS" :key="group.group" :label="group.group">
      <option v-for="name in group.models" :key="name" :value="name">{{ name }}</option>
    </optgroup>
  </select>
</template>

<script setup>
import { MODEL_NAME_GROUPS } from '../constants/modelNames';

defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  emptyLabel: {
    type: String,
    default: 'ทุกโมเดล',
  },
});

defineEmits(['update:modelValue']);
</script>
