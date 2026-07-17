import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../main.dart';

/// Écran de verrouillage biométrique au démarrage.
class BiometricLockScreen extends StatefulWidget {
  const BiometricLockScreen({super.key});

  @override
  State<BiometricLockScreen> createState() => _BiometricLockScreenState();
}

class _BiometricLockScreenState extends State<BiometricLockScreen> {
  bool _checking = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _unlock());
  }

  Future<void> _unlock() async {
    if (_checking) return;
    setState(() {
      _checking = true;
      _error = null;
    });
    final auth = context.read<AuthNotifier>();
    final ok = await auth.unlockWithBiometric();
    if (!mounted) return;
    if (!ok) {
      setState(() {
        _checking = false;
        _error = 'Authentification échouée';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        width: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF667eea), Color(0xFF764ba2)],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.fingerprint, size: 88, color: Colors.white),
                const SizedBox(height: 24),
                Consumer<AuthNotifier>(
                  builder: (_, auth, __) => Text(
                    'Bonjour, ${auth.userDisplayName}',
                    style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w600),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  _checking ? 'Vérification…' : 'Déverrouillez pour continuer',
                  style: TextStyle(color: Colors.white.withValues(alpha: 0.9)),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 16),
                  Text(_error!, style: const TextStyle(color: Colors.white70)),
                ],
                const SizedBox(height: 32),
                FilledButton.icon(
                  onPressed: _checking ? null : _unlock,
                  icon: const Icon(Icons.lock_open),
                  label: const Text('Déverrouiller'),
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: const Color(0xFF667eea),
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                  ),
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed: () async {
                    await context.read<AuthNotifier>().logout();
                  },
                  child: Text('Utiliser un autre compte', style: TextStyle(color: Colors.white.withValues(alpha: 0.85))),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
