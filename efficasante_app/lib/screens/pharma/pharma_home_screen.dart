import 'package:flutter/material.dart';
import '../../services/pharma_api_service.dart';
import '../../services/auth_service.dart';
import '../../utils/pharma_format.dart';
import 'pharma_reports_screen.dart';
import 'pharma_customers_screen.dart';
import 'pharma_returns_screen.dart';
import 'pharma_supplier_invoices_screen.dart';

class PharmaHomeScreen extends StatefulWidget {
  const PharmaHomeScreen({super.key});

  @override
  State<PharmaHomeScreen> createState() => _PharmaHomeScreenState();
}

class _PharmaHomeScreenState extends State<PharmaHomeScreen> {
  Map<String, dynamic>? _stats;
  List<dynamic> _recentSales = [];
  List<dynamic> _lowStock = [];
  List<dynamic> _expiry = [];
  bool _loading = true;
  String? _error;

  bool get _canViewReports {
    final role = AuthService().userRole;
    return role == 'admin' || role == 'comptable';
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
      final api = PharmaApiService();
      final results = await Future.wait([
        api.getDashboard(),
        api.getRecentSales(),
        api.getStockAlerts(),
      ]);
      if (!mounted) return;
      setState(() {
        _stats = results[0]['data'] as Map<String, dynamic>?;
        _recentSales = (results[1]['data'] as List<dynamic>?) ?? [];
        final alerts = results[2]['data'] as Map<String, dynamic>? ?? {};
        _lowStock = (alerts['low_stock'] as List<dynamic>?) ?? [];
        _expiry = (alerts['expiry'] as List<dynamic>?) ?? [];
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
      body: RefreshIndicator(
        onRefresh: _load,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverAppBar(
              expandedHeight: 120,
              pinned: true,
              actions: [
                if (_canViewReports)
                  IconButton(
                    tooltip: 'Rapports PDF',
                    icon: const Icon(Icons.picture_as_pdf, color: Colors.white),
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const PharmaReportsScreen()),
                      );
                    },
                  ),
              ],
              flexibleSpace: FlexibleSpaceBar(
                title: const Text(
                  'PharmaPro',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                background: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFF0D9488), Color(0xFF0F766E)],
                    ),
                  ),
                ),
              ),
            ),
            if (_loading)
              const SliverFillRemaining(
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              SliverFillRemaining(
                child: _ErrorState(message: _error!, onRetry: _load),
              )
            else ...[
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 1.15,
                  ),
                  delegate: SliverChildListDelegate([
                    _KpiCard(
                      label: 'CA aujourd\'hui',
                      value: PharmaFormat.money(_num(_stats, 'sales_today')),
                      icon: Icons.payments,
                      color: const Color(0xFF0D9488),
                    ),
                    _KpiCard(
                      label: 'Transactions',
                      value: '${_int(_stats, 'transactions_today')}',
                      icon: Icons.receipt_long,
                      color: const Color(0xFF6366F1),
                    ),
                    _KpiCard(
                      label: 'Produits actifs',
                      value: '${_int(_stats, 'products_active')}',
                      icon: Icons.medication,
                      color: const Color(0xFF8B5CF6),
                    ),
                    _KpiCard(
                      label: 'Stock faible',
                      value: '${_int(_stats, 'low_stock')}',
                      icon: Icons.warning_amber,
                      color: const Color(0xFFF59E0B),
                    ),
                  ]),
                ),
              ),
              if (_lowStock.isNotEmpty || _expiry.isNotEmpty)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                    child: Text(
                      'Alertes stock',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                  ),
                ),
              SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, i) {
                    if (i < _lowStock.length) {
                      final p = _lowStock[i] as Map<String, dynamic>;
                      return _AlertTile(
                        icon: Icons.inventory,
                        title: p['name']?.toString() ?? 'Produit',
                        subtitle: 'Stock : ${p['stock_available'] ?? p['available_qty'] ?? 0}',
                        color: Colors.orange,
                      );
                    }
                    final j = i - _lowStock.length;
                    final p = _expiry[j] as Map<String, dynamic>;
                    return _AlertTile(
                      icon: Icons.event_busy,
                      title: p['product_name']?.toString() ?? p['name']?.toString() ?? 'Lot',
                      subtitle: 'Expire : ${p['expiry_date'] ?? '—'}',
                      color: Colors.red,
                    );
                  },
                  childCount: _lowStock.length + _expiry.length,
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      ActionChip(
                        avatar: const Icon(Icons.people, size: 18),
                        label: const Text('Clients'),
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const PharmaCustomersScreen()),
                        ),
                      ),
                      ActionChip(
                        avatar: const Icon(Icons.undo, size: 18),
                        label: const Text('Retours'),
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const PharmaReturnsScreen()),
                        ),
                      ),
                      ActionChip(
                        avatar: const Icon(Icons.request_quote, size: 18),
                        label: const Text('Factures'),
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const PharmaSupplierInvoicesScreen()),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: Text(
                    'Ventes récentes',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ),
              ),
              if (_recentSales.isEmpty)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: Text('Aucune vente récente')),
                  ),
                )
              else
                SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, i) {
                      final sale = _recentSales[i] as Map<String, dynamic>;
                      return ListTile(
                        leading: const CircleAvatar(
                          backgroundColor: Color(0xFFE0F2F1),
                          child: Icon(Icons.receipt, color: Color(0xFF0D9488)),
                        ),
                        title: Text(sale['sale_number']?.toString() ?? 'Vente'),
                        subtitle: Text(sale['completed_at']?.toString() ?? ''),
                        trailing: Text(
                          PharmaFormat.money(_toDouble(sale['total_ttc'])),
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      );
                    },
                    childCount: _recentSales.length.clamp(0, 10),
                  ),
                ),
              const SliverToBoxAdapter(child: SizedBox(height: 24)),
            ],
          ],
        ),
      ),
    );
  }

  static double _num(Map<String, dynamic>? m, String key) => _toDouble(m?[key]);
  static int _int(Map<String, dynamic>? m, String key) {
    final v = m?[key];
    if (v is int) return v;
    if (v is num) return v.toInt();
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

class _KpiCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _KpiCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 26),
            const Spacer(),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Colors.grey.shade600,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AlertTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;

  const _AlertTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      leading: Icon(icon, color: color),
      title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(subtitle),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.cloud_off, size: 56, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Réessayer'),
            ),
          ],
        ),
      ),
    );
  }
}
