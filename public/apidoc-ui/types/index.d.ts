import { FeConfig } from '/@/store/modules/app/interface/FeConfig'

declare type ObjectType<T> = {
  [key: string]: T
}

declare const apidocFeConfig: FeConfig

declare module 'highlight.js/lib/core.js'
declare module 'highlight.js/lib/languages/json'
declare module 'highlight.js/lib/languages/htmlbars'
declare module 'highlight.js/lib/languages/php'
declare module 'highlight.js/lib/languages/javascript'
declare module 'highlight.js/lib/languages/php-template'
declare module 'highlight.js/lib/languages/xml'
declare module 'highlight.js/lib/languages/typescript'
