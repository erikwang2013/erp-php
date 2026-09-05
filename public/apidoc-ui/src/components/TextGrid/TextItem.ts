import { defineComponent, h, PropType } from 'vue'

export default defineComponent({
  components: {},
  props: {
    title: {
      type: [Number, String] as PropType<number | string>,
      default: '',
    },
    value: {
      type: [Number, String] as PropType<number | string>,
      default: '',
    },
    labelWidth: {
      type: [Number, String] as PropType<number | string>,
      default: 80,
    },
  },
  render() {
    const { title, value, $slots, labelWidth } = this

    let labelWidthStyle = labelWidth
    if (typeof labelWidth === 'number') {
      labelWidthStyle = labelWidth + 'px'
    }

    return h(
      'div',
      {
        class: ['text-grid-item'],
      },
      [
        h(
          'div',
          {
            class: ['text-grid-item_label'],
            style: { width: labelWidthStyle },
          },
          [title, $slots.title && $slots.title()],
        ),
        h(
          'div',
          {
            class: ['text-grid-item_value'],
          },
          [value, $slots.default && $slots.default()],
        ),
      ],
    )
  },
})
