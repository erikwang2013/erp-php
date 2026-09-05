import { ref, Ref } from 'vue'

interface Types {
  visible: Ref<boolean>
  onShow: () => void
  onCancel: () => void
}
export default (): Types => {
  const visible = ref(false)

  function onShow() {
    visible.value = true
  }

  function onCancel() {
    visible.value = false
  }

  return {
    visible,
    onShow,
    onCancel,
  }
}
