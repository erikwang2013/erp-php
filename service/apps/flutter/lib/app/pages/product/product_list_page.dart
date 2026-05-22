/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class ProductListPage extends StatefulWidget {
  const ProductListPage({super.key});
  @override
  State<ProductListPage> createState() => _ProductListPageState();
}

class _ProductListPageState extends State<ProductListPage> {
  List<dynamic> _items = [];
  int _total = 0;
  bool _loading = true;
  int _page = 1;
  final int _limit = 20;
  String _keyword = '';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.get(
        '/admin/product?page=$_page&limit=$_limit&keyword=$_keyword'
      );
      final data = res['data'];
      setState(() {
        _items = data['list'] ?? [];
        _total = data['total'] ?? 0;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('商品管理')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              itemCount: _items.length,
              itemBuilder: (context, i) {
                final item = _items[i];
                return ListTile(
                  leading: const Icon(Icons.inventory_2),
                  title: Text(item['name'] ?? ''),
                  subtitle: Text(item['code'] ?? ''),
                  trailing: Text(item['spec'] ?? ''),
                );
              },
            ),
    );
  }
}
