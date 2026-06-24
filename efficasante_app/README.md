# Efficasante - Application mobile (Flutter)

Application mobile **style Flutter** pour le système Efficasante (gestion clinique et hospitalière), compatible **Android** et **iOS**, sans problème d’installation.

## Prérequis

- **Flutter SDK** 3.0 ou supérieur ([flutter.dev](https://flutter.dev/docs/get-started/install))
- **Serveur Efficasante** déployé et accessible (PHP + MySQL)
- **Android Studio** (pour Android) ou **Xcode** (pour iOS, uniquement sur macOS)

## Installation

### 1. Générer les dossiers Android et iOS (si besoin)

À la racine du projet (`efficasante_app`) :

```bash
flutter create .
```

Répondez **n** si Flutter propose d’écraser des fichiers existants (pour garder `lib/`, `pubspec.yaml`, etc.).

### 2. Récupérer les dépendances

```bash
flutter pub get
```

### 3. Configurer l’URL de l’API

Ouvrez **`lib/config/api_config.dart`** et modifiez `baseUrl` selon votre environnement :

- **Émulateur Android** : `http://10.0.2.2/efficasante` (localhost du PC)
- **Simulateur iOS** : `http://localhost/efficasante` ou `http://127.0.0.1/efficasante`
- **Appareil réel** : `http://VOTRE_IP/efficasante` (ex. `http://192.168.1.10/efficasante`)
- **Production** : `https://votredomaine.com/efficasante`

### 4. Activer l’API REST sur le serveur

L’API REST est dans **`api/rest/index.php`** (à la racine du projet Efficasante, pas dans l’app Flutter).

- Vérifiez que le serveur PHP sert bien ce fichier (ex. `https://votredomaine.com/efficasante/api/rest/index.php?path=login`).
- La table **`api_tokens`** est créée automatiquement au premier appel.

## Lancer l’application

- **Android (émulateur ou appareil)** :
  ```bash
  flutter run
  ```
- **iOS (simulateur, uniquement sur macOS)** :
  ```bash
  flutter run
  ```

## Build pour installation (APK / IPA)

### Android (APK)

```bash
flutter build apk --release
```

L’APK se trouve dans :  
`build/app/outputs/flutter-apk/app-release.apk`

Vous pouvez le copier sur un téléphone Android et l’installer (autoriser « Sources inconnues » si demandé).

### Android (App Bundle pour Play Store)

```bash
flutter build appbundle --release
```

Fichier généré : `build/app/outputs/bundle/release/app-release.aab`

### iOS (iPhone / iPad)

Sur **macOS**, avec Xcode installé :

```bash
flutter build ios --release
```

Puis ouvrir `ios/Runner.xcworkspace` dans Xcode, configurer la signature et lancer l’archive pour envoyer sur l’App Store ou installer sur un appareil de test.

## Modules disponibles dans l’app

- **Connexion** (email / mot de passe)
- **Tableau de bord** (statistiques)
- **Patients** (liste, détail, recherche)
- **Rendez-vous** (liste, filtre par date)
- **Consultations** (liste)
- **Plus** : Laboratoire, Paiements, Communication, Pharmacie, Finances, Paramètres (écrans « à venir »), Déconnexion

Les modules Laboratoire, Paiements, etc. peuvent être complétés plus tard en ajoutant les routes correspondantes dans l’API PHP et les écrans dans Flutter.

## Dépannage

- **« Erreur de connexion » / timeouts** : vérifiez que le téléphone et le PC (ou le serveur) sont sur le même réseau et que `baseUrl` pointe vers la bonne adresse (IP pour appareil réel).
- **Android : « Cleartext HTTP not permitted »** : pour du HTTP (sans HTTPS), ajoutez dans `android/app/src/main/AndroidManifest.xml` (dans `<application>`) :
  ```xml
  android:usesCleartextTraffic="true"
  ```
- **iOS : HTTP bloqué** : dans `ios/Runner/Info.plist`, ajoutez une exception pour votre domaine (ou pour localhost en dev) sous `NSAppTransportSecurity`.

## Licence

Utilisation dans le cadre du projet Efficasante.
