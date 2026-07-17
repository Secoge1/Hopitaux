import 'package:flutter/material.dart';
import '../../services/pharma_api_service.dart';
import '../../utils/pharma_format.dart';

class PharmaReturnsScreen extends StatefulWidget {
  const PharmaReturnsScreen({super.key});

  @override
  State<PharmaReturnsScreen> createState() => _PharmaReturnsScreenState();
}

class _PharmaReturnsScreenState extends State<PharmaReturnsScreen> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await PharmaApiService().getReturns();
      if (!mounted) return;
      setState(() {
        _items = (res['data'] as List<dynamic>?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Retours vente')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : _items.isEmpty
                  ? const Center(child: Text('Aucun retour'))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        itemCount: _items.length,
                        itemBuilder: (context, i) {
                          final r = _items[i] as Map<String, dynamic>;
                          return ListTile(
                            leading: const CircleAvatar(
                              backgroundColor: Color(0xFFFFEBEE),
                              child: Icon(Icons.undo, color: Colors.red),
                            ),
                            title: Text(r['return_number']?.toString() ?? 'Retour'),
                            subtitle: Text(
                              'Vente ${r['sale_number'] ?? '—'} · ${r['customer_name'] ?? '—'}',
                            ),
                            trailing: Text(
                              PharmaFormat.money(_toDouble(r['total_refund'])),
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }

  static double _toDouble(dynamic v) {
    if (v is double) return v;
    if (v is int) return v.toDouble();
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}
