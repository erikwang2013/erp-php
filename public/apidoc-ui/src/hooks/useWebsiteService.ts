import { useRouter } from 'vue-router'

export default function useWebsiteService() {
  const router = useRouter()
  const events = {
    test: function (data) {
      console.log(data)
    },
    openTab: function (data) {
      // 仅允许 http/https 协议，防止 javascript:/data: 等注入
      const url = data.params && data.params.url
      if (typeof url !== 'string' || !/^https?:\/\//i.test(url)) {
        return
      }
      router.push({
        name: 'IframePage',
        query: {
          url: url,
          title: data.params.title,
        },
      })
    },
  }
  window.addEventListener(
    'message',
    function (e) {
      // 只处理同源 postMessage，防止任意来源注入事件
      if (e.origin !== window.location.origin) {
        return
      }
      // console.log(e.data)
      const event = e.data.event
      if (event && events[event]) {
        events[event](e.data)
      }
    },
    false,
  )

  return {}
}
