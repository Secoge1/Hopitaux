import 'package:intl/intl.dart';

/// Utilitaires d'affichage PharmaPro (montants FCFA).
class PharmaFormat {
  static final _currency = NumberFormat('#,##0', 'fr_FR');

  static String money(num value) => '${_currency.format(value)} FCFA';
}
