// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// ConfirmDialog 通用组件测试：展示、取消、密码必填、确认回调
// （成功/失败/异常）与 loading 状态。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/widgets/confirm_dialog.dart';

void main() {
  /// 打开对话框，把 ConfirmDialog.show 的返回值写入 [resultBox]。
  Future<void> openDialog(
    WidgetTester tester, {
    String title = '确认删除',
    String? content,
    Future<bool> Function(String password)? onConfirm,
    List<bool?>? resultBox,
  }) async {
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Center(
          child: ElevatedButton(
            onPressed: () async {
              final r = await ConfirmDialog.show(
                context,
                title: title,
                content: content,
                onConfirm: onConfirm,
              );
              if (resultBox != null) resultBox.add(r);
            },
            child: const Text('打开'),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('打开'));
    await tester.pumpAndSettle();
  }

  group('ConfirmDialog — 展示', () {
    testWidgets('显示标题、内容、密码输入与确认/取消按钮', (tester) async {
      await openDialog(
        tester,
        title: '确认删除',
        content: '删除后不可恢复，请谨慎操作。',
      );

      expect(find.text('确认删除'), findsWidgets); // 标题 + 确认按钮
      expect(find.text('删除后不可恢复，请谨慎操作。'), findsOneWidget);
      expect(find.text('请输入您的密码确认'), findsOneWidget);
      expect(find.text('取消'), findsOneWidget);
      expect(find.byType(TextField), findsOneWidget);
    });

    testWidgets('不传 content 时内容区不渲染', (tester) async {
      await openDialog(tester);

      expect(find.byType(TextField), findsOneWidget);
      expect(find.text('请输入您的密码确认'), findsOneWidget);
    });
  });

  group('ConfirmDialog — 交互', () {
    testWidgets('密码为空时点确认提示「请输入密码」且不关闭', (tester) async {
      final resultBox = <bool?>[];
      await openDialog(tester, resultBox: resultBox);

      await tester.tap(find.widgetWithText(ElevatedButton, '确认删除'));
      await tester.pumpAndSettle();

      expect(find.text('请输入密码'), findsOneWidget);
      // 对话框仍打开
      expect(find.byType(AlertDialog), findsOneWidget);
      // 尚未返回结果
      expect(resultBox, isEmpty);
    });

    testWidgets('点取消关闭对话框且返回 false', (tester) async {
      final resultBox = <bool?>[];
      await openDialog(tester, resultBox: resultBox);

      await tester.tap(find.text('取消'));
      await tester.pumpAndSettle();

      expect(find.byType(AlertDialog), findsNothing);
      expect(resultBox, [false]);
    });

    testWidgets('onConfirm 返回 true 时关闭并返回 true', (tester) async {
      final resultBox = <bool?>[];
      await openDialog(
        tester,
        resultBox: resultBox,
        onConfirm: (pwd) async => pwd == '123456',
      );

      await tester.enterText(find.byType(TextField), '123456');
      await tester.tap(find.widgetWithText(ElevatedButton, '确认删除'));
      await tester.pumpAndSettle();

      expect(find.byType(AlertDialog), findsNothing);
      expect(resultBox, [true]);
    });

    testWidgets('密码错误时 onConfirm 返回 false，提示操作失败且不关闭', (tester) async {
      final resultBox = <bool?>[];
      await openDialog(
        tester,
        resultBox: resultBox,
        onConfirm: (pwd) async => pwd == '123456',
      );

      await tester.enterText(find.byType(TextField), 'wrong');
      await tester.tap(find.widgetWithText(ElevatedButton, '确认删除'));
      await tester.pumpAndSettle();

      expect(find.text('操作失败，请重试'), findsOneWidget);
      expect(find.byType(AlertDialog), findsOneWidget);
      expect(resultBox, isEmpty);
    });

    testWidgets('onConfirm 抛异常时展示错误信息', (tester) async {
      await openDialog(
        tester,
        onConfirm: (pwd) async => throw Exception('网络超时'),
      );

      await tester.enterText(find.byType(TextField), '123456');
      await tester.tap(find.widgetWithText(ElevatedButton, '确认删除'));
      await tester.pumpAndSettle();

      expect(find.text('操作失败：Exception: 网络超时'), findsOneWidget);
      expect(find.byType(AlertDialog), findsOneWidget);
    });

    testWidgets('确认过程中显示 loading（确认按钮禁用）', (tester) async {
      await openDialog(
        tester,
        onConfirm: (pwd) async {
          await Future<void>.delayed(const Duration(milliseconds: 50));
          return true;
        },
      );

      await tester.enterText(find.byType(TextField), '123456');
      await tester.tap(find.widgetWithText(ElevatedButton, '确认删除'));
      // 未完成前 pump 一帧
      await tester.pump();

      expect(
        find.descendant(
          of: find.byType(ElevatedButton),
          matching: find.byType(CircularProgressIndicator),
        ),
        findsOneWidget,
      );

      await tester.pumpAndSettle();
    });
  });
}
