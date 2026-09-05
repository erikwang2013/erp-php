import Markdown from './Markdown.vue'
import Anchor from './Anchor.vue'
import { createApp } from 'vue'
import Modal from './Modal.vue'
import { ObjectType } from '/#/index'
import piniaStore from '/@/store'

export const MdAnchor = Anchor

export const mdModal = (props: ObjectType<any>): any => {
  // 实例化组件，createApp第二个参数是props
  const confirmInstance = createApp(Modal, {
    ...props,
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

export default Markdown
