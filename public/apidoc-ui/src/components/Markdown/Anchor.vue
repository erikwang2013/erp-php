<template>
  <a-anchor :getContainer="pageContainer" :offsetTop="95" :target-offset="50" @click="handleClick">
    <a-anchor-link v-for="(item, i) in navs" :key="i" :href="`#${item.title}`" :title="item.title">
      <template v-if="item.children && item.children.length">
        <a-anchor-link
          v-for="(cItem, j) in item.children"
          :key="j"
          :href="`#${cItem.title}`"
          :title="cItem.title"
        />
      </template>
    </a-anchor-link>
  </a-anchor>
</template>

<script lang="ts">
  import { defineComponent, PropType, watchEffect, ref } from 'vue'
  import { handleNavTree } from './helper'

  export default defineComponent({
    components: {},
    props: {
      md: {
        type: String as PropType<string>,
        default: '',
      },
    },
    setup(props) {
      const pageContainer = ref()
      const navs = ref()

      watchEffect(() => {
        navs.value = handleNavTree(props.md)
      })

      const handleClick = (e: Event) => {
        e.preventDefault()
      }

      return {
        navs,
        pageContainer,
        handleClick,
      }
    },
  })
</script>
<style lang="less" scoped></style>
