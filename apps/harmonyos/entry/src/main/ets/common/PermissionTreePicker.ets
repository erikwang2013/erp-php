/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { PermissionNode } from '../model/DataModels';

/**
 * 权限树勾选器（纯组件；懒渲染版）。
 *
 * 输入：后端嵌套权限树 nodes（id 为 hashid 字符串、children 仅在有子节点时下发）+
 * 已授权平铺 id 集合 presetIds（角色编辑预选按后端逐条下发含中间目录的
 * permissions 平铺映射而来，勿只比顶层——否则叶子授权保存即静默清空）。
 *
 * 渲染（P1-h 懒渲染）：依折叠状态计算「可见行平铺数组」（折叠剪枝——收起组
 * 的子树不下钻不生成行；默认仅展开一级，种子 358 节点树首屏只渲染 26 个顶级
 * 行），外层 List 懒容器按可见行渲染、只物化可视区行。List 高度 = 可见行数
 * × 行高、封顶 TREE_MAX_ROWS 行：未超上限时恰包住内容、随宿主弹框（FormDialog
 * 字段区 Scroll）整体滚动；超出后树区内部滚动——两者互斥，不叠同向双滚动。
 * 勾选态判定改为预计算集合（pickedSet = 自身在集、descSel = 含已勾后代），
 * 取代原逐行 indexOf + 递归 hasDescendantSelected 的重复整树扫描。
 *
 * 三态级联（与 Flutter permission_tree_picker 同语义，勿改）：
 * 勾选集合为平铺 id 集；节点态由「自身 id 是否在集 + 后代是否在集」推导：
 *   自身在集 → 实勾；自身不在但有后代在集 → indeterminate；否则未勾。
 * 点选节点：自身在集 → 整棵子树（含自身 id）移出集合（父清=全清）；
 *   自身不在（含 indeterminate 态）→ 整棵子树（含自身）加入（点 partial 父=全勾）。
 * 子级变更不回写祖先（已勾父下取消一子 → 父保持勾选，Flutter 同语义）。
 * 输出：全勾 id 全集合（含目录 id，逐 id 原样往返、不做 antd 式自动闭包），
 * 每次点选后经 onChange 全量回调。
 */

/** 行高（vp）与 List 懒容器可视行上限（超出时树区内部滚动） */
const TREE_ROW_HEIGHT: number = 44;
const TREE_MAX_ROWS: number = 8;

/** 平铺可见行：节点 + 渲染缩进深度 */
class TreeRowData {
  node: PermissionNode;
  depth: number;

  constructor(node: PermissionNode, depth: number) {
    this.node = node;
    this.depth = depth;
  }
}

@Component
export struct PermissionTreePicker {
  // nodes 被宿主替换（弹框内「刷新」重载权限区）时重建可见行，防止树区渲染旧树
  @Prop @Watch('onNodesChange') nodes: Array<PermissionNode> = [];
  @Prop presetIds: Array<string> = [];
  onChange?: (ids: Array<string>) => void;

  @State picked: Array<string> = [];
  @State collapsed: Array<string> = []; // 已收起（含子节点的）组 id
  // picked 的 Set 视图与「含已勾后代」节点集：行渲染 O(1) 判定（每次 picked
  // 变更后全量重建——358 节点后序一次微秒级，远小于原逐行递归扫描）
  @State pickedSet: Set<string> = new Set<string>();
  @State descSel: Set<string> = new Set<string>();
  // 可见行缓存（随 collapsed 同步重建；渲染触发仍由 collapsed/picked 承担，
  // 本字段仅作 ForEach 数据源与 List 高度计算）
  private rowsCache: Array<TreeRowData> = [];

  aboutToAppear(): void {
    this.picked = this.presetIds.slice();
    this.refreshSets();
    // 默认仅展开一级：全部含子节点 id 置收起（首屏只渲染顶级行）
    const init: Array<string> = [];
    this.collectParents(this.nodes, init);
    this.collapsed = init;
    this.rowsCache = this.flattenVisible();
  }

  /** 节点替换后重建可见行（折叠/勾选状态按 id 保留，与旧版实时渲染语义一致） */
  onNodesChange(): void {
    this.rowsCache = this.flattenVisible();
  }

  private childrenOf(node: PermissionNode): Array<PermissionNode> {
    return node.children ?? [];
  }

  private hasId(list: Array<string>, id: string): boolean {
    return list.indexOf(id) >= 0;
  }

  /** picked 变更后重建 pickedSet + descSel（descSel 全树后序一次） */
  private refreshSets(): void {
    const ps = new Set<string>();
    for (let i = 0; i < this.picked.length; i++) {
      ps.add(this.picked[i]);
    }
    // descSel 遍历必须用本次新集 ps（若读 this.pickedSet 会滞后一拍：
    // 赋值在遍历之后才执行，首播/每次 toggle 后 descSel 都基于上一事件）
    const ds = new Set<string>();
    for (let i = 0; i < this.nodes.length; i++) {
      this.markDescSel(this.nodes[i], ds, ps);
    }
    this.pickedSet = ps;
    this.descSel = ds;
  }

