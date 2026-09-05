<template>
  <div class="home-page">
    <div class="home-page-content">
      <div class="readme">
        <h1 v-if="appStore.serverConfig.title">{{ appStore.serverConfig.title }}</h1>
        <h1 v-else-if="appStore.feConfig.TITLE">{{ appStore.feConfig.TITLE }}</h1>

        <p>{{ appStore.serverConfig.desc }}</p>
      </div>
      <a-row :gutter="16">
        <a-col :xs="24" :sm="24" :md="8" :lg="8" :xl="8">
          <div class="number-block">
            <a-card-meta class="number-block-item">
              <template #avatar>
                <a-avatar class="color-orange" :size="50">APP</a-avatar>
              </template>
              <template #title>
                <div>{{ apidocStore.dashboard.appCount }}</div>
              </template>
              <template #description>
                <div>{{ t('home.appCount') }}</div>
              </template>
            </a-card-meta>
            <a-card-meta class="number-block-item">
              <template #avatar>
                <a-avatar class="color-green" :size="50">API</a-avatar>
              </template>
              <template #title>
                <div>{{ apidocStore.dashboard.apiCount }}</div>
              </template>
              <template #description>
                <div>{{ t('home.apiCount') }}</div>
              </template>
            </a-card-meta>
            <a-card-meta class="number-block-item">
              <template #avatar>
                <a-avatar class="color-blue" :size="50">DOCS</a-avatar>
              </template>
              <template #title>
                <div>{{ apidocStore.dashboard.docsCount }}</div>
              </template>
              <template #description>
                <div>{{ t('home.docsCount') }}</div>
              </template>
            </a-card-meta>
          </div>
        </a-col>
        <a-col :xs="24" :sm="24" :md="8" :lg="8" :xl="8">
          <a-card class="mb-sm" :bodyStyle="{ padding: '10px' }">
            <template #title> {{ t('home.methodCount') }} </template>
            <div
              v-if="Object.keys(apidocStore.dashboard.apiMethodTotal).length"
              class="method-list"
            >
              <ul>
                <li v-for="(number, key) in apidocStore.dashboard.apiMethodTotal" :key="key">
                  <div class="info">
                    <div class="name">{{ key }}</div>
                    <div class="value">{{ number }}</div>
                  </div>
                  <div
                    class="bg"
                    :style="{
                      backgroundColor:
                        appStore.feConfig.METHOD_COLOR && appStore.feConfig.METHOD_COLOR[key]
                          ? appStore.feConfig.METHOD_COLOR[key]
                          : '',
                    }"
                  ></div>
                </li>
              </ul>
            </div>
          </a-card>
        </a-col>
        <a-col :xs="24" :sm="24" :md="8" :lg="8" :xl="8">
          <a-card class="mb-sm" :bodyStyle="{ padding: '10px' }">
            <template #title> {{ t('common.tag') }} </template>
            <div v-if="Object.keys(apidocStore.dashboard.apiTagTotal).length" class="tags-wraper">
              <a-tag v-for="(number, key) in apidocStore.dashboard.apiTagTotal" :key="key"
                >{{ key }} {{ number }}</a-tag
              >
            </div>
            <div v-else>
              <a-empty :image="state.simpleImage" :description="t('common.notdata')" />
            </div>
          </a-card>
        </a-col>
      </a-row>
      <a-row :gutter="16">
        <a-col :xs="24" :sm="24" :md="8" :lg="8" :xl="8">
          <a-card class="mb-sm">
            <template #title> {{ t('common.author') }} </template>
            <div
              v-if="Object.keys(apidocStore.dashboard.apiAuthorTotal).length"
              class="author-list"
            >
              <ul>
                <li v-for="(number, key) in apidocStore.dashboard.apiAuthorTotal" :key="key">
                  <h4>{{ key }}</h4>
                  <a-progress
                    :format="() => number"
                    :percent="parseInt((number / apidocStore.dashboard.apiCount) * 100 + '')"
                  />
                </li>
              </ul>
            </div>
            <div v-else>
              <a-empty :image="state.simpleImage" :description="t('common.notdata')" />
            </div>
          </a-card>
        </a-col>
        <a-col :xs="24" :sm="24" :md="16" :lg="16" :xl="16">
          <a-card class="mb-sm" :bodyStyle="{ padding: '10px' }">
            <template #title> {{ t('common.group') }} </template>
            <div>
              <a-table
                :columns="state.groupColumns"
                :pagination="false"
                size="small"
                :data-source="state.groupData"
                :locale="{ emptyText: t('common.notdata') }"
                rowKey="name"
              />
            </div>
          </a-card>
        </a-col>
      </a-row>
      <div v-if="appStore.feConfig.SHOW_VERSION !== false" class="footer-version"
        >Ui Version：{{ APP_VERSION }}</div
      >
    </div>
  </div>
</template>

<script lang="ts">
  export default {
    name: 'Home',
  }
</script>

<script lang="ts" setup>
  import { useAppStore, useApidocStore } from '/@/store'
  import { Empty } from 'ant-design-vue'
  import { ObjectType } from '/#/index'
  import { useI18n } from '/@/hooks/useI18n'
  import { Dashboard } from '/@/store/modules/Apidoc/types'
  import packageConfig from '../../../package.json'

  const APP_VERSION = packageConfig.version

  console.info('Version:' + APP_VERSION)

  const { t } = useI18n()
  const appStore = useAppStore()
  const apidocStore = useApidocStore()

  const state = reactive<{
    simpleImage: any
    groupColumns: ObjectType<string>[]
    groupData: any
  }>({
    simpleImage: Empty.PRESENTED_IMAGE_SIMPLE,
    groupColumns: [
      {
        title: t('common.name'),
        dataIndex: 'title',
      },
      {
        title: t('common.controller'),
        dataIndex: 'controller',
      },
      {
        title: t('common.api'),
        dataIndex: 'api',
      },
    ],
    groupData: [],
  })

  function handleGroupData(dashboard: Dashboard) {
    const app = appStore.appObject[appStore.appKey]
    if (!(app && app.groups && app.groups.length)) {
      return []
    }
    const controllerGroupTotal = dashboard.controllerGroupTotal
      ? dashboard.controllerGroupTotal
      : {}
    const apiGroupTotal = dashboard.apiGroupTotal ? dashboard.apiGroupTotal : {}

    function renderGroup(groups) {
      if (!(groups && groups.length)) {
        return []
      }
      return groups.map((item) => {
        const row: any = {
          title: item.title,
          controller: controllerGroupTotal[item.name],
          api: apiGroupTotal[item.name],
        }
        if (item.children && item.children.length) {
          row.children = renderGroup(item.children)
        }
        return row
      })
    }
    state.groupData = renderGroup(app.groups)
  }

  watchEffect(() => {
    handleGroupData(apidocStore.dashboard)
  })
</script>

<style lang="less" scoped>
  @import './home.less';
</style>
