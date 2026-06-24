import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_service.dart';

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

  AuthService._();

  String? _token;
  Map<String, dynamic>? _user;

  String? get token => _token;
  Map<String, dynamic>? get user => _user;
  bool get isLoggedIn => _token != null && _token!.isNotEmpty;

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
    return true;
  }

  Future<void> logout() async {
    _token = null;
    _user = null;
    _api.setToken(null);
    await _storage.delete(key: _keyToken);
    await _storage.delete(key: _keyUser);
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
