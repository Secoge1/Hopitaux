import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'main.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';

class AppRouter extends StatelessWidget {
  const AppRouter({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthNotifier>(
      builder: (_, auth, __) {
        if (auth.isLoggedIn) {
          return const HomeScreen();
        }
        return const LoginScreen();
      },
    );
  }
}
