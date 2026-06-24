/// Configuration de l'API - À adapter selon votre serveur
class ApiConfig {
  /// URL de base du serveur Efficasante (sans slash final)
  /// - Émulateur Android: http://10.0.2.2/efficasante
  /// - Simulateur iOS: http://localhost/efficasante
  /// - Appareil réel: http://VOTRE_IP/efficasante (ex: 192.168.1.10)
  /// - Production: https://votredomaine.com/efficasante
  static const String baseUrl = 'http://10.0.2.2/efficasante';

  static String get restUrl => '$baseUrl/api/rest/index.php';
}
