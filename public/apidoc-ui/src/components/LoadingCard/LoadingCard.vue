<template>
  <div class="loading-card">
    <!-- <img src="../../assets/images/logo.png" /> -->
    <a-spin tip="Loading..." />
  </div>
</template>

<script lang="ts" setup></script>
<style lang="less" scoped>
  .loading-card {
    padding: 200px;
    text-align: center;
    & > img {
      width: 50px;
    }
    .ant-spin {
      display: inline-block;
    }
  }
</style>