  /** 后序标记：节点含已勾后代（自身在集不算）则记入 out；sel 为本次新 picked 集 */
  private markDescSel(node: PermissionNode, out: Set<string>, sel: Set<string>): boolean {
    const children = this.childrenOf(node);
    let any = false;
    for (let i = 0; i < children.length; i++) {
      const child = children[i];
      if (sel.has(child.id)) {
        any = true;
      } else if (this.markDescSel(child, out, sel)) {
        any = true;
      }
    }
    if (any) {
      out.add(node.id);
    }
    return any;
  }

  /** 收集全部含子节点 id（默认收起集：展开一级） */
  private collectParents(nodes: Array<PermissionNode>, out: Array<string>): void {
    for (let i = 0; i < nodes.length; i++) {
      const node = nodes[i];
      if (this.childrenOf(node).length > 0) {
        out.push(node.id);
        this.collectParents(this.childrenOf(node), out);
      }
    }
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
    this.refreshSets();
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
    this.rowsCache = this.flattenVisible();
  }

  /** 可见行平铺：深度优先 + 折叠剪枝（收起组不下钻） */
  private flattenVisible(): Array<TreeRowData> {
    const out: Array<TreeRowData> = [];
    this.flattenInto(this.nodes, 0, out);
    return out;
  }

  private flattenInto(nodes: Array<PermissionNode>, depth: number, out: Array<TreeRowData>): void {
    for (let i = 0; i < nodes.length; i++) {
      const node = nodes[i];
      out.push(new TreeRowData(node, depth));
      if (this.childrenOf(node).length > 0 && !this.hasId(this.collapsed, node.id)) {
        this.flattenInto(this.childrenOf(node), depth + 1, out);
      }
    }
  }

  build() {
    Column() {
      if (this.rowsCache.length === 0) {
        Text('')
      } else {
        List({ space: 0 }) {
          ForEach(this.rowsCache, (row: TreeRowData) => {
            ListItem() {
              this.buildRow(row)
            }
          }, (row: TreeRowData) => row.node.id)
        }
        .width('100%')
        // 高度=可见行数×行高、封顶 TREE_MAX_ROWS：不叠同向双滚动（见头部注释）
        .height(Math.min(this.rowsCache.length, TREE_MAX_ROWS) * TREE_ROW_HEIGHT)
        .scrollBar(BarState.Off)
      }
    }
    .width('100%')
    .alignItems(HorizontalAlign.Start)
  }

  @Builder
  buildRow(row: TreeRowData) {
    Row() {
      // 展开/收起箭头区（仅含子节点渲染；点击独立，不与行勾选冲突）
      if (this.childrenOf(row.node).length > 0) {
        Button() {
          Image($rawfile('arrow_right.png'))
            .width(14)
            .height(14)
            .fillColor($r('app.color.text_hint'))
            .rotate({ angle: this.hasId(this.collapsed, row.node.id) ? 0 : 90 })
        }
        .width(28)
        .height(28)
        .backgroundColor(Color.Transparent)
        .borderRadius(4)
        .onClick(() => {
          this.toggleCollapse(row.node);
        })
      } else {
        Row()
          .width(28)
          .height(28)
      }

      // 自绘勾选框：ArkUI Checkbox 无 indeterminate 单态 → 三态复合手绘
      Row() {
        if (this.pickedSet.has(row.node.id)) {
          Text('✓')
            .fontSize(12)
            .fontColor(Color.White)
            .fontWeight(FontWeight.Bold)
        } else if (this.descSel.has(row.node.id)) {
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
      .backgroundColor(this.pickedSet.has(row.node.id) || this.descSel.has(row.node.id)
        ? $r('app.color.primary') : $r('app.color.surface'))
      .border({ width: 1, color: this.pickedSet.has(row.node.id) || this.descSel.has(row.node.id)
        ? $r('app.color.primary') : $r('app.color.border') })
      .borderRadius(4)
      .onClick(() => {
        this.toggle(row.node);
      })

      // 名称 + slug（点击同勾选）
      Column() {
        Text(row.node.name || row.node.slug || '-')
          .fontSize(14)
          .fontWeight(this.childrenOf(row.node).length > 0 ? FontWeight.Medium : FontWeight.Normal)
          .fontColor($r('app.color.text_primary'))
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .width('100%')
        if (row.node.slug && row.node.slug.length > 0) {
          Text(row.node.slug)
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
        this.toggle(row.node);
      })
    }
    .width('100%')
    .height(TREE_ROW_HEIGHT)
    .padding({ left: 8 + row.depth * 20 })
    .alignItems(VerticalAlign.Center)
  }
}
