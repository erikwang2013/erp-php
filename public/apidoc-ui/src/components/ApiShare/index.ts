import { createApp } from 'vue'
import Modal from './ApiShare.vue'
import { ObjectType } from '/#/index'
import piniaStore from '/@/store'

function modal(props: ObjectType<any>): any {
  // 实例化组件，createApp第二个参数是props
  const confirmInstance = createApp(Modal, {
    ...props,
    onSuccess: (res: any) => {
      props.onSuccess && props.onSuccess(res)
      unmount()
    },
    onCancel: () => {
      props.onCancel && props.onCancel()
      unmount()
    },
  }).use(piniaStore)
  // 卸载组件
  const unmount = () => {
    confirmInstance.unmount()
    document.body.removeChild(parentNode)
  }
  // 创建一个挂载容器
  const parentNode = document.createElement('div')
  document.body.appendChild(parentNode)
  // 挂载组件
  confirmInstance.mount(parentNode)
  return confirmInstance
}
export default modal
