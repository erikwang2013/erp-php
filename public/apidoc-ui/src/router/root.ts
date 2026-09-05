import BasicLayout from '../layout/index.vue'

import KeepPage from '../layout/KeepPage.vue'

export default [
  {
    path: '/',
    name: 'index',
    component: BasicLayout,
    meta: {},
    redirect: '/home',
    children: [
      {
        path: '/home',
        name: 'Home',
        component: () => import(/* webpackChunkName: "home" */ '../pages/home/index.vue'),
        meta: {
          keepKey: 'Home',
          affix: true,
        },
      },
      {
        path: '/api',
        name: 'ApiDetail',
        component: () => import(/* webpackChunkName: "apiDetail" */ '../pages/apiDetail/index.vue'),
        meta: {},
      },
      {
        path: '/md',
        name: 'MdDetail',
        component: () => import(/* webpackChunkName: "mdDetail" */ '../pages/mdDetail/index.vue'),
        meta: {},
      },
      {
        path: '/iframePage',
        name: 'IframePage',
        component: KeepPage,
        meta: {},
      },
    ],
  },
]
