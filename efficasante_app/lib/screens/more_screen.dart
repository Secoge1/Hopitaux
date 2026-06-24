import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../main.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

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
                  subtitle: Text('${auth.userRole}'),
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
            label: 'Pharmacie',
            onTap: () => _showComingSoon(context, 'Pharmacie'),
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

  void _showComingSoon(BuildContext context, String module) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(module),
        content: Text(
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
  final VoidCallback onTap;
  final Color? textColor;

  const _MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: textColor ?? Theme.of(context).colorScheme.primary),
      title: Text(label, style: TextStyle(color: textColor)),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
