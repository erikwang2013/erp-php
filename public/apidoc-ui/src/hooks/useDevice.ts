import { ref, Ref } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { DeviceEnum } from '/@/enums/appEnum'
import { useAppStore } from '/@/store/modules/app'

interface Types {
  device: Ref<string>
}
export default (): Types => {
  const appStore = useAppStore()
  const isMobile = useMediaQuery('(max-width: 1024px)')
  const device = ref(setDevice(isMobile.value))
  watch(
    () => isMobile.value,
    (is) => {
      device.value = setDevice(is)
    },
  )
  function setDevice(is: boolean) {
    const deviceCode = is === true ? DeviceEnum.MOBILE : DeviceEnum.DESKTOP
    appStore.setDevice(deviceCode)
    return deviceCode
  }

  return {
    device,
  }
}
