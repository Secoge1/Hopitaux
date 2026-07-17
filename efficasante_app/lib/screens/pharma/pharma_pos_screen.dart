import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../services/pharma_api_service.dart';
import '../../utils/pharma_format.dart';
import '../../widgets/barcode_scanner_screen.dart';

class _CartLine {
  final int productId;
  final String name;
  final double unitPrice;
  int quantity;

  _CartLine({
    required this.productId,
    required this.name,
    required this.unitPrice,
  }) : quantity = 1;

  double get total => unitPrice * quantity;

  Map<String, dynamic> toApiLine() => {
        'product_id': productId,
        'quantity': quantity,
        'unit_price': unitPrice,
      };
}

class PharmaPosScreen extends StatefulWidget {
  const PharmaPosScreen({super.key});

  @override
  State<PharmaPosScreen> createState() => _PharmaPosScreenState();
}

class _PharmaPosScreenState extends State<PharmaPosScreen> {
  final _barcodeCtrl = TextEditingController();
  final _barcodeFocus = FocusNode();
  final List<_CartLine> _cart = [];
  bool _searching = false;
  bool _submitting = false;
  String? _message;
  String _paymentMethod = 'cash';

  double get _total => _cart.fold(0.0, (sum, l) => sum + l.total);

  @override
  void dispose() {
    _barcodeCtrl.dispose();
    _barcodeFocus.dispose();
    super.dispose();
  }

  Future<void> _openScanner() async {
    final code = await openBarcodeScanner(context);
    if (code != null && mounted) {
      await _lookupBarcode(code);
    }
  }

  Future<void> _lookupBarcode([String? raw]) async {
    final code = (raw ?? _barcodeCtrl.text).trim();
    if (code.isEmpty) return;

    setState(() {
      _searching = true;
      _message = null;
    });

    try {
      final res = await PharmaApiService().getProductByBarcode(code);
      final product = res['data'] as Map<String, dynamic>?;
      if (product == null) {
        setState(() => _message = 'Produit introuvable');
        return;
      }
      _addProduct(product);
      _barcodeCtrl.clear();
      _barcodeFocus.requestFocus();
    } catch (e) {
      setState(() => _message = e.toString().replaceAll('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  void _addProduct(Map<String, dynamic> product) {
    final id = _toInt(product['id']);
    if (id == 0) return;

    final price = _toDouble(product['sale_price']);
    final name = product['name']?.toString() ?? 'Produit';
    final existing = _cart.where((l) => l.productId == id).toList();

    setState(() {
      if (existing.isNotEmpty) {
        existing.first.quantity++;
      } else {
        _cart.add(_CartLine(productId: id, name: name, unitPrice: price));
      }
      _message = null;
    });
  }

  Future<void> _checkout() async {
    if (_cart.isEmpty) return;

    final paidCtrl = TextEditingController(text: _total.toStringAsFixed(0));
    var method = _paymentMethod;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
        title: const Text('Encaisser'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Total : ${PharmaFormat.money(_total)}'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: method,
              decoration: const InputDecoration(labelText: 'Mode de paiement'),
              items: const [
                DropdownMenuItem(value: 'cash', child: Text('Espèces')),
                DropdownMenuItem(value: 'mobile_money', child: Text('Mobile Money')),
                DropdownMenuItem(value: 'bank', child: Text('Banque')),
                DropdownMenuItem(value: 'card', child: Text('Carte')),
              ],
              onChanged: (v) => setDialogState(() => method = v ?? 'cash'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: paidCtrl,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                labelText: 'Montant reçu (FCFA)',
                prefixIcon: Icon(Icons.payments),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Valider')),
        ],
      ),
      ),
    );

    if (ok != true || !mounted) return;

    final amountPaid = double.tryParse(paidCtrl.text) ?? _total;
    paidCtrl.dispose();

    if (amountPaid < _total) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Montant insuffisant')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final res = await PharmaApiService().createSale(
        lines: _cart.map((l) => l.toApiLine()).toList(),
        amountPaid: amountPaid,
        paymentMethod: method,
      );
      if (mounted) setState(() => _paymentMethod = method);
      final sale = res['sale'] as Map<String, dynamic>?;
      if (!mounted) return;
      setState(() {
        _cart.clear();
        _submitting = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Vente ${sale?['sale_number'] ?? ''} enregistrée — ${PharmaFormat.money(_toDouble(sale?['total_ttc'] ?? _total))}',
          ),
          backgroundColor: Colors.green.shade700,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Caisse POS'),
        actions: [
          if (_cart.isNotEmpty)
            TextButton(
              onPressed: _submitting ? null : () => setState(_cart.clear),
              child: const Text('Vider'),
            ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _barcodeCtrl,
                    focusNode: _barcodeFocus,
                    decoration: InputDecoration(
                      labelText: 'Code-barres ou SKU',
                      prefixIcon: const Icon(Icons.qr_code),
                      suffixIcon: _searching
                          ? const Padding(
                              padding: EdgeInsets.all(12),
                              child: SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              ),
                            )
                          : IconButton(
                              icon: const Icon(Icons.search),
                              onPressed: () => _lookupBarcode(),
                            ),
                    ),
                    textInputAction: TextInputAction.search,
                    onSubmitted: _lookupBarcode,
                  ),
                ),
                const SizedBox(width: 8),
                FilledButton.tonalIcon(
                  onPressed: _searching ? null : _openScanner,
                  icon: const Icon(Icons.qr_code_scanner),
                  label: const Text('Scan'),
                ),
              ],
            ),
          ),
          if (_message != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(_message!, style: TextStyle(color: Colors.red.shade700)),
            ),
          Expanded(
            child: _cart.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.shopping_cart_outlined, size: 64, color: Colors.grey.shade400),
                        const SizedBox(height: 12),
                        Text(
                          'Scannez ou saisissez un code-barres',
                          style: TextStyle(color: Colors.grey.shade600),
                        ),
                      ],
                    ),
                  )
                : ListView.builder(
                    itemCount: _cart.length,
                    itemBuilder: (context, i) {
                      final line = _cart[i];
                      return Card(
                        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                        child: ListTile(
                          title: Text(line.name),
                          subtitle: Text('${PharmaFormat.money(line.unitPrice)} × ${line.quantity}'),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              IconButton(
                                icon: const Icon(Icons.remove_circle_outline),
                                onPressed: () {
                                  setState(() {
                                    if (line.quantity > 1) {
                                      line.quantity--;
                                    } else {
                                      _cart.removeAt(i);
                                    }
                                  });
                                },
                              ),
                              Text('${line.quantity}'),
                              IconButton(
                                icon: const Icon(Icons.add_circle_outline),
                                onPressed: () => setState(() => line.quantity++),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
          SafeArea(
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 8,
                    offset: const Offset(0, -2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Total (${_cart.length} ligne${_cart.length > 1 ? 's' : ''})'),
                      Text(
                        PharmaFormat.money(_total),
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: _cart.isEmpty || _submitting ? null : _checkout,
                    icon: _submitting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.check_circle),
                    label: Text(_submitting ? 'En cours…' : 'Encaisser'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    if (v is String) return int.tryParse(v) ?? 0;
    return 0;
  }

  static double _toDouble(dynamic v) {
    if (v is double) return v;
    if (v is int) return v.toDouble();
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}
