<template>
  <div>
    <a-popover placement="bottomRight">
      <template #content>
        <iframe
          :src="appStore.apidocHost + RoutePathEnum.UserMenuCard"
          ref="iframe"
          class="user-iframe"
          border="0"
        ></iframe>
      </template>
      <a-avatar>
        <template #icon>
          <UserOutlined />
        </template>
      </a-avatar>
    </a-popover>
  </div>
</template>

<script setup lang="ts">
  import { UserOutlined } from '@ant-design/icons-vue'
  import { useAppStore } from '/@/store/modules/app'
  import { RoutePathEnum } from '/@/enums/websiteService'
  const appStore = useAppStore()
</script>

<style lang="less" scoped>
  .user-iframe {
    width: 260px;
    height: 340px;
    border: none;
  }
</style>
