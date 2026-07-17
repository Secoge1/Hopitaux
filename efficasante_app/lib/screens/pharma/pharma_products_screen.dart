import 'package:flutter/material.dart';
import '../../services/pharma_api_service.dart';
import '../../utils/pharma_format.dart';
import '../../widgets/barcode_scanner_screen.dart';
import '../../widgets/product_barcode_display.dart';
import 'pharma_product_detail_screen.dart';

class PharmaProductsScreen extends StatefulWidget {
  const PharmaProductsScreen({super.key});

  @override
  State<PharmaProductsScreen> createState() => _PharmaProductsScreenState();
}

class _PharmaProductsScreenState extends State<PharmaProductsScreen> {
  final _searchCtrl = TextEditingController();
  List<dynamic> _products = [];
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _page = 1;
  int _total = 0;
  static const _limit = 20;

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    } else {
      setState(() => _loadingMore = true);
    }

    try {
      final res = await PharmaApiService().getProducts(
        page: _page,
        limit: _limit,
        search: _searchCtrl.text.trim(),
      );
      if (!mounted) return;
      final items = (res['data'] as List<dynamic>?) ?? [];
      final pagination = res['pagination'] as Map<String, dynamic>? ?? {};
      setState(() {
        if (reset) {
          _products = items;
        } else {
          _products = [..._products, ...items];
        }
        _total = _toInt(pagination['total']);
        _loading = false;
        _loadingMore = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _search() {
    _load(reset: true);
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _products.length >= _total) return;
    _page++;
    await _load();
  }

  Future<void> _scanSearch() async {
    final code = await openBarcodeScanner(context);
    if (code == null || !mounted) return;
    _searchCtrl.text = code;
    await _load(reset: true);
    if (_products.length == 1) {
      final id = _toInt((_products.first as Map<String, dynamic>)['id']);
      if (id > 0) _openDetail(id);
    }
  }

  void _openDetail(int id) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => PharmaProductDetailScreen(productId: id)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Produits')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchCtrl,
                    decoration: InputDecoration(
                      hintText: 'Rechercher nom, SKU, code-barres…',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchCtrl.clear();
                          _search();
                        },
                      ),
                    ),
                    textInputAction: TextInputAction.search,
                    onSubmitted: (_) => _search(),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton.filledTonal(
                  tooltip: 'Scanner code-barres',
                  onPressed: _scanSearch,
                  icon: const Icon(Icons.qr_code_scanner),
                ),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? _ErrorView(message: _error!, onRetry: () => _load(reset: true))
                    : _products.isEmpty
                        ? const Center(child: Text('Aucun produit trouvé'))
                        : RefreshIndicator(
                            onRefresh: () => _load(reset: true),
                            child: NotificationListener<ScrollNotification>(
                              onNotification: (n) {
                                if (n.metrics.pixels >= n.metrics.maxScrollExtent - 120) {
                                  _loadMore();
                                }
                                return false;
                              },
                              child: ListView.builder(
                                itemCount: _products.length + (_loadingMore ? 1 : 0),
                                itemBuilder: (context, i) {
                                  if (i >= _products.length) {
                                    return const Padding(
                                      padding: EdgeInsets.all(16),
                                      child: Center(child: CircularProgressIndicator()),
                                    );
                                  }
                                  final p = _products[i] as Map<String, dynamic>;
                                  final barcode = p['barcode_primary']?.toString() ?? '';
                                  return Card(
                                    margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                                    child: InkWell(
                                      onTap: () => _openDetail(_toInt(p['id'])),
                                      borderRadius: BorderRadius.circular(16),
                                      child: Padding(
                                        padding: const EdgeInsets.all(12),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                CircleAvatar(
                                                  backgroundColor: const Color(0xFFE0F2F1),
                                                  child: Text(
                                                    (p['name']?.toString() ?? '?').substring(0, 1).toUpperCase(),
                                                    style: const TextStyle(color: Color(0xFF0D9488)),
                                                  ),
                                                ),
                                                const SizedBox(width: 12),
                                                Expanded(
                                                  child: Column(
                                                    crossAxisAlignment: CrossAxisAlignment.start,
                                                    children: [
                                                      Text(
                                                        p['name']?.toString() ?? '—',
                                                        style: const TextStyle(fontWeight: FontWeight.w600),
                                                      ),
                                                      Text('SKU ${p['sku'] ?? '—'}'),
                                                      Text(
                                                        '${PharmaFormat.money(_toDouble(p['sale_price']))} · Stock ${p['stock_total'] ?? p['stock_available'] ?? 0}',
                                                        style: Theme.of(context).textTheme.bodySmall,
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                                const Icon(Icons.chevron_right),
                                              ],
                                            ),
                                            if (barcode.isNotEmpty) ...[
                                              const SizedBox(height: 10),
                                              ProductBarcodeDisplay(code: barcode, height: 48, showLabel: false),
                                            ],
                                          ],
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
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

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
