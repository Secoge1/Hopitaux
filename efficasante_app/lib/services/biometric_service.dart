import 'package:local_auth/local_auth.dart';

/// Déverrouillage biométrique (empreinte / Face ID).
class BiometricService {
  static final BiometricService _instance = BiometricService._();
  factory BiometricService() => _instance;

  BiometricService._();

  final _auth = LocalAuthentication();

  Future<bool> isDeviceSupported() async {
    try {
      return await _auth.isDeviceSupported();
    } catch (_) {
      return false;
    }
  }

  Future<bool> canCheckBiometrics() async {
    try {
      if (!await isDeviceSupported()) return false;
      final types = await _auth.getAvailableBiometrics();
      return types.isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  Future<bool> authenticate({String reason = 'Déverrouiller Efficasante'}) async {
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          stickyAuth: true,
          biometricOnly: false,
        ),
      );
    } catch (_) {
      return false;
    }
  }

  Future<String> biometricLabel() async {
    final types = await _auth.getAvailableBiometrics();
    if (types.contains(BiometricType.face)) return 'Face ID';
    if (types.contains(BiometricType.fingerprint)) return 'Empreinte digitale';
    if (types.contains(BiometricType.strong) || types.contains(BiometricType.weak)) {
      return 'Biométrie';
    }
    return 'Code appareil';
  }
}
