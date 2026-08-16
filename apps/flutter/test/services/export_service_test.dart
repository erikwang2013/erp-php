// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// ExportService 单元测试：验证导出请求的构造（URL、请求体、bytes 响应类型）。
// 请求层用 FakeHttpClientAdapter 离线模拟；FileSaver 落盘依赖宿主平台
// （xdg-user-dir / 系统下载目录），测试只关心请求是否正确发出。
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:admin_app/app/services/export_service.dart';

import '../helpers/fake_http_client_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late Dio dio;
  late FakeHttpClientAdapter adapter;
  late ExportService service;

  setUp(() {
    adapter = FakeHttpClientAdapter();
    dio = Dio()..httpClientAdapter = adapter;
    service = ExportService(dio);
  });

  group('ExportService — 请求构造', () {
    test('exportExcel 发出正确的请求（路径/请求体/bytes 响应）', () async {
      adapter.routes['/admin/export/excel'] = (options) async =>
          FakeHttpClientAdapter.bytesResponse(Uint8List.fromList([1, 2, 3]));

      // FileSaver 落盘依赖宿主平台，可能成功或抛异常，这里宽容处理，
      // 断言重点在请求是否按约定发出。
      try {
        await service.exportExcel(
          table: 'user',
          columns: ['id', 'username'],
          conditions: {'status': 1},
        );
      } on Exception {
        // 忽略平台差异（如无可用下载目录）
      }

      expect(adapter.requests, hasLength(1));
      final req = adapter.requests.single;
      expect(req.path, '/admin/export/excel');
      expect(req.responseType, ResponseType.bytes);

      final data = req.data as Map<String, dynamic>;
      expect(data['table'], 'user');
      expect(data['columns'], ['id', 'username']);
      expect(data['conditions'], {'status': 1});
    });

    test('exportExcel 未传 conditions 时默认空对象', () async {
      adapter.routes['/admin/export/excel'] = (options) async =>
          FakeHttpClientAdapter.bytesResponse(Uint8List.fromList([1]));

      try {
        await service.exportExcel(table: 'role', columns: ['id']);
      } on Exception {
        // 同上：忽略 FileSaver 平台差异
      }

      final data = adapter.requests.single.data as Map<String, dynamic>;
      expect(data['conditions'], isEmpty);
    });

    test('exportPdf 发出正确的请求（类型/标题/数据/bytes 响应）', () async {
      adapter.routes['/admin/export/pdf'] = (options) async =>
          FakeHttpClientAdapter.bytesResponse(Uint8List.fromList([9, 9]));

      try {
        await service.exportPdf(
          type: 'dashboard',
          title: '仪表盘报表',
          data: {'month': '2026-01'},
        );
      } on Exception {
        // 同上：忽略 FileSaver 平台差异
      }

      final req = adapter.requests.single;
      expect(req.path, '/admin/export/pdf');
      expect(req.responseType, ResponseType.bytes);
      final data = req.data as Map<String, dynamic>;
      expect(data['type'], 'dashboard');
      expect(data['title'], '仪表盘报表');
      expect(data['data'], {'month': '2026-01'});
    });

    test('导出请求网络失败时异常向上抛出', () async {
      adapter.fallback = (options) async => throw DioException(
            requestOptions: options,
            type: DioExceptionType.connectionError,
          );

      await expectLater(
        service.exportExcel(table: 'user', columns: ['id']),
        throwsA(isA<DioException>()),
      );
    });
  });
}
