import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

class PaymentSyncNoticeBanner extends StatefulWidget {
  const PaymentSyncNoticeBanner({super.key});

  @override
  State<PaymentSyncNoticeBanner> createState() => _PaymentSyncNoticeBannerState();
}

class _PaymentSyncNoticeBannerState extends State<PaymentSyncNoticeBanner> {
  Map<String, dynamic>? _notice;
  bool _visible = false;
  Timer? _hideTimer;

  static String _storageKey(int userId) => 'hopitaux_payment_sync_notice_$userId';

  @override
  void initState() {
    super.initState();
    _loadNotice();
  }

  @override
  void dispose() {
    _hideTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadNotice() async {
    try {
      final res = await ApiService().getTenantNotices();
      if (!mounted || res['success'] != true) return;

      final data = res['data'] as Map<String, dynamic>?;
      final notices = data?['notices'] as List<dynamic>?;
      if (notices == null || notices.isEmpty) return;

      final auth = AuthService();
      final userId = auth.userId ?? (data?['user_id'] as num?)?.toInt();
      if (userId == null) return;

      Map<String, dynamic>? item;
      for (final raw in notices) {
        final n = raw as Map<String, dynamic>;
        if (n['key'] == 'payment_finance_sync' && n['enabled'] == true && n['stamp'] != null) {
          item = n;
          break;
        }
      }
      if (item == null) return;

      final stamp = item['stamp'] as String;
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getString(_storageKey(userId)) == stamp) return;

      if (!mounted) return;
      setState(() {
        _notice = item;
        _visible = true;
      });

      final durationMs = (item['duration_ms'] as num?)?.toInt() ?? 10000;
      _hideTimer = Timer(Duration(milliseconds: durationMs), () => _dismiss(userId, stamp));
    } catch (_) {
      /* API indisponible ou feature off */
    }
  }

  Future<void> _dismiss(int userId, String stamp) async {
    _hideTimer?.cancel();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_storageKey(userId), stamp);
    if (!mounted) return;
    setState(() => _visible = false);
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (mounted) setState(() => _notice = null);
  }

  @override
  Widget build(BuildContext context) {
    if (_notice == null) return const SizedBox.shrink();

    final auth = AuthService();
    final userId = auth.userId;
    final stamp = _notice!['stamp'] as String?;
    if (userId == null || stamp == null) return const SizedBox.shrink();

    final title = _notice!['title'] as String? ?? 'Nouveau — synchronisation Paiements & Comptabilité';
    final message = _notice!['message'] as String? ?? '';

    return AnimatedOpacity(
      opacity: _visible ? 1 : 0,
      duration: const Duration(milliseconds: 600),
      child: AnimatedSlide(
        offset: _visible ? Offset.zero : const Offset(0, -0.05),
        duration: const Duration(milliseconds: 600),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: Material(
            elevation: 0,
            color: Colors.transparent,
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0x59198754)),
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    Color(0x1F198754),
                    Color(0x1420C997),
                  ],
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 32,
                    height: 32,
                    alignment: Alignment.center,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(
                        colors: [Color(0xFF198754), Color(0xFF20C997)],
                      ),
                    ),
                    child: const Text('★', style: TextStyle(color: Colors.white, fontSize: 14)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF146C43),
                            fontSize: 14,
                          ),
                        ),
                        if (message.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(
                            message,
                            style: const TextStyle(
                              color: Color(0xFF1F5132),
                              fontSize: 13,
                              height: 1.35,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  IconButton(
                    visualDensity: VisualDensity.compact,
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                    onPressed: () => _dismiss(userId, stamp),
                    icon: const Icon(Icons.close, color: Color(0xFF146C43), size: 20),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
