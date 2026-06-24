import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class ApiService {
  static final ApiService _instance = ApiService._();
  factory ApiService() => _instance;

  String? _token;

  ApiService._();

  void setToken(String? token) {
    _token = token;
  }

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_token != null && _token!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? queryParams,
  }) async {
    var uri = Uri.parse(ApiConfig.restUrl);
    final params = Map<String, String>.from(queryParams ?? {});
    params['path'] = path;
    uri = uri.replace(queryParameters: params);

    http.Response response;
    switch (method) {
      case 'GET':
        response = await http.get(uri, headers: _headers);
        break;
      case 'POST':
        response = await http.post(
          uri,
          headers: _headers,
          body: body != null ? jsonEncode(body) : null,
        );
        break;
      case 'PUT':
        response = await http.put(
          uri,
          headers: _headers,
          body: body != null ? jsonEncode(body) : null,
        );
        break;
      case 'DELETE':
        response = await http.delete(uri, headers: _headers);
        break;
      default:
        throw Exception('Méthode non supportée: $method');
    }

    final decoded = jsonDecode(utf8.decode(response.bodyBytes));
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded as Map<String, dynamic>;
    }
    throw ApiException(
      decoded['error'] as String? ?? 'Erreur serveur',
      response.statusCode,
    );
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    final res = await _request('POST', 'login', body: {
      'email': email,
      'password': password,
    });
    return res;
  }

  Future<Map<String, dynamic>> getDashboardStats() async {
    return _request('GET', 'dashboard/stats');
  }

  Future<Map<String, dynamic>> getPatients({
    int page = 1,
    int limit = 20,
    String search = '',
    String statut = '',
  }) async {
    final params = <String, String>{
      'page': page.toString(),
      'limit': limit.toString(),
    };
    if (search.isNotEmpty) params['search'] = search;
    if (statut.isNotEmpty) params['statut'] = statut;
    return _request('GET', 'patients', queryParams: params);
  }

  Future<Map<String, dynamic>> getPatient(int id) async {
    return _request('GET', 'patients', queryParams: {'id': id.toString()});
  }

  Future<Map<String, dynamic>> getRendezVous({
    int page = 1,
    int limit = 20,
    String search = '',
    String statut = '',
    String date = '',
  }) async {
    final params = <String, String>{
      'page': page.toString(),
      'limit': limit.toString(),
    };
    if (search.isNotEmpty) params['search'] = search;
    if (statut.isNotEmpty) params['statut'] = statut;
    if (date.isNotEmpty) params['date'] = date;
    return _request('GET', 'rendez-vous', queryParams: params);
  }

  Future<Map<String, dynamic>> getConsultations({
    int page = 1,
    int limit = 20,
    String search = '',
    String statut = '',
    String date = '',
  }) async {
    final params = <String, String>{
      'page': page.toString(),
      'limit': limit.toString(),
    };
    if (search.isNotEmpty) params['search'] = search;
    if (statut.isNotEmpty) params['statut'] = statut;
    if (date.isNotEmpty) params['date'] = date;
    return _request('GET', 'consultations', queryParams: params);
  }

  Future<Map<String, dynamic>> getTenantNotices() async {
    return _request('GET', 'tenant/notices');
  }
}

class ApiException implements Exception {
  final String message;
  final int statusCode;
  ApiException(this.message, this.statusCode);
  @override
  String toString() => message;
}
