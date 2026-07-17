import 'package:barcode_widget/barcode_widget.dart';
import 'package:flutter/material.dart';

/// Affiche un code-barres produit (EAN-13/8 ou Code128).
class ProductBarcodeDisplay extends StatelessWidget {
  final String code;
  final double height;
  final bool showLabel;

  const ProductBarcodeDisplay({
    super.key,
    required this.code,
    this.height = 72,
    this.showLabel = true,
  });

  static Barcode barcodeFor(String raw) {
    final code = raw.trim();
    if (RegExp(r'^\d{13}$').hasMatch(code)) return Barcode.ean13();
    if (RegExp(r'^\d{8}$').hasMatch(code)) return Barcode.ean8();
    if (RegExp(r'^\d{12}$').hasMatch(code)) return Barcode.upcA();
    return Barcode.code128();
  }

  @override
  Widget build(BuildContext context) {
    if (code.trim().isEmpty) {
      return Text(
        'Aucun code-barres enregistré',
        style: TextStyle(color: Colors.grey.shade600, fontStyle: FontStyle.italic),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: Colors.grey.shade300),
          ),
          child: BarcodeWidget(
            barcode: barcodeFor(code),
            data: code.trim(),
            height: height,
            drawText: showLabel,
            color: Colors.black,
            backgroundColor: Colors.white,
          ),
        ),
        if (showLabel) ...[
          const SizedBox(height: 6),
          SelectableText(
            code.trim(),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontFamily: 'monospace',
                  letterSpacing: 1.2,
                ),
          ),
        ],
      ],
    );
  }
}

/// Liste tous les codes-barres d'un produit (principal + secondaires).
class ProductBarcodesList extends StatelessWidget {
  final Map<String, dynamic> product;

  const ProductBarcodesList({super.key, required this.product});

  List<String> _codes() {
    final seen = <String>{};
    final codes = <String>[];

    void add(String? value) {
      final v = value?.trim() ?? '';
      if (v.isNotEmpty && seen.add(v)) codes.add(v);
    }

    add(product['barcode_primary']?.toString());
    final extra = product['barcodes'];
    if (extra is List) {
      for (final item in extra) {
        if (item is Map) add(item['barcode']?.toString());
      }
    }
    return codes;
  }

  @override
  Widget build(BuildContext context) {
    final codes = _codes();
    if (codes.isEmpty) {
      return const ProductBarcodeDisplay(code: '');
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < codes.length; i++) ...[
          if (i > 0) const Divider(height: 24),
          if (codes.length > 1)
            Text(
              i == 0 ? 'Code principal' : 'Code alternatif ${i}',
              style: Theme.of(context).textTheme.labelLarge,
            ),
          if (codes.length > 1) const SizedBox(height: 8),
          ProductBarcodeDisplay(code: codes[i]),
        ],
      ],
    );
  }
}
