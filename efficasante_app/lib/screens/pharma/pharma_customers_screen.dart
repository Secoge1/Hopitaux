import 'package:flutter/material.dart';
import '../../services/pharma_api_service.dart';

class PharmaCustomersScreen extends StatefulWidget {
  const PharmaCustomersScreen({super.key});

  @override
  State<PharmaCustomersScreen> createState() => _PharmaCustomersScreenState();
}

class _PharmaCustomersScreenState extends State<PharmaCustomersScreen> {
  final _searchCtrl = TextEditingController();
  List<dynamic> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await PharmaApiService().getCustomers(search: _searchCtrl.text.trim());
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

  String _name(Map<String, dynamic> c) {
    if ((c['company_name'] ?? '').toString().isNotEmpty) {
      return c['company_name'].toString();
    }
    return '${c['first_name'] ?? ''} ${c['last_name'] ?? ''}'.trim();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Clients')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchCtrl,
                    decoration: const InputDecoration(
                      hintText: 'Rechercher…',
                      prefixIcon: Icon(Icons.search),
                      border: OutlineInputBorder(),
                      isDense: true,
                    ),
                    onSubmitted: (_) => _load(),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Text(_error!))
                    : _items.isEmpty
                        ? const Center(child: Text('Aucun client'))
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.builder(
                              itemCount: _items.length,
                              itemBuilder: (context, i) {
                                final c = _items[i] as Map<String, dynamic>;
                                return ListTile(
                                  leading: const CircleAvatar(
                                    child: Icon(Icons.person),
                                  ),
                                  title: Text(_name(c).isEmpty ? c['code']?.toString() ?? 'Client' : _name(c)),
                                  subtitle: Text('${c['phone'] ?? '—'} · ${c['code'] ?? ''}'),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
