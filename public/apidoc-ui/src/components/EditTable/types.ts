import { ObjectType } from '/#/index'

export interface ColumnItem {
  title?: string
  dataIndex: string
  width?: number | string
  itemRender?: ItemRender
  align?: 'left' | 'center' | 'right'
}

interface ItemRender {
  name:
    | 'input'
    | 'number'
    | 'select'
    | 'checkbox'
    | 'app-select'
    | 'delete-button'
    | 'check-status'
    | 'auto'
    | 'auto-complete'
  props?: ObjectType<any>
  on?: ItemNo
}

interface ItemNo {
  change?: (v: any) => void
}
