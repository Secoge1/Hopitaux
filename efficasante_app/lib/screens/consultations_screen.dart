import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';

class ConsultationsScreen extends StatefulWidget {
  const ConsultationsScreen({super.key});

  @override
  State<ConsultationsScreen> createState() => _ConsultationsScreenState();
}

class _ConsultationsScreenState extends State<ConsultationsScreen> {
  List<dynamic> _list = [];
  bool _loading = true;
  String? _error;
  int _page = 1;
  int _totalPages = 1;

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
      final res = await ApiService().getConsultations(
        page: _page,
        limit: 20,
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
        title: const Text('Consultations'),
      ),
      body: Column(
        children: [
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
                            Icon(Icons.medical_services, size: 64, color: Colors.grey.shade400),
                            const SizedBox(height: 16),
                            Text('Aucune consultation', style: TextStyle(color: Colors.grey.shade600)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _load(),
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          itemCount: _list.length,
                          itemBuilder: (context, i) {
                            final c = _list[i] as Map<String, dynamic>;
                            final dateConsult = c['date_consultation'] as String?;
                            final patientNom = '${c['patient_prenom'] ?? ''} ${c['patient_nom'] ?? ''}'.trim();
                            final medecinNom = '${c['medecin_prenom'] ?? ''} ${c['medecin_nom'] ?? ''}'.trim();
                            final motif = c['motif'] as String? ?? '';
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                                  child: Icon(Icons.medical_services, color: Theme.of(context).colorScheme.onPrimaryContainer),
                                ),
                                title: Text(patientNom.isNotEmpty ? patientNom : 'Patient'),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    if (dateConsult != null) Text(_formatDate(dateConsult)),
                                    if (medecinNom.isNotEmpty) Text(medecinNom, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                                    if (motif.isNotEmpty) Text(motif, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
                                  ],
                                ),
                                isThreeLine: true,
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

  String _formatDate(String d) {
    try {
      final dt = DateTime.tryParse(d);
      if (dt != null) return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {}
    return d;
  }
}
