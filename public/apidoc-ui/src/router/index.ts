import { createRouter, createWebHashHistory } from 'vue-router'
import routes from './root'

import NProgress from 'nprogress'
import 'nprogress/nprogress.css'

//导入生成的路由数据
const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

router.beforeEach((_to, _from, next) => {
  NProgress.start() // 进度条开始
  next()
})

router.afterEach(() => {
  NProgress.done() // 进度条结束
})

export default router
