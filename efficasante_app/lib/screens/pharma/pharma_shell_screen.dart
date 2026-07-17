import 'package:flutter/material.dart';
import 'pharma_home_screen.dart';
import 'pharma_pos_screen.dart';
import 'pharma_products_screen.dart';

/// Conteneur principal PharmaPro ERP (navigation mobile caisse / stock).
class PharmaShellScreen extends StatefulWidget {
  const PharmaShellScreen({super.key});

  @override
  State<PharmaShellScreen> createState() => _PharmaShellScreenState();
}

class _PharmaShellScreenState extends State<PharmaShellScreen> {
  int _index = 0;

  static const _accent = Color(0xFF0D9488);

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: Theme.of(context).copyWith(
        colorScheme: Theme.of(context).colorScheme.copyWith(
              primary: _accent,
              secondary: const Color(0xFF14B8A6),
            ),
      ),
      child: Scaffold(
        body: IndexedStack(
          index: _index,
          children: const [
            PharmaHomeScreen(),
            PharmaPosScreen(),
            PharmaProductsScreen(),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (i) => setState(() => _index = i),
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.storefront_outlined),
              selectedIcon: Icon(Icons.storefront),
              label: 'Accueil',
            ),
            NavigationDestination(
              icon: Icon(Icons.point_of_sale_outlined),
              selectedIcon: Icon(Icons.point_of_sale),
              label: 'Caisse',
            ),
            NavigationDestination(
              icon: Icon(Icons.inventory_2_outlined),
              selectedIcon: Icon(Icons.inventory_2),
              label: 'Produits',
            ),
          ],
        ),
      ),
    );
  }
}
