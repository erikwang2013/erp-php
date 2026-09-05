export interface CreateStorageParams {
  prefixKey: string
  storage: Storage
}

export const createStorage = ({
  prefixKey = '',
  storage = localStorage,
}: CreateStorageParams): any => {
  const WebStorage = class WebStorage {
    private prefixKey?: string
    private storage: Storage

    constructor() {
      this.prefixKey = prefixKey
      this.storage = storage
    }
    private getKey(key: string) {
      return `${this.prefixKey}${key}`.toUpperCase()
    }
    /**
     *
     * @param {string} key
     * @param {*} value
     * @param {number} expire  Expiration time in seconds
     * @memberof Cache
     */
    // eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
    set(key: string, value: any, expire: number | null = null): void {
      const stringData = JSON.stringify({
        value,
        time: Date.now(),
        expire: expire ? new Date().getTime() + expire * 1000 : null,
      })

      this.storage.setItem(this.getKey(key), stringData)
    }
    get(key: string, def: any = null): any {
      const val = this.storage.getItem(this.getKey(key))
      if (!val) return def

      try {
        const data = JSON.parse(val)
        const { value, expire } = data
        if (!expire || expire >= new Date().getTime()) {
          return value
        }
        this.remove(key)
      } catch (e) {
        return def
      }
    }
    remove(key: string): void {
      this.storage.removeItem(this.getKey(key))
    }
  }
  return new WebStorage()
}
