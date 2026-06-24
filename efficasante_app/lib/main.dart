import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'app.dart';
import 'services/auth_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
  ]);
  await AuthService().loadStored();
  runApp(const EfficasanteApp());
}

class EfficasanteApp extends StatelessWidget {
  const EfficasanteApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthNotifier(AuthService()),
      child: MaterialApp(
        title: 'Efficasante',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFF1976D2),
            brightness: Brightness.light,
            primary: const Color(0xFF1976D2),
            secondary: const Color(0xFF667eea),
          ),
          useMaterial3: true,
          appBarTheme: const AppBarTheme(
            centerTitle: true,
            elevation: 0,
            systemOverlayStyle: SystemUiOverlayStyle(
              statusBarColor: Colors.transparent,
              statusBarIconBrightness: Brightness.dark,
            ),
          ),
          cardTheme: CardTheme(
            elevation: 2,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            clipBehavior: Clip.antiAlias,
          ),
          inputDecorationTheme: InputDecorationTheme(
            filled: true,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          ),
        ),
        darkTheme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFF1976D2),
            brightness: Brightness.dark,
          ),
          useMaterial3: true,
        ),
        home: const AppRouter(),
      ),
    );
  }
}

class AuthNotifier extends ChangeNotifier {
  final AuthService _auth;
  AuthNotifier(this._auth);

  bool get isLoggedIn => _auth.isLoggedIn;
  String get userDisplayName => _auth.userDisplayName;
  String get userRole => _auth.userRole;

  Future<bool> login(String email, String password) async {
    final ok = await _auth.login(email, password);
    if (ok) notifyListeners();
    return ok;
  }

  Future<void> logout() async {
    await _auth.logout();
    notifyListeners();
  }
}
