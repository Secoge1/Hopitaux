# Installation sur Android et iOS

## En bref

1. **Installer Flutter** : [https://flutter.dev/docs/get-started/install](https://flutter.dev/docs/get-started/install)
2. **Dans le dossier `efficasante_app`** :
   - `flutter create .` (génère android/ et ios/ si besoin, répondez **n** pour ne pas écraser vos fichiers)
   - `flutter pub get`
3. **Modifier l’URL du serveur** dans `lib/config/api_config.dart` (voir README.md).
4. **Lancer** : `flutter run` (branchez un téléphone ou lancez un émulateur).

## Build Android (APK installable)

```bash
flutter build apk --release
```

Fichier produit : **`build/app/outputs/flutter-apk/app-release.apk`**

- Copiez l’APK sur votre téléphone Android et installez-le.
- Si le téléphone demande d’autoriser « Sources inconnues » ou « Installer des applications inconnues », acceptez pour ce fichier ou ce navigateur.

## Build iOS (iPhone / iPad)

- Nécessite un **Mac** avec **Xcode**.
- Ouvrez un terminal dans le dossier `efficasante_app` :

```bash
flutter build ios --release
```

Puis ouvrez **`ios/Runner.xcworkspace`** dans Xcode :

- Choisissez votre équipe (Team) dans **Signing & Capabilities**.
- Pour tester sur un appareil : branchez l’iPhone, sélectionnez-le comme cible, puis **Run**.
- Pour l’App Store : **Product → Archive**, puis distribuez l’archive.

## Problèmes courants

- **« Cleartext HTTP not permitted » (Android)**  
  Vous utilisez `http://` sans SSL. Dans **`android/app/src/main/AndroidManifest.xml`**, dans la balise `<application ...>`, ajoutez :  
  `android:usesCleartextTraffic="true"`

- **Connexion refusée / timeout**  
  Sur **appareil réel**, utilisez l’**IP** de votre PC (ex. `http://192.168.1.10/efficasante`) dans `api_config.dart`, et vérifiez que le téléphone et le PC sont sur le **même réseau**.

- **iOS : erreur de sécurité avec HTTP**  
  En développement avec `http://`, ajoutez dans **`ios/Runner/Info.plist`** une exception `NSAppTransportSecurity` pour autoriser votre domaine ou localhost (voir la doc Apple ou le README).
