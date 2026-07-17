import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../services/pharma_api_service.dart';
import '../../utils/pharma_format.dart';
import '../../widgets/barcode_edit_dialog.dart';
import '../../widgets/barcode_scanner_screen.dart';
import '../../widgets/product_barcode_display.dart';

class PharmaProductDetailScreen extends StatefulWidget {
  final int productId;

  const PharmaProductDetailScreen({super.key, required this.productId});

  @override
  State<PharmaProductDetailScreen> createState() => _PharmaProductDetailScreenState();
}

class _PharmaProductDetailScreenState extends State<PharmaProductDetailScreen> {
  Map<String, dynamic>? _product;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  bool get _canEditBarcode {
    final role = AuthService().userRole;
    return role == 'admin' || role == 'pharmacien';
  }

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
      final res = await PharmaApiService().getProductDetail(widget.productId);
      if (!mounted) return;
      setState(() {
        _product = res['data'] as Map<String, dynamic>?;
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

  List<Map<String, dynamic>> _barcodeRows() {
    final rows = <Map<String, dynamic>>[];
    final seen = <String>{};

    void add(String? code, bool primary) {
      final v = code?.trim() ?? '';
      if (v.isEmpty || !seen.add(v)) return;
      rows.add({'barcode': v, 'is_primary': primary});
    }

    add(_product?['barcode_primary']?.toString(), true);
    final extra = _product?['barcodes'];
    if (extra is List) {
      for (final item in extra) {
        if (item is Map) {
          add(item['barcode']?.toString(), (item['is_primary'] ?? 0) == 1);
        }
      }
    }
    return rows;
  }

  Future<void> _runSave(Future<Map<String, dynamic>> Function() action) async {
    setState(() => _saving = true);
    try {
      final res = await action();
      if (!mounted) return;
      setState(() {
        _product = res['data'] as Map<String, dynamic>?;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Code-barres enregistré'), backgroundColor: Colors.green),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    }
  }

  Future<void> _editPrimary() async {
    if (!_canEditBarcode || _product == null) return;
    final code = await showBarcodeEditDialog(
      context,
      title: 'Code-barres principal',
      initialValue: _product!['barcode_primary']?.toString(),
    );
    if (code == null || !mounted) return;
    await _runSave(() => PharmaApiService().setPrimaryBarcode(widget.productId, code));
  }

  Future<void> _addAlternate() async {
    if (!_canEditBarcode) return;
    final code = await showBarcodeEditDialog(
      context,
      title: 'Ajouter un code alternatif',
      confirmLabel: 'Ajouter',
    );
    if (code == null || !mounted) return;
    await _runSave(() => PharmaApiService().addSecondaryBarcode(widget.productId, code));
  }

  Future<void> _removeBarcode(String barcode, bool isPrimary) async {
    if (!_canEditBarcode) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Supprimer le code-barres ?'),
        content: Text(
          isPrimary
              ? 'Le code principal sera supprimé. Un code alternatif deviendra principal s\'il existe.'
              : 'Supprimer le code $barcode ?',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuler')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    await _runSave(() => PharmaApiService().removeBarcode(widget.productId, barcode));
  }

  Future<void> _scanVerify() async {
    final code = await openBarcodeScanner(context);
    if (code == null || !mounted || _product == null) return;

    final codes = _barcodeRows().map((r) => r['barcode'] as String).toSet();
    final match = codes.contains(code);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(match ? 'Code-barres correspondant ✓' : 'Code scanné : $code — ne correspond pas'),
        backgroundColor: match ? Colors.green.shade700 : Colors.orange.shade800,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Fiche produit'),
        actions: [
          if (_canEditBarcode && _product != null)
            IconButton(
              tooltip: 'Modifier code principal',
              icon: _saving
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.edit),
              onPressed: _saving ? null : _editPrimary,
            ),
          IconButton(
            tooltip: 'Vérifier code-barres',
            icon: const Icon(Icons.qr_code_scanner),
            onPressed: _product == null ? null : _scanVerify,
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(onPressed: _load, child: const Text('Réessayer')),
                    ],
                  ),
                )
              : _buildContent(context),
    );
  }

  Widget _buildContent(BuildContext context) {
    final p = _product!;
    final barcodes = _barcodeRows();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          p['name']?.toString() ?? 'Produit',
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
        ),
        if (p['generic_name'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(p['generic_name'].toString(), style: TextStyle(color: Colors.grey.shade700)),
          ),
        const SizedBox(height: 16),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _row('SKU', p['sku']?.toString() ?? '—'),
                _row('Prix vente', PharmaFormat.money(_toDouble(p['sale_price']))),
                _row('Stock disponible', '${p['stock_available'] ?? p['stock_total'] ?? 0}'),
                _row('Statut', p['status']?.toString() ?? '—'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            Expanded(
              child: Text(
                'Codes-barres',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
            ),
            if (_canEditBarcode)
              TextButton.icon(
                onPressed: _saving ? null : _editPrimary,
                icon: const Icon(Icons.edit, size: 18),
                label: const Text('Modifier'),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (barcodes.isEmpty)
          const ProductBarcodeDisplay(code: '')
        else
          ...barcodes.map((row) {
            final code = row['barcode'] as String;
            final primary = row['is_primary'] as bool;
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        if (primary)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0D9488).withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'Principal',
                              style: TextStyle(color: Color(0xFF0D9488), fontSize: 12, fontWeight: FontWeight.w600),
                            ),
                          ),
                        const Spacer(),
                        if (_canEditBarcode)
                          IconButton(
                            tooltip: 'Supprimer',
                            icon: const Icon(Icons.delete_outline, color: Colors.red),
                            onPressed: _saving ? null : () => _removeBarcode(code, primary),
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    ProductBarcodeDisplay(code: code),
                  ],
                ),
              ),
            );
          }),
        const SizedBox(height: 8),
        if (_canEditBarcode) ...[
          FilledButton.tonalIcon(
            onPressed: _saving ? null : _addAlternate,
            icon: const Icon(Icons.add),
            label: const Text('Ajouter un code alternatif'),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: _saving ? null : _editPrimary,
            icon: const Icon(Icons.qr_code_2),
            label: Text(
              (p['barcode_primary']?.toString() ?? '').isEmpty
                  ? 'Définir le code principal'
                  : 'Changer le code principal',
            ),
          ),
        ],
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: _scanVerify,
          icon: const Icon(Icons.qr_code_scanner),
          label: const Text('Scanner pour vérifier'),
        ),
      ],
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 120, child: Text(label, style: TextStyle(color: Colors.grey.shade600))),
          Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w500))),
        ],
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
