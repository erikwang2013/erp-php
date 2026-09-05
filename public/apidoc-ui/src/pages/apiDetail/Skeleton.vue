<template>
  <div class="skeleton">
    <ul>
      <li class="skeleton-title skeleton-sm"></li>
      <li class="skeleton-xs"></li>
      <li class="skeleton-inline">
        <div class="skeleton-xs"></div>
        <div class="skeleton-sm"></div>
      </li>
      <li class="skeleton-title"></li>
      <li class="skeleton-inline">
        <div class="skeleton-xs"></div>
        <div class="skeleton-xs"></div>
        <div class="skeleton-xs"></div>
        <div class="skeleton-xs"></div>
      </li>
      <li class="skeleton-sm"></li>
      <li></li>
      <li></li>
      <li></li>
      <li class="skeleton-sm"></li>
      <li></li>
      <li></li>
      <li></li>
      <li class="skeleton-sm"></li>
      <li></li>
      <li></li>
      <li></li>

      <!-- <li class="skeleton-md"></li>
      <li class="skeleton-lg"></li> -->
    </ul>
  </div>
</template>

<style lang="less" scoped>
  .skeleton {
    & > ul {
      list-style: none;
      padding: 0;
      li {
        list-style: none;
        height: 16px;
        margin-top: 16px;
        background: @background-color-base;
      }
      .skeleton-title {
        height: 32px;
      }
      .skeleton-xs {
        width: 50px;
      }
      .skeleton-sm {
        width: 20%;
      }
      .skeleton-md {
        width: 40%;
      }
      .skeleton-lg {
        width: 70%;
      }
      .skeleton-inline {
        background: none;
        overflow: hidden;
        & > div {
          display: inline-block;
          margin-right: 16px;
          height: 16px;
          background: @background-color-base;
        }
      }
    }
  }
</style>
