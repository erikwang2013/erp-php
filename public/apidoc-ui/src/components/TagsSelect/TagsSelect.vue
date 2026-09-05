<template>
  <div v-if="Object.keys(apidocStore.dashboard.apiTagTotal).length" class="tags-select">
    <a-popover placement="bottom" overlayClassName="tags-select_popover">
      <template #content>
        <div class="tags-select_list select-menu">
          <ul>
            <li
              v-for="(_number, key) in apidocStore.dashboard.apiTagTotal"
              :key="key"
              :class="{ active: selectTags.includes(key as string) }"
              @click="onSelectTag(key as string)"
            >
              {{ key }}
              <span class="tags-select_check">
                <CheckOutlined />
              </span>
            </li>
          </ul>
        </div>
      </template>
      <a-badge v-if="selectTags.length > 0" :count="selectTags.length">
        <a-button
          ><template #icon><TagsOutlined /></template
        ></a-button>
      </a-badge>
      <a-button v-else
        ><template #icon><TagsOutlined /></template
      ></a-button>
    </a-popover>
  </div>
</template>

<script lang="ts" setup>
  import { TagsOutlined, CheckOutlined } from '@ant-design/icons-vue'
  import { useApidocStore } from '/@/store'
  const apidocStore = useApidocStore()
  let selectTags = ref<string[]>([])

  const emit = defineEmits<{
    (event: 'change', tags: string[]): void
  }>()

  const onSelectTag = (tag: string) => {
    if (selectTags.value.includes(tag)) {
      selectTags.value = selectTags.value.filter((item) => item !== tag)
    } else {
      selectTags.value = [...selectTags.value, tag]
    }
    emit('change', selectTags.value)
  }
</script>
<style lang="less" scoped>
  .tags-select {
    &:deep(.ant-badge-count) {
      top: 5px;
      right: 5px;
      height: 16px;
      line-height: 16px;
      font-size: 12px;
    }
    &_list {
      width: 180px;
    }
  }
</style>
