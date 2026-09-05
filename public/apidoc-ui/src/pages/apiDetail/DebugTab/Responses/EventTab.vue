<template>
  <div class="event-list">
    <a-list size="small" bordered :data-source="props.eventList">
      <template #renderItem="{ item }">
        <a-list-item>
          <a-badge :status="item.status ? item.status : 'default'">
            <template #text>
              <span v-if="item.name">{{ item.name }}：</span>
              <span v-else
                >{{ t(`debug.event.${item.event}`, t('debug.event.custom') + item.event) }}：</span
              >
              <span v-if="item.handleValue"
                >{{ item.key }} = {{ item.handleValue }}({{ item.value }})</span
              >
              <span v-else-if="item.event == 'ajax'">
                <span>ajax("{{ item.url }}")</span>
                <div
                  v-if="item.before && typeof item.before == 'object' && item.before.length"
                  style="padding-left: 84px"
                >
                  <div
                    v-for="(ajaxEventItem, ajaxIndex) in item.before"
                    :key="ajaxIndex"
                    class="mb-xs"
                  >
                    <span
                      ><a-tag style="width: 50px">Before</a-tag
                      >{{ t(`debug.event.${ajaxEventItem.event}`) }}：</span
                    >
                    <span v-if="ajaxEventItem.handleValue"
                      >{{ ajaxEventItem.key }} = {{ ajaxEventItem.handleValue }}({{
                        ajaxEventItem.value
                      }})</span
                    >
                    <span v-else
                      >{{ ajaxEventItem.key }} {{ ajaxEventItem.value ? '=' : '' }}
                      {{ ajaxEventItem.value }}</span
                    >
                  </div>
                </div>

                <div
                  v-if="item.after && typeof item.after == 'object' && item.after.length"
                  style="padding-left: 84px"
                >
                  <div
                    v-for="(ajaxEventItem, ajaxIndex) in item.after"
                    :key="ajaxIndex"
                    class="mb-xs"
                  >
                    <span
                      ><a-tag style="width: 50px">After</a-tag
                      >{{ t(`debug.event.${ajaxEventItem.event}`) }}：</span
                    >
                    <span v-if="ajaxEventItem.handleValue"
                      >{{ ajaxEventItem.key }} = {{ ajaxEventItem.handleValue }}({{
                        ajaxEventItem.value
                      }})</span
                    >
                    <span v-else
                      >{{ ajaxEventItem.key }} {{ ajaxEventItem.value ? '=' : '' }}
                      {{ ajaxEventItem.value }}</span
                    >
                  </div>
                </div>
              </span>

              <span v-else
                >{{ item.key }} {{ item.value || item.realValue ? '=' : '' }}
                {{ item.realValue ? item.realValue : item.value }}</span
              >
              <span v-if="item.message" v-html="textToHtml(item.message)"></span>
            </template>
          </a-badge>
        </a-list-item>
      </template>
    </a-list>
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { textToHtml } from '/@/utils/helper'

  import { ApiDetailEventItem } from '/@/api/apidocApi/types'

  const { t } = useI18n()

  const props = withDefaults(
    defineProps<{
      eventList: ApiDetailEventItem[]
    }>(),
    {},
  )
</script>

<style lang="less" scoped></style>
