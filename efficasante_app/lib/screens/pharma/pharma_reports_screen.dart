import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import '../../services/pharma_api_service.dart';
import '../../utils/pharma_format.dart';

class PharmaReportsScreen extends StatefulWidget {
  const PharmaReportsScreen({super.key});

  @override
  State<PharmaReportsScreen> createState() => _PharmaReportsScreenState();
}

class _PharmaReportsScreenState extends State<PharmaReportsScreen> {
  late DateTime _dateFrom;
  late DateTime _dateTo;
  Map<String, dynamic>? _bilan;
  bool _loading = true;
  bool _downloading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _dateFrom = DateTime(now.year, 1, 1);
    _dateTo = now;
    _load();
  }

  String get _dateFromStr => DateFormat('yyyy-MM-dd').format(_dateFrom);
  String get _dateToStr => DateFormat('yyyy-MM-dd').format(_dateTo);

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await PharmaApiService().getBilanReport(
        dateFrom: _dateFromStr,
        dateTo: _dateToStr,
      );
      if (!mounted) return;
      setState(() {
        _bilan = res['data'] as Map<String, dynamic>?;
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

  Future<void> _pickDate({required bool from}) async {
    final initial = from ? _dateFrom : _dateTo;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked == null) return;
    setState(() {
      if (from) {
        _dateFrom = picked;
      } else {
        _dateTo = picked;
      }
    });
    await _load();
  }

  Future<void> _downloadPdf(String path, String filename) async {
    setState(() => _downloading = true);
    try {
      final bytes = await PharmaApiService().downloadReportPdf(
        path,
        dateFrom: _dateFromStr,
        dateTo: _dateToStr,
      );
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/$filename');
      await file.writeAsBytes(bytes);
      if (!mounted) return;
      await OpenFilex.open(file.path);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('PDF ouvert : $filename')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rapports comptables')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(_error!, textAlign: TextAlign.center),
                      ),
                      FilledButton(onPressed: _load, child: const Text('Réessayer')),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Text('Période', style: TextStyle(fontWeight: FontWeight.bold)),
                            const SizedBox(height: 12),
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: const Text('Du'),
                              subtitle: Text(DateFormat('dd/MM/yyyy').format(_dateFrom)),
                              trailing: const Icon(Icons.calendar_today),
                              onTap: () => _pickDate(from: true),
                            ),
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: const Text('Au'),
                              subtitle: Text(DateFormat('dd/MM/yyyy').format(_dateTo)),
                              trailing: const Icon(Icons.calendar_today),
                              onTap: () => _pickDate(from: false),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_bilan != null) ...[
                      _Kpi('Total actif', PharmaFormat.money(_num(_bilan!, 'actif'))),
                      _Kpi('Total passif', PharmaFormat.money(_num(_bilan!, 'passif'))),
                      _Kpi('Résultat net', PharmaFormat.money(_num(_bilan!, 'resultat'))),
                      _Kpi(
                        'Équilibre',
                        (_bilan!['equilibre'] == true) ? 'OK' : 'Écart',
                      ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton.icon(
                      onPressed: _downloading
                          ? null
                          : () => _downloadPdf(
                                'reports/bilan/pdf',
                                'bilan_${_dateFromStr}_${_dateToStr}.pdf',
                              ),
                      icon: _downloading
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.picture_as_pdf),
                      label: const Text('Télécharger Bilan PDF'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _downloading
                          ? null
                          : () => _downloadPdf(
                                'reports/grand_livre/pdf',
                                'grand_livre_${_dateFromStr}_${_dateToStr}.pdf',
                              ),
                      icon: const Icon(Icons.menu_book),
                      label: const Text('Télécharger Grand Livre PDF'),
                    ),
                  ],
                ),
    );
  }

  static double _num(Map<String, dynamic> m, String key) {
    final v = m[key];
    if (v is double) return v;
    if (v is int) return v.toDouble();
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}

class _Kpi extends StatelessWidget {
  final String label;
  final String value;

  const _Kpi(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(label),
        trailing: Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
      ),
    );
  }
}
