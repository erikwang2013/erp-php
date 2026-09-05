// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:file_saver/file_saver.dart';
import 'api_service.dart';

class ExportService {
  final Dio _dio;

  ExportService(this._dio);

  Future<void> exportExcel({
    required String table,
    required List<String> columns,
    Map<String, dynamic>? conditions,
  }) async {
    final response = await _dio.post(
      '/admin/v1/export/excel',
      data: {
        'table': table,
        'columns': columns,
        'conditions': conditions ?? {},
      },
      options: Options(responseType: ResponseType.bytes),
    );

    final filename = 'export_${table}_${DateTime.now().millisecondsSinceEpoch}.xlsx';
    final bytes = _fileOrThrow(response, prefix: 'PK'); // xlsx 为 ZIP 容器，必以 PK 头起始
    await FileSaver.instance.saveFile(name: filename, bytes: bytes, ext: 'xlsx');
  }

  Future<void> exportPdf({
    required String type,
    required String title,
    required Map<String, dynamic> data,
  }) async {
    final response = await _dio.post(
      '/admin/v1/export/pdf',
      data: {
        'type': type,
        'title': title,
        'data': data,
      },
      options: Options(responseType: ResponseType.bytes),
    );

    final filename = 'export_${type}_${DateTime.now().millisecondsSinceEpoch}.pdf';
    final bytes = _fileOrThrow(response, prefix: '%PDF');
    await FileSaver.instance.saveFile(name: filename, bytes: bytes, ext: 'pdf');
  }

  /// 落盘前的文件完整性校验：业务错误 JSON 或网关错误页常以 HTTP 200 返回，
  /// 此前会被原样保存成损坏的 .xlsx/.pdf。magic 头（xlsx=PK / pdf=%PDF）校验
  /// 可拦截一切非目标格式的响应；失败时尽力带出服务端 message（错误 JSON），
  /// 否则走 ApiService 通用翻译。
  static Uint8List _fileOrThrow(Response resp, {required String prefix}) {
    final data = resp.data;
    if (data is List<int> && data.length >= prefix.length) {
      final head = latin1.decode(data.sublist(0, prefix.length));
      if (head == prefix) {
        return data is Uint8List ? data : Uint8List.fromList(data);
      }
    }

    String? message;
    if (data is List<int>) {
      try {
        final body = jsonDecode(utf8.decode(data, allowMalformed: true));
        if (body is Map && body['message'] is String) message = body['message'] as String;
      } catch (_) {
        // 非 JSON（如 HTML 网关页）：用通用文案即可
      }
    }
    throw ApiException(-1, message ??
        ApiService.friendlyError(DioException(
          requestOptions: resp.requestOptions,
          response: resp,
          type: DioExceptionType.badResponse,
        )));
  }
}
