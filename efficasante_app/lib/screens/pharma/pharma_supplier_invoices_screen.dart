import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../services/pharma_api_service.dart';
import '../../utils/pharma_format.dart';

class PharmaSupplierInvoicesScreen extends StatefulWidget {
  const PharmaSupplierInvoicesScreen({super.key});

  @override
  State<PharmaSupplierInvoicesScreen> createState() => _PharmaSupplierInvoicesScreenState();
}

class _PharmaSupplierInvoicesScreenState extends State<PharmaSupplierInvoicesScreen> {
  List<dynamic> _items = [];
  Map<String, dynamic>? _summary;
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
      final res = await PharmaApiService().getSupplierInvoices();
      if (!mounted) return;
      setState(() {
        _items = (res['data'] as List<dynamic>?) ?? [];
        _summary = res['summary'] as Map<String, dynamic>?;
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
    final role = AuthService().userRole;
    if (role != 'admin' && role != 'comptable' && role != 'pharmacien' && role != 'pharma_manager') {
      return Scaffold(
        appBar: AppBar(title: const Text('Factures fournisseur')),
        body: const Center(child: Text('Accès réservé aux rôles achats/comptabilité')),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Factures fournisseur')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(12),
                    children: [
                      if (_summary != null)
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Text(
                              'Reste dû : ${PharmaFormat.money(_toDouble(_summary!['total_due']))}',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                        ),
                      if (_items.isEmpty)
                        const Padding(
                          padding: EdgeInsets.all(24),
                          child: Center(child: Text('Aucune facture')),
                        )
                      else
                        ..._items.map((item) {
                          final inv = item as Map<String, dynamic>;
                          final due = _toDouble(inv['amount_ttc']) - _toDouble(inv['amount_paid']);
                          return Card(
                            child: ListTile(
                              title: Text(inv['invoice_number']?.toString() ?? 'Facture'),
                              subtitle: Text('${inv['supplier_name'] ?? '—'} · ${inv['status'] ?? ''}'),
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    PharmaFormat.money(_toDouble(inv['amount_ttc'])),
                                    style: const TextStyle(fontWeight: FontWeight.w600),
                                  ),
                                  if (due > 0)
                                    Text(
                                      'Dû ${PharmaFormat.money(due)}',
                                      style: TextStyle(color: Colors.orange.shade800, fontSize: 12),
                                    ),
                                ],
                              ),
                            ),
                          );
                        }),
                    ],
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
