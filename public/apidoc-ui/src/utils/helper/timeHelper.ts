/**
 * 格式化时间
 * @param dateTime
 * @param format
 * @returns
 */
export const formatTime = (dateTime = new Date(), format = 'hh:mm:ss') => {
  const z = {
    y: dateTime.getFullYear(),
    M: dateTime.getMonth() + 1,
    d: dateTime.getDate(),
    h: dateTime.getHours(),
    m: dateTime.getMinutes(),
    s: dateTime.getSeconds(),
  }
  return format.replace(/(y+|M+|d+|h+|m+|s+)/g, function (v) {
    return ((v.length > 1 ? '0' : '') + z[v.slice(-1)]).slice(-(v.length > 2 ? v.length : 2))
  })
}
