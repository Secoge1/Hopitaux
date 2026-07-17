import 'package:flutter/foundation.dart';

/// Configuration de l'API SeSanté / Hopitaux
class ApiConfig {
  /// Production Se.Santé (HIS clinique)
  static const String productionUrl = 'https://sesante.secogesarl.com';

  /// Production PharmaPro ERP (officine autonome)
  static const String pharmaProductionUrl = 'https://pharmasmart.secogesarl.com';

  /// Développement — émulateur Android → localhost du PC (WAMP)
  static const String devAndroidEmulatorUrl = 'http://10.0.2.2/Hopitaux';

  /// Développement — simulateur iOS ou Flutter web sur la même machine
  static const String devLocalUrl = 'http://localhost/Hopitaux';

  /// Développement — téléphone réel sur le même Wi‑Fi (remplacez par l'IP du PC)
  // static const String devLanUrl = 'http://192.168.1.10/Hopitaux';

  /// URL active : release → production ; debug → émulateur Android par défaut.
  /// Pour iOS / appareil réel en debug, remplacez temporairement par [devLocalUrl] ou [devLanUrl].
  static String get baseUrl => kReleaseMode ? productionUrl : devAndroidEmulatorUrl;

  static String get restUrl => '$baseUrl/api/rest/index.php';

  /// API REST PharmaPro ERP (caisse mobile, stock, dashboard officine)
  static String get pharmaRestUrl => '$baseUrl/api/rest/pharma/index.php';
}
