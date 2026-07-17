import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../main.dart';
import '../services/pharma_api_service.dart';
import '../services/biometric_service.dart';
import 'pharma/pharma_shell_screen.dart';

class MoreScreen extends StatefulWidget {
  const MoreScreen({super.key});

  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  bool? _pharmaAccess;
  bool _checkingPharma = false;
  bool _biometricAvailable = false;
  bool _biometricBusy = false;

  @override
  void initState() {
    super.initState();
    _checkPharmaAccess();
    _checkBiometric();
  }

  Future<void> _checkBiometric() async {
    final ok = await BiometricService().canCheckBiometrics();
    if (mounted) setState(() => _biometricAvailable = ok);
  }

  Future<void> _checkPharmaAccess() async {
    if (!PharmaApiService().roleMayAccess) {
      setState(() => _pharmaAccess = false);
      return;
    }
    setState(() => _checkingPharma = true);
    try {
      final ok = await PharmaApiService().hasAccess();
      if (mounted) setState(() => _pharmaAccess = ok);
    } catch (_) {
      if (mounted) setState(() => _pharmaAccess = false);
    } finally {
      if (mounted) setState(() => _checkingPharma = false);
    }
  }

  Future<void> _openPharma(BuildContext context) async {
    if (_pharmaAccess != true) {
      _showPharmaUnavailable(context);
      return;
    }
    if (!context.mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const PharmaShellScreen()),
    );
  }

  void _showPharmaUnavailable(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('PharmaPro ERP'),
        content: const Text(
          'Ce module n\'est pas disponible pour votre compte.\n\n'
          'Vérifiez que PharmaPro ERP est activé par l\'administrateur plateforme '
          'et que votre rôle est admin, pharmacien ou comptable.',
        ),
        actions: [
          FilledButton(onPressed: () => Navigator.pop(ctx), child: const Text('OK')),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Plus'),
      ),
      body: ListView(
        padding: const EdgeInsets.symmetric(vertical: 16),
        children: [
          Consumer<AuthNotifier>(
            builder: (_, auth, __) => Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Card(
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                    child: Text(
                      auth.userDisplayName.isNotEmpty ? auth.userDisplayName[0].toUpperCase() : '?',
                      style: TextStyle(color: Theme.of(context).colorScheme.onPrimaryContainer),
                    ),
                  ),
                  title: Text(auth.userDisplayName),
                  subtitle: Text(auth.userRole),
                ),
              ),
            ),
          ),
          _MenuItem(
            icon: Icons.science,
            label: 'Laboratoire',
            onTap: () => _showComingSoon(context, 'Laboratoire'),
          ),
          _MenuItem(
            icon: Icons.payment,
            label: 'Paiements',
            onTap: () => _showComingSoon(context, 'Paiements'),
          ),
          _MenuItem(
            icon: Icons.message,
            label: 'Communication',
            onTap: () => _showComingSoon(context, 'Communication'),
          ),
          _MenuItem(
            icon: Icons.medication,
            label: 'PharmaPro ERP',
            subtitle: _pharmaSubtitle(),
            trailing: _checkingPharma
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : _pharmaAccess == true
                    ? null
                    : Icon(Icons.lock_outline, color: Colors.grey.shade500, size: 20),
            onTap: () => _openPharma(context),
          ),
          _MenuItem(
            icon: Icons.account_balance_wallet,
            label: 'Finances',
            onTap: () => _showComingSoon(context, 'Finances'),
          ),
          _MenuItem(
            icon: Icons.settings,
            label: 'Paramètres',
            onTap: () => _showComingSoon(context, 'Paramètres'),
          ),
          if (_biometricAvailable)
            Consumer<AuthNotifier>(
              builder: (_, auth, __) => SwitchListTile(
                secondary: const Icon(Icons.fingerprint),
                title: const Text('Déverrouillage biométrique'),
                subtitle: const Text('Empreinte ou Face ID au lancement'),
                value: auth.biometricEnabled,
                onChanged: _biometricBusy
                    ? null
                    : (v) async {
                        setState(() => _biometricBusy = true);
                        await auth.setBiometricEnabled(v);
                        if (mounted) setState(() => _biometricBusy = false);
                      },
              ),
            ),
          const Divider(height: 24),
          _MenuItem(
            icon: Icons.logout,
            label: 'Déconnexion',
            textColor: Colors.red,
            onTap: () async {
              final ok = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Déconnexion'),
                  content: const Text('Voulez-vous vraiment vous déconnecter ?'),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      child: const Text('Annuler'),
                    ),
                    FilledButton(
                      onPressed: () => Navigator.pop(ctx, true),
                      child: const Text('Déconnexion'),
                    ),
                  ],
                ),
              );
              if (ok == true && context.mounted) {
                await context.read<AuthNotifier>().logout();
              }
            },
          ),
        ],
      ),
    );
  }

  String? _pharmaSubtitle() {
    if (_checkingPharma) return 'Vérification…';
    if (_pharmaAccess == true) return 'Caisse, stock, dashboard officine';
    return 'Non activé ou rôle insuffisant';
  }

  void _showComingSoon(BuildContext context, String module) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(module),
        content: const Text(
          'Ce module sera disponible dans une prochaine mise à jour. '
          'En attendant, utilisez la version web sur votre navigateur.',
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final String? subtitle;
  final VoidCallback onTap;
  final Color? textColor;
  final Widget? trailing;

  const _MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.subtitle,
    this.textColor,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: textColor ?? Theme.of(context).colorScheme.primary),
      title: Text(label, style: TextStyle(color: textColor)),
      subtitle: subtitle != null ? Text(subtitle!) : null,
      trailing: trailing ?? const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
