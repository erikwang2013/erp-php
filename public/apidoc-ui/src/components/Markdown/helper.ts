import { trim } from 'lodash-es'

interface ItemState {
  title: string
  level: number
  index?: number
  children?: ItemState[]
}

// 将一级二级标题数据处理成树结构
export function handleNavTree(mdContent: string): ItemState[] {
  const navs = getTitle(mdContent)
  const navLevel = [2, 3]
  let retNavs: any = []
  let toAppendNavList
  navLevel.forEach((level) => {
    // 遍历一级二级标题，将同一级的标题组成新数组
    toAppendNavList = find(navs, {
      level: level,
    })
    if (retNavs.length === 0) {
      // 处理一级标题
      retNavs = retNavs.concat(toAppendNavList)
    } else {
      // 处理二级标题，并将二级标题添加到对应的父级标题的children中
      toAppendNavList.forEach((item: any) => {
        item = Object.assign(item)
        const parentNavIndex = getParentIndex(navs, item.index)
        return appendToParentNav(retNavs, parentNavIndex, item)
      })
    }
  })
  return retNavs
}

function getTitle(content: string) {
  let nav: ItemState[] = []

  const tempArr: ItemState[] = []
  content.replace(/(#+)[^#][^\n]*?(?:\n)/g, function (match, m1) {
    let title = match.replace('\n', '')
    const level = m1.length
    title = title.replace(/^#+/, '').replace(/\([^)]*?\)/, '')
    tempArr.push({
      title: trim(title),
      level: level,
      children: [],
    })
    return ''
  })

  // 只处理一级二级标题，以及添加与id对应的index值
  nav = tempArr.filter((item) => item.level === 2 || item.level === 3)
  let index = 0
  return (nav = nav.map((item) => {
    item.index = index++
    return item
  }))
}

function find(arr: any, condition: any) {
  return arr.filter((item: any) => {
    for (const key in condition) {
      // eslint-disable-next-line no-prototype-builtins
      if (condition.hasOwnProperty(key) && condition[key] !== item[key]) {
        return false
      }
    }
    return true
  })
}

function getParentIndex(nav: any, endIndex: number) {
  for (let i = endIndex - 1; i >= 0; i--) {
    if (nav[endIndex].level > nav[i].level) {
      return nav[i].index
    }
  }
}
function appendToParentNav(nav: any, parentIndex: number, newNav: any) {
  const index = findIndex(nav, {
    index: parentIndex,
  })
  nav[index].children = nav[index].children.concat(newNav)
}

function findIndex(arr: any, condition: any) {
  let ret = -1
  arr.forEach((item: any, index: number) => {
    for (const key in condition) {
      // eslint-disable-next-line no-prototype-builtins
      if (condition.hasOwnProperty(key) && condition[key] !== item[key]) {
        return false
      }
    }
    ret = index
  })
  return ret
}

export const goAnchor = (selector: string): void => {
  // 移动距离
  let top = 0
  // 当前滚动条位置
  const scrollTop =
    document.documentElement.scrollTop || window.pageYOffset || document.body.scrollTop
  // 若为指定距离
  if (typeof selector === 'number') {
    top = selector - scrollTop
  } else {
    const anchor: any = document.querySelector(selector) || { offsetTop: 0 }
    top = anchor.offsetTop - scrollTop
  }
  window.scrollBy({ top, behavior: 'smooth' })
}
