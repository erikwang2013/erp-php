//动态加载外部css

export function loadStyle(url) {
  const link = document.createElement('link')
  link.type = 'text/css'
  link.rel = 'stylesheet'
  link.href = url
  const head = document.head || document.getElementsByTagName('head')[0]
  ;(document.body || head).appendChild(link)
}
