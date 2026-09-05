import { defineComponent, h, PropType } from 'vue'
import './TextGrid.less'
export default defineComponent({
  components: {},
  props: {
    colspan: {
      type: Number as PropType<number>,
      default: 1,
    },
    labelWidth: {
      type: [Number, String] as PropType<number | string>,
      default: '',
    },
  },
  render() {
    const { $slots, colspan, labelWidth } = this
    let colSpanStyle = ''
    for (let i = 0; i < colspan; i++) {
      colSpanStyle += ' 1fr'
    }

    let slots: any = $slots.default && $slots.default()

    if (labelWidth && slots && slots.length) {
      slots = slots.map((p) => {
        if (p.props && !p.props.labelWidth) {
          p.props.labelWidth = labelWidth
        }
        return p
      })
    }

    return h(
      'div',
      {
        class: ['text-grid'],
        style: { 'grid-template-columns': colSpanStyle },
      },
      {
        default() {
          return slots
        },
      },
    )
  },
})
