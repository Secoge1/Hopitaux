import 'package:flutter/material.dart';
import 'barcode_scanner_screen.dart';
import 'product_barcode_display.dart';

/// Dialogue saisie / scan d'un code-barres produit.
Future<String?> showBarcodeEditDialog(
  BuildContext context, {
  required String title,
  String? initialValue,
  String confirmLabel = 'Enregistrer',
}) async {
  final ctrl = TextEditingController(text: initialValue ?? '');

  final result = await showDialog<String>(
    context: context,
    builder: (ctx) => _BarcodeEditDialogBody(
      title: title,
      controller: ctrl,
      confirmLabel: confirmLabel,
    ),
  );

  ctrl.dispose();
  return result;
}

class _BarcodeEditDialogBody extends StatefulWidget {
  final String title;
  final TextEditingController controller;
  final String confirmLabel;

  const _BarcodeEditDialogBody({
    required this.title,
    required this.controller,
    required this.confirmLabel,
  });

  @override
  State<_BarcodeEditDialogBody> createState() => _BarcodeEditDialogBodyState();
}

class _BarcodeEditDialogBodyState extends State<_BarcodeEditDialogBody> {
  final _formKey = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: widget.controller,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: 'Code-barres',
                prefixIcon: Icon(Icons.qr_code),
              ),
              onChanged: (_) => setState(() {}),
              validator: (v) {
                if (v == null || v.trim().isEmpty) {
                  return 'Saisissez ou scannez un code-barres';
                }
                return null;
              },
            ),
            const SizedBox(height: 8),
            if (widget.controller.text.trim().isNotEmpty)
              ProductBarcodeDisplay(
                code: widget.controller.text.trim(),
                height: 56,
                showLabel: false,
              ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () async {
                final code = await openBarcodeScanner(context);
                if (code != null) {
                  widget.controller.text = code;
                  setState(() {});
                }
              },
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scanner'),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
        FilledButton(
          onPressed: () {
            if (_formKey.currentState?.validate() != true) return;
            Navigator.pop(context, widget.controller.text.trim());
          },
          child: Text(widget.confirmLabel),
        ),
      ],
    );
  }
}
