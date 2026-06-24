import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';

class RendezVousScreen extends StatefulWidget {
  const RendezVousScreen({super.key});

  @override
  State<RendezVousScreen> createState() => _RendezVousScreenState();
}

class _RendezVousScreenState extends State<RendezVousScreen> {
  List<dynamic> _list = [];
  bool _loading = true;
  String? _error;
  int _page = 1;
  int _totalPages = 1;
  String _filterDate = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool reset = true}) async {
    setState(() {
      _loading = true;
      _error = null;
      if (reset) _page = 1;
    });
    try {
      final res = await ApiService().getRendezVous(
        page: _page,
        limit: 20,
        date: _filterDate,
      );
      if (!mounted) return;
      final data = res['data'] as List<dynamic>? ?? [];
      final pag = res['pagination'] as Map<String, dynamic>? ?? {};
      setState(() {
        if (reset) {
          _list = data;
        } else {
          _list = [..._list, ...data];
        }
        _totalPages = (pag['total_pages'] as num?)?.toInt() ?? 1;
        _loading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('Exception: ', '');
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rendez-vous'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: _pickDate,
          ),
        ],
      ),
      body: Column(
        children: [
          if (_filterDate.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                children: [
                  Chip(
                    label: Text(_filterDate),
                    onDeleted: () {
                      setState(() => _filterDate = '');
                      _load();
                    },
                  ),
                ],
              ),
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(child: Text(_error!, style: const TextStyle(color: Colors.red))),
                  TextButton(onPressed: () => _load(), child: const Text('Réessayer')),
                ],
              ),
            ),
          Expanded(
            child: _loading && _list.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : _list.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.calendar_today, size: 64, color: Colors.grey.shade400),
                            const SizedBox(height: 16),
                            Text('Aucun rendez-vous', style: TextStyle(color: Colors.grey.shade600)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _load(),
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          itemCount: _list.length,
                          itemBuilder: (context, i) {
                            final r = _list[i] as Map<String, dynamic>;
                            final dateRdv = r['date_rdv'] as String?;
                            final patientNom = '${r['patient_prenom'] ?? ''} ${r['patient_nom'] ?? ''}'.trim();
                            final medecinNom = '${r['medecin_prenom'] ?? ''} ${r['medecin_nom'] ?? ''}'.trim();
                            final statut = r['statut'] as String? ?? '';
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: _colorForStatut(statut),
                                  child: Icon(Icons.event, color: Colors.white, size: 20),
                                ),
                                title: Text(patientNom.isNotEmpty ? patientNom : 'Patient'),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    if (dateRdv != null) Text(_formatDate(dateRdv)),
                                    if (medecinNom.isNotEmpty) Text(medecinNom, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                                  ],
                                ),
                                trailing: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: _colorForStatut(statut).withValues(alpha: 0.2),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(statut, style: TextStyle(fontSize: 12, color: _colorForStatut(statut))),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Color _colorForStatut(String s) {
    switch (s.toLowerCase()) {
      case 'confirme':
        return Colors.green;
      case 'annule':
        return Colors.red;
      case 'en_attente':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  String _formatDate(String d) {
    try {
      final dt = DateTime.tryParse(d);
      if (dt != null) return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {}
    return d;
  }

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date != null) {
      setState(() => _filterDate = DateFormat('yyyy-MM-dd').format(date));
      _load();
    }
  }
}
