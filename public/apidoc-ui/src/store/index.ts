import { createPinia } from 'pinia'
import { useAppStore } from './modules/app'
import { useApidocStore } from './modules/Apidoc'

const pinia = createPinia()

export { useAppStore, useApidocStore }
export default pinia
