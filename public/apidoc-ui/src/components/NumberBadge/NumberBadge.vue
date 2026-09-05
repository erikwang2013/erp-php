<template>
  <div :class="['number-badge', type]">{{ props.count }} </div>
</template>

<script setup lang="ts">
  const props = withDefaults(
    defineProps<{
      count: number
      type?: 'default' | 'success' | 'error' | 'warning' | 'processing'
    }>(),
    {
      count: 0,
      type: 'default',
    },
  )
</script>

<style lang="less" scoped>
  .number-badge {
    display: inline-block;
    z-index: auto;
    min-width: 18px;
    height: 18px;
    padding: 0 6px;
    color: @text-color;
    font-weight: normal;
    font-size: 12px;
    line-height: 18px;
    white-space: nowrap;
    text-align: center;
    background: @component-background;
    border-radius: 10px;
    box-shadow: 0 0 0 1px @input-border-color;
    &.success {
      color: @badge-text-color;
      background: @success-color;
      box-shadow: 0 0 0 1px @success-color;
    }
    &.error {
      color: @badge-text-color;
      background: @badge-color;
      box-shadow: 0 0 0 1px @badge-color;
    }
    &.warning {
      color: @badge-text-color;
      background: @warning-color;
      box-shadow: 0 0 0 1px @warning-color;
    }
    &.processing {
      color: @badge-text-color;
      background: @info-color;
      box-shadow: 0 0 0 1px @info-color;
    }
  }
</style>
