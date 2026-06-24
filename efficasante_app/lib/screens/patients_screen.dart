import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'patient_detail_screen.dart';

class PatientsScreen extends StatefulWidget {
  const PatientsScreen({super.key});

  @override
  State<PatientsScreen> createState() => _PatientsScreenState();
}

class _PatientsScreenState extends State<PatientsScreen> {
  List<dynamic> _list = [];
  bool _loading = true;
  String? _error;
  int _page = 1;
  int _totalPages = 1;
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _load();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
            _scrollController.position.maxScrollExtent - 200 &&
        !_loading &&
        _page < _totalPages) {
      _loadMore();
    }
  }

  Future<void> _load({bool reset = true}) async {
    setState(() {
      _loading = true;
      _error = null;
      if (reset) _page = 1;
    });
    try {
      final res = await ApiService().getPatients(
        page: _page,
        limit: 20,
        search: _searchController.text.trim(),
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

  Future<void> _loadMore() async {
    if (_loading || _page >= _totalPages) return;
    setState(() => _page++);
    await _load(reset: false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Patients'),
        actions: [
          IconButton(
            icon: const Icon(Icons.search),
            onPressed: () => _showSearch(),
          ),
        ],
      ),
      body: Column(
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      _error!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                  TextButton(
                    onPressed: () => _load(),
                    child: const Text('Réessayer'),
                  ),
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
                            Icon(Icons.person_off,
                                size: 64, color: Colors.grey.shade400),
                            const SizedBox(height: 16),
                            Text(
                              'Aucun patient',
                              style: TextStyle(color: Colors.grey.shade600),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _load(),
                        child: ListView.builder(
                          controller: _scrollController,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          itemCount: _list.length + (_page < _totalPages ? 1 : 0),
                          itemBuilder: (context, i) {
                            if (i == _list.length) {
                              return const Padding(
                                padding: EdgeInsets.all(16),
                                child: Center(child: CircularProgressIndicator()),
                              );
                            }
                            final p = _list[i] as Map<String, dynamic>;
                            final nom = p['nom'] as String? ?? '';
                            final prenom = p['prenom'] as String? ?? '';
                            final dossier = p['numero_dossier'] as String? ?? '';
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                                  child: Text(
                                    (prenom.isNotEmpty ? prenom[0] : nom.isNotEmpty ? nom[0] : '?').toUpperCase(),
                                    style: TextStyle(
                                      color: Theme.of(context).colorScheme.onPrimaryContainer,
                                    ),
                                  ),
                                ),
                                title: Text('$prenom $nom'),
                                subtitle: dossier.isNotEmpty ? Text('Dossier: $dossier') : null,
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () {
                                  final id = p['id'];
                                  if (id != null) {
                                    Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) => PatientDetailScreen(patientId: id is int ? id : int.tryParse(id.toString()) ?? 0),
                                      ),
                                    );
                                  }
                                },
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

  void _showSearch() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Rechercher un patient'),
        content: TextField(
          controller: _searchController,
          decoration: const InputDecoration(
            hintText: 'Nom, prénom ou n° dossier',
          ),
          onSubmitted: (_) {
            Navigator.pop(ctx);
            _load();
          },
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(ctx);
              _load();
            },
            child: const Text('Rechercher'),
          ),
        ],
      ),
    );
  }
}
