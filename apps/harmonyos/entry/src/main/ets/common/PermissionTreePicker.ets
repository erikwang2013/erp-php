/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { PermissionNode } from '../model/DataModels';

/**
 * 权限树勾选器（纯组件；随宿主弹框整体滚动，不设独立滚动区）。
 *
 * 输入：后端嵌套权限树 nodes（id 为 hashid 字符串、children 仅在有子节点时下发）+
 * 已授权平铺 id 集合 presetIds（角色编辑预选按后端逐条下发含中间目录的
 * permissions 平铺映射而来，勿只比顶层——否则叶子授权保存即静默清空）。
 *
 * 三态级联（与 Flutter permission_tree_picker 同语义）：
 * 勾选集合为平铺 id 集；节点态由「自身 id 是否在集 + 后代是否在集」推导：
 *   自身在集 → 实勾；自身不在但有后代在集 → indeterminate；否则未勾。
 * 点选节点：自身在集 → 整棵子树（含自身 id）移出集合（父清=全清）；
 *   自身不在（含 indeterminate 态）→ 整棵子树（含自身）加入（点 partial 父=全勾）。
 * 子级变更不回写祖先（已勾父下取消一子 → 父保持勾选，Flutter 同语义）。
 * 输出：全勾 id 全集合（含目录 id，逐 id 原样往返、不做 antd 式自动闭包），
 * 每次点选后经 onChange 全量回调。
 */
@Component
export struct PermissionTreePicker {
  @Prop nodes: Array<PermissionNode> = [];
  @Prop presetIds: Array<string> = [];
  onChange?: (ids: Array<string>) => void;

  @State picked: Array<string> = [];
  @State collapsed: Array<string> = []; // 已收起（含子节点的）组 id

  aboutToAppear(): void {
    this.picked = this.presetIds.slice();
  }

  private childrenOf(node: PermissionNode): Array<PermissionNode> {
    return node.children ?? [];
  }

  private hasId(list: Array<string>, id: string): boolean {
    return list.indexOf(id) >= 0;
  }

  private hasDescendantSelected(node: PermissionNode): boolean {
    const children = this.childrenOf(node);
    for (let i = 0; i < children.length; i++) {
      if (this.hasId(this.picked, children[i].id) || this.hasDescendantSelected(children[i])) {
        return true;
      }
    }
    return false;
  }

  /** 收集节点自身 + 全部后代 id（子树操作/判定用） */
  private collectSubtree(node: PermissionNode, out: Array<string>): void {
    out.push(node.id);
    const children = this.childrenOf(node);
    for (let i = 0; i < children.length; i++) {
      this.collectSubtree(children[i], out);
    }
  }

  /**
   * 切换节点勾选：父节点整棵子树全勾/全清，叶子只动自身。
   * 依据点选前状态决定，indeterminate 也走「全勾」分支（与 Flutter 规格一致）。
   */
  private toggle(node: PermissionNode): void {
    const subtree: Array<string> = [];
    this.collectSubtree(node, subtree);
    const next = this.picked.slice();
    if (this.hasId(next, node.id)) {
      for (let i = 0; i < subtree.length; i++) {
        const idx = next.indexOf(subtree[i]);
        if (idx >= 0) {
          next.splice(idx, 1);
        }
      }
    } else {
      for (let i = 0; i < subtree.length; i++) {
        if (!this.hasId(next, subtree[i])) {
          next.push(subtree[i]);
        }
      }
    }
    this.picked = next;
    if (this.onChange) {
      this.onChange(next.slice());
    }
  }

  private toggleCollapse(node: PermissionNode): void {
    if (this.hasId(this.collapsed, node.id)) {
      const next = this.collapsed.slice();
      next.splice(next.indexOf(node.id), 1);
      this.collapsed = next;
    } else {
      this.collapsed = this.collapsed.concat([node.id]);
    }
  }

  build() {
    Column() {
      if (this.nodes.length > 0) {
        this.buildRows(this.nodes, 0)
      } else {
        Text('')
      }
    }
    .width('100%')
    .alignItems(HorizontalAlign.Start)
  }

  @Builder
  buildRows(nodes: Array<PermissionNode>, depth: number) {
    ForEach(nodes, (node: PermissionNode) => {
      Row() {
        // 展开/收起箭头区（仅含子节点渲染；点击独立，不与行勾选冲突）
        if (this.childrenOf(node).length > 0) {
          Button() {
            Image($rawfile('arrow_right.png'))
              .width(14)
              .height(14)
              .fillColor($r('app.color.text_hint'))
              .rotate({ angle: this.hasId(this.collapsed, node.id) ? 0 : 90 })
          }
          .width(28)
          .height(28)
          .backgroundColor(Color.Transparent)
          .borderRadius(4)
          .onClick(() => {
            this.toggleCollapse(node);
          })
        } else {
          Row()
            .width(28)
            .height(28)
        }

        // 自绘勾选框：ArkUI Checkbox 无 indeterminate 单态 → 三态复合手绘
        Row() {
          if (this.hasId(this.picked, node.id)) {
            Text('✓')
              .fontSize(12)
              .fontColor(Color.White)
              .fontWeight(FontWeight.Bold)
          } else if (this.hasDescendantSelected(node)) {
            Row()
              .width(8)
              .height(2)
              .backgroundColor(Color.White)
              .borderRadius(1)
          }
        }
        .width(20)
        .height(20)
        .justifyContent(FlexAlign.Center)
        .backgroundColor(this.hasId(this.picked, node.id) || this.hasDescendantSelected(node)
          ? $r('app.color.primary') : $r('app.color.surface'))
        .border({ width: 1, color: this.hasId(this.picked, node.id) || this.hasDescendantSelected(node)
          ? $r('app.color.primary') : $r('app.color.border') })
        .borderRadius(4)
        .onClick(() => {
          this.toggle(node);
        })

        // 名称 + slug（点击同勾选）
        Column() {
          Text(node.name || node.slug || '-')
            .fontSize(14)
            .fontWeight(this.childrenOf(node).length > 0 ? FontWeight.Medium : FontWeight.Normal)
            .fontColor($r('app.color.text_primary'))
            .maxLines(1)
            .textOverflow({ overflow: TextOverflow.Ellipsis })
            .width('100%')
          if (node.slug && node.slug.length > 0) {
            Text(node.slug)
              .fontSize(11)
              .fontColor($r('app.color.text_hint'))
              .maxLines(1)
              .textOverflow({ overflow: TextOverflow.Ellipsis })
              .width('100%')
          }
        }
        .alignItems(HorizontalAlign.Start)
        .justifyContent(FlexAlign.Center)
        .layoutWeight(1)
        .margin({ left: 6 })
        .onClick(() => {
          this.toggle(node);
        })
      }
      .width('100%')
      .height(44)
      .padding({ left: 8 + depth * 20 })
      .alignItems(VerticalAlign.Center)

      if (this.childrenOf(node).length > 0 && !this.hasId(this.collapsed, node.id)) {
        this.buildRows(this.childrenOf(node), depth + 1)
      }
    }, (node: PermissionNode) => node.id)
  }
}
