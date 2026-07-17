import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import 'api_service.dart';
import 'auth_service.dart';

/// Client HTTP pour l'API REST PharmaPro ERP.
class PharmaApiService {
  static final PharmaApiService _instance = PharmaApiService._();
  factory PharmaApiService() => _instance;

  PharmaApiService._();

  static const _allowedRoles = {'admin', 'pharmacien', 'comptable', 'pharma_manager', 'pharma_cashier'};

  bool get roleMayAccess => _allowedRoles.contains(AuthService().userRole);

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    final token = AuthService().token;
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? queryParams,
  }) async {
    var uri = Uri.parse(ApiConfig.pharmaRestUrl);
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
      default:
        throw Exception('Méthode non supportée: $method');
    }

    Map<String, dynamic> decoded;
    try {
      decoded = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException('Réponse serveur invalide', response.statusCode);
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded;
    }
    throw ApiException(
      decoded['error'] as String? ?? 'Erreur PharmaPro',
      response.statusCode,
    );
  }

  /// Vérifie que le tenant a PharmaPro activé et que le rôle est autorisé.
  Future<bool> hasAccess() async {
    if (!AuthService().isLoggedIn || !roleMayAccess) return false;
    try {
      await getFeatures();
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 403 || e.statusCode == 401) return false;
      rethrow;
    }
  }

  Future<Map<String, dynamic>> getFeatures() async {
    return _request('GET', 'features');
  }

  Future<Map<String, dynamic>> getDashboard() async {
    return _request('GET', 'dashboard');
  }

  Future<Map<String, dynamic>> getProducts({
    int page = 1,
    int limit = 20,
    String search = '',
  }) async {
    final params = <String, String>{
      'page': page.toString(),
      'limit': limit.toString(),
    };
    if (search.isNotEmpty) params['q'] = search;
    return _request('GET', 'products', queryParams: params);
  }

  Future<Map<String, dynamic>> getProductByBarcode(String code) async {
    return _request('GET', 'products/barcode', queryParams: {'code': code});
  }

  Future<Map<String, dynamic>> getProductDetail(int id) async {
    return _request('GET', 'products/detail', queryParams: {'id': id.toString()});
  }

  Future<Map<String, dynamic>> getBilanReport({
    String? dateFrom,
    String? dateTo,
  }) async {
    final params = <String, String>{};
    if (dateFrom != null) params['date_from'] = dateFrom;
    if (dateTo != null) params['date_to'] = dateTo;
    return _request('GET', 'reports/bilan', queryParams: params);
  }

  Future<Map<String, dynamic>> getGrandLivreReport({
    String? dateFrom,
    String? dateTo,
  }) async {
    final params = <String, String>{};
    if (dateFrom != null) params['date_from'] = dateFrom;
    if (dateTo != null) params['date_to'] = dateTo;
    return _request('GET', 'reports/grand_livre', queryParams: params);
  }

  /// Télécharge un PDF comptable (Bilan ou Grand Livre).
  Future<Uint8List> downloadReportPdf(
    String path, {
    String? dateFrom,
    String? dateTo,
  }) async {
    var uri = Uri.parse(ApiConfig.pharmaRestUrl);
    final params = <String, String>{'path': path};
    if (dateFrom != null) params['date_from'] = dateFrom;
    if (dateTo != null) params['date_to'] = dateTo;
    uri = uri.replace(queryParameters: params);

    final token = AuthService().token;
    final headers = <String, String>{
      'Accept': 'application/pdf',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };

    final response = await http.get(uri, headers: headers);
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return response.bodyBytes;
    }

    try {
      final decoded = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
      throw ApiException(decoded['error'] as String? ?? 'Erreur PDF', response.statusCode);
    } catch (_) {
      throw ApiException('Erreur PDF (${response.statusCode})', response.statusCode);
    }
  }

  Future<Map<String, dynamic>> getRecentSales() async {
    return _request('GET', 'sales/recent');
  }

  Future<Map<String, dynamic>> getStockAlerts() async {
    return _request('GET', 'stock/alerts');
  }

  Future<Map<String, dynamic>> createSale({
    required List<Map<String, dynamic>> lines,
    required double amountPaid,
    String paymentMethod = 'cash',
    String? customerName,
    String? promoCode,
    String? loyaltyPhone,
  }) async {
    return _request('POST', 'sales', body: {
      'lines': lines,
      'payment': {
        'method': paymentMethod,
        'amount': amountPaid,
        if (promoCode != null && promoCode.isNotEmpty) 'promo_code': promoCode,
        if (loyaltyPhone != null && loyaltyPhone.isNotEmpty) 'loyalty_phone': loyaltyPhone,
      },
      if (customerName != null && customerName.isNotEmpty) 'customer_name': customerName,
    });
  }

  Future<Map<String, dynamic>> getPromotions() async {
    return _request('GET', 'promotions');
  }

  Future<Map<String, dynamic>> validatePromoCode(String code, {double subtotal = 0}) async {
    return _request('GET', 'promotions/validate', queryParams: {
      'code': code,
      'subtotal': subtotal.toStringAsFixed(2),
    });
  }

  Future<Map<String, dynamic>> getPrescriptions({String status = ''}) async {
    final params = <String, String>{};
    if (status.isNotEmpty) params['status'] = status;
    return _request('GET', 'prescriptions', queryParams: params);
  }

  Future<Map<String, dynamic>> getInventories() async {
    return _request('GET', 'inventory');
  }

  Future<Map<String, dynamic>> getCustomers({
    int page = 1,
    String search = '',
  }) async {
    final params = <String, String>{'page': page.toString()};
    if (search.isNotEmpty) params['q'] = search;
    return _request('GET', 'customers', queryParams: params);
  }

  Future<Map<String, dynamic>> getReturns({
    int page = 1,
    String search = '',
  }) async {
    final params = <String, String>{'page': page.toString()};
    if (search.isNotEmpty) params['q'] = search;
    return _request('GET', 'returns', queryParams: params);
  }

  Future<Map<String, dynamic>> getSupplierInvoices({
    int page = 1,
    String status = '',
    String search = '',
  }) async {
    final params = <String, String>{'page': page.toString()};
    if (status.isNotEmpty) params['status'] = status;
    if (search.isNotEmpty) params['q'] = search;
    return _request('GET', 'supplier-invoices', queryParams: params);
  }

  Future<Map<String, dynamic>> setPrimaryBarcode(int productId, String barcode) async {
    return _request('POST', 'products/barcode/primary', body: {
      'product_id': productId,
      'barcode': barcode,
    });
  }

  Future<Map<String, dynamic>> addSecondaryBarcode(int productId, String barcode) async {
    return _request('POST', 'products/barcode/add', body: {
      'product_id': productId,
      'barcode': barcode,
    });
  }

  Future<Map<String, dynamic>> removeBarcode(int productId, String barcode) async {
    return _request('POST', 'products/barcode/remove', body: {
      'product_id': productId,
      'barcode': barcode,
    });
  }
}
