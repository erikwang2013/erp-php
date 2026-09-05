import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import piniaStore from './store'
import './assets/icons/iconfont/iconfont.css'
import './styles/index.less'

import 'ant-design-vue/es/message/style/css'

// 支持SVG
import 'virtual:svg-icons-register'

import { setupI18n } from '/@/locales/setupI18n'
import { downloadFile } from '/@/utils/helper/index'

window['downloadFile'] = downloadFile
const app = createApp(App)
setupI18n(app)
app.use(router).use(piniaStore).mount('#app')
