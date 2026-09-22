// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import '../l10n/app_l10n.dart';

/// 单据明细行编辑器（订单录入路径）：录入 items[]，由调用方在 FormDialog 的 onSubmit
/// 闭包里取 [onChanged] 累积的结果塞进 payload。
///
/// 为什么挂在 FormDialog 的 child 插槽而不是做成字段类型：FormDialog 的表单值是
/// `Map<String,String>`（见 form_dialog.dart 的 _submit），装不下数组；改成 `Map<String,dynamic>`
/// 会波及每一个 Flutter 表单页。child 是既有的富内容插槽（权限树同款），零改动接入。
/// 行内控制器的写法照抄 pages/oms/order_detail_page.dart 的分配弹框（同一仓库既有范式）。
///
/// 数量/单价本地只做「非空」的粗校验，量程与 product_id 解码由后端
/// （OrderController::buildItems）兜底：非法即 422，主表金额恒以明细汇总为准。
class LineItemsEditor extends StatefulWidget {
  final ValueChanged<List<Map<String, dynamic>>> onChanged;
  const LineItemsEditor({super.key, required this.onChanged});

  @override
  State<LineItemsEditor> createState() => _LineItemsEditorState();
}

class _LineRow {
  final TextEditingController pid = TextEditingController();
  final TextEditingController qty = TextEditingController();
  final TextEditingController price = TextEditingController();
  final TextEditingController unit = TextEditingController();

  void dispose() {
    pid.dispose();
    qty.dispose();
    price.dispose();
    unit.dispose();
  }
}

class _LineItemsEditorState extends State<LineItemsEditor> {
  final List<_LineRow> _rows = [];

  @override
  void initState() {
    super.initState();
    _rows.add(_LineRow()); // 至少一行，用户不必先点「添加」
  }

  @override
  void dispose() {
    for (final r in _rows) {
      r.dispose();
    }
    super.dispose();
  }

  /// 汇总非空行：product_id 是商品下拉同款的 hashid 串，原样上送（后端 decodeFlexibleId 解，
  /// 垃圾串 422）；quantity 恒下发（后端 required|gt:0，缺键会报「不能为空」而非「必须大于 0」）
  void _emit() {
    final out = <Map<String, dynamic>>[];
    for (final r in _rows) {
      final pid = r.pid.text.trim();
      if (pid.isEmpty) continue; // 空行跳过（与 oms 分配弹框同规则）
      out.add({
        'product_id': pid,
        'quantity': r.qty.text.trim(),
        'price': r.price.text.trim(),
        'unit': r.unit.text.trim(),
      });
    }
    widget.onChanged(out);
  }

  void _addRow() {
    setState(() => _rows.add(_LineRow()));
    _emit();
  }

  void _removeRow(int i) {
    final gone = _rows[i];
    setState(() => _rows.removeAt(i));
    // 行已从列表移除、本帧重建后 TextField 才卸载：立即 dispose 会撞
    // 「used after being disposed」，故延到本帧结束
    // （同 pages/oms/order_detail_page.dart 分配弹框的处理）
    WidgetsBinding.instance.addPostFrameCallback((_) => gone.dispose());
    _emit();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.detailItems, style: const TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        for (var i = 0; i < _rows.length; i++) _row(context, i),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _addRow,
            icon: const Icon(Icons.add, size: 18),
            label: Text(l.commonAdd),
          ),
        ),
      ],
    );
  }

  // 取 l 用 AppL10n.of 而非形参：app_l10n.dart 只 import 不 export app_localizations.dart，
  // 写成形参类型 AppLocalizations 在本文件不可见（Dart 不传递 re-export）
  Widget _row(BuildContext context, int i) {
    final l = AppL10n.of(context);
    final r = _rows[i];
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          children: [
            TextField(
              controller: r.pid,
              // 商品 hashid 含字母：默认键盘，勿用 number
              decoration: InputDecoration(labelText: l.fieldProductId, isDense: true),
              onChanged: (_) => _emit(),
            ),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: r.qty,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(labelText: l.fieldQty, isDense: true),
                    onChanged: (_) => _emit(),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: r.price,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(labelText: l.fieldPrice, isDense: true),
                    onChanged: (_) => _emit(),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: r.unit,
                    decoration: InputDecoration(labelText: l.fieldUnit, isDense: true),
                    onChanged: (_) => _emit(),
                  ),
                ),
                // 首行不可删：删空后编辑器一片空白，用户以为控件坏了
                if (_rows.length > 1)
                  IconButton(
                    tooltip: l.commonDelete,
                    icon: const Icon(Icons.delete_outline, size: 20),
                    onPressed: () => _removeRow(i),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
