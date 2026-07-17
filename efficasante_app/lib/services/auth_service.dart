import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'biometric_service.dart';

class AuthService {
  static final AuthService _instance = AuthService._();
  factory AuthService() => _instance;

  final ApiService _api = ApiService();
  final _storage = const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  static const _keyToken = 'efficasante_token';
  static const _keyUser = 'efficasante_user';
  static const _keyBiometric = 'efficasante_biometric_enabled';

  AuthService._();

  String? _token;
  Map<String, dynamic>? _user;
  bool _biometricEnabled = false;
  bool _sessionUnlocked = false;

  String? get token => _token;
  Map<String, dynamic>? get user => _user;
  bool get isLoggedIn => _token != null && _token!.isNotEmpty;
  bool get biometricEnabled => _biometricEnabled;
  bool get sessionUnlocked => _sessionUnlocked;
  bool get needsBiometricUnlock => isLoggedIn && _biometricEnabled && !_sessionUnlocked;

  Future<void> loadStored() async {
    _token = await _storage.read(key: _keyToken);
    final userJson = await _storage.read(key: _keyUser);
    if (_token != null) {
      _api.setToken(_token);
    }
    if (userJson != null) {
      try {
        _user = Map<String, dynamic>.from(jsonDecode(userJson) as Map);
      } catch (_) {
        _user = null;
      }
    }
    final prefs = await SharedPreferences.getInstance();
    _biometricEnabled = prefs.getBool(_keyBiometric) ?? false;
    _sessionUnlocked = !_biometricEnabled || !isLoggedIn;
  }

  Future<bool> login(String email, String password) async {
    final res = await _api.login(email, password);
    if (res['success'] != true) return false;
    _token = res['token'] as String?;
    _user = res['user'] as Map<String, dynamic>?;
    if (_token != null) {
      _api.setToken(_token);
      await _storage.write(key: _keyToken, value: _token);
      if (_user != null) {
        await _storage.write(key: _keyUser, value: jsonEncode(_user));
      }
    }
    _sessionUnlocked = true;
    return true;
  }

  Future<void> logout() async {
    _token = null;
    _user = null;
    _sessionUnlocked = false;
    _api.setToken(null);
    await _storage.delete(key: _keyToken);
    await _storage.delete(key: _keyUser);
  }

  Future<bool> setBiometricEnabled(bool enabled) async {
    if (enabled) {
      final can = await BiometricService().canCheckBiometrics();
      if (!can) return false;
      final ok = await BiometricService().authenticate(
        reason: 'Activer la connexion biométrique',
      );
      if (!ok) return false;
    }
    _biometricEnabled = enabled;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_keyBiometric, enabled);
    if (!enabled) _sessionUnlocked = true;
    return true;
  }

  Future<bool> unlockWithBiometric() async {
    if (!_biometricEnabled) {
      _sessionUnlocked = true;
      return true;
    }
    final ok = await BiometricService().authenticate();
    if (ok) _sessionUnlocked = true;
    return ok;
  }

  String get userDisplayName => _user?['nom_utilisateur'] as String? ?? '';
  String get userRole => _user?['role'] as String? ?? '';
  int? get userId {
    final id = _user?['id'];
    if (id is int) return id;
    if (id is num) return id.toInt();
    return null;
  }
}
