<template>
  <div class="drag-line-x" @mousedown="mouseDown" :style="{ left: lastX + 'px' }"></div>
</template>

<script setup lang="ts">
  interface Props {
    value: number
    min: number
    max: number
  }
  const props = withDefaults(defineProps<Props>(), {
    value: 200,
    min: 200,
    max: 1000,
  })
  const emit = defineEmits<{
    (event: 'mouseMoveChange', x: number): void
    (event: 'mouseUpChange', x: number): void
  }>()
  const lastX = ref(props.value)

  const mouseDown = (event: MouseEvent) => {
    document.addEventListener('mousemove', mouseMove)
    document.addEventListener('mouseup', mouseUp)
    lastX.value = event.screenX
  }
  const mouseMove = (event: MouseEvent) => {
    const { screenX } = event
    if (props.min > 0 && screenX < props.min) {
      lastX.value = props.min
      document.removeEventListener('mousemove', mouseMove)
    } else if (props.max > 0 && screenX > props.max) {
      lastX.value = props.max
      document.removeEventListener('mousemove', mouseMove)
    } else {
      lastX.value = screenX
    }
    emit('mouseMoveChange', lastX.value)
  }
  const mouseUp = () => {
    if (props.min > 0 && lastX.value < props.min) {
      lastX.value = props.min
    } else if (props.max > 0 && lastX.value > props.max) {
      lastX.value = props.max
    }
    emit('mouseUpChange', lastX.value)
    document.removeEventListener('mousemove', mouseMove)
    document.removeEventListener('mouseup', mouseUp)
  }
  onBeforeUnmount(() => {
    document.removeEventListener('mousemove', mouseMove)
    document.removeEventListener('mouseup', mouseUp)
  })
</script>
<style lang="less" scoped>
  .drag-line-x {
    position: absolute;
    top: 0;
    right: -2px;
    z-index: 100;
    width: 3px;
    height: 100%;
    cursor: col-resize;
    border-top: none;
    border-bottom: none;

    &.hide {
      display: none;
    }

    &:hover {
      background: @primary-color-hover;
      box-shadow: 0 0 4px 0 rgba(28, 36, 56, 0.15);
    }
  }
</style>
