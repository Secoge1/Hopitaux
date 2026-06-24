import 'package:flutter/material.dart';
import '../services/api_service.dart';

class PatientDetailScreen extends StatefulWidget {
  final int patientId;

  const PatientDetailScreen({super.key, required this.patientId});

  @override
  State<PatientDetailScreen> createState() => _PatientDetailScreenState();
}

class _PatientDetailScreenState extends State<PatientDetailScreen> {
  Map<String, dynamic>? _patient;
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
      final res = await ApiService().getPatient(widget.patientId);
      if (mounted) {
        setState(() {
          _patient = res['data'] as Map<String, dynamic>?;
          _loading = false;
        });
      }
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
        title: const Text('Dossier patient'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: _load,
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                )
              : _patient == null
                  ? const Center(child: Text('Patient non trouvé'))
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Row(
                                children: [
                                  CircleAvatar(
                                    radius: 36,
                                    backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                                    child: Text(
                                      (_patient!['prenom'] ?? _patient!['nom'] ?? '?')
                                          .toString()
                                          .isNotEmpty
                                          ? (_patient!['prenom'] ?? _patient!['nom'])
                                              .toString()[0]
                                              .toUpperCase()
                                          : '?',
                                      style: TextStyle(
                                        fontSize: 28,
                                        color: Theme.of(context).colorScheme.onPrimaryContainer,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 16),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          '${_patient!['prenom'] ?? ''} ${_patient!['nom'] ?? ''}'.trim(),
                                          style: Theme.of(context).textTheme.titleLarge,
                                        ),
                                        if (_patient!['numero_dossier'] != null)
                                          Text(
                                            'Dossier: ${_patient!['numero_dossier']}',
                                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                                  color: Colors.grey.shade600,
                                                ),
                                          ),
                                        if (_patient!['age'] != null)
                                          Text('${_patient!['age']} ans'),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                          _InfoSection(
                            title: 'Informations',
                            items: {
                              'Date de naissance': _patient!['date_naissance'],
                              'Sexe': _patient!['sexe'],
                              'Groupe sanguin': _patient!['groupe_sanguin'],
                              'Téléphone': _patient!['telephone'],
                              'Email': _patient!['email'],
                              'Adresse': _patient!['adresse'],
                              'Ville': _patient!['ville'],
                              'Profession': _patient!['profession'],
                            },
                          ),
                          if (_patient!['antecedents_medicaux'] != null &&
                              _patient!['antecedents_medicaux'].toString().isNotEmpty)
                            _InfoSection(
                              title: 'Antécédents médicaux',
                              items: {'': _patient!['antecedents_medicaux']},
                            ),
                          if (_patient!['allergies'] != null &&
                              _patient!['allergies'].toString().isNotEmpty)
                            _InfoSection(
                              title: 'Allergies',
                              items: {'': _patient!['allergies']},
                            ),
                          if (_patient!['notes'] != null &&
                              _patient!['notes'].toString().isNotEmpty)
                            _InfoSection(
                              title: 'Notes',
                              items: {'': _patient!['notes']},
                            ),
                        ],
                      ),
                    ),
    );
  }
}

class _InfoSection extends StatelessWidget {
  final String title;
  final Map<String, dynamic?> items;

  const _InfoSection({required this.title, required this.items});

  @override
  Widget build(BuildContext context) {
    final entries = items.entries
        .where((e) => e.value != null && e.value.toString().trim().isNotEmpty)
        .toList();
    if (entries.isEmpty) return const SizedBox.shrink();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: 12),
            ...entries.map((e) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: e.key.isEmpty
                    ? Text(e.value.toString())
                    : Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SizedBox(
                            width: 120,
                            child: Text(
                              e.key,
                              style: TextStyle(
                                color: Colors.grey.shade600,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          Expanded(child: Text(e.value.toString())),
                        ],
                      ),
              );
            }),
          ],
        ),
      ),
    );
  }
}
