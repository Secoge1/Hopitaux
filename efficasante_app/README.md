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

Ouvrez **`lib/config/api_config.dart`**. En **release** (`flutter build apk`), l’URL production est utilisée automatiquement :

- **Production** : `https://sesante.secogesarl.com`

En **debug** (`flutter run`), par défaut :

- **Émulateur Android** : `http://10.0.2.2/Hopitaux`
- **Simulateur iOS** : remplacez temporairement par `http://localhost/Hopitaux` dans `baseUrl`
- **Appareil réel** : IP du PC, ex. `http://192.168.1.10/Hopitaux`

Vérifiez l’API : `https://sesante.secogesarl.com/api/rest/index.php?path=login` (POST JSON).
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
- **Plus** : Laboratoire, Paiements, Communication, **PharmaPro ERP** (caisse POS, produits, alertes stock), Finances, Paramètres (écrans « à venir »), Déconnexion

Les modules Laboratoire, Paiements, etc. peuvent être complétés plus tard en ajoutant les routes correspondantes dans l’API PHP et les écrans dans Flutter.

**PharmaPro ERP** : menu Plus → PharmaPro ERP (rôles admin/pharmacien/comptable, feature `pharma_erp_suite` activée). API : `/api/rest/pharma/index.php`.

Fonctionnalités PharmaPro mobile :
- **Scanner caméra** code-barres (caisse POS et recherche produits)
- **Affichage code-barres** sur chaque fiche produit (EAN/Code128)
- **Modification codes-barres** depuis la fiche produit (admin/pharmacien) : principal, alternatifs, scan
- **Rapports PDF** Bilan et Grand Livre (admin/comptable, icône PDF sur l’accueil PharmaPro)
- **Déverrouillage biométrique** (Plus → interrupteur empreinte / Face ID)

Après `flutter create .`, ajoutez la permission caméra dans `android/app/src/main/AndroidManifest.xml` :
```xml
<uses-permission android:name="android.permission.CAMERA" />
```

## Dépannage

- **« Erreur de connexion » / timeouts** : vérifiez que le téléphone et le PC (ou le serveur) sont sur le même réseau et que `baseUrl` pointe vers la bonne adresse (IP pour appareil réel).
- **Android : « Cleartext HTTP not permitted »** : pour du HTTP (sans HTTPS), ajoutez dans `android/app/src/main/AndroidManifest.xml` (dans `<application>`) :
  ```xml
  android:usesCleartextTraffic="true"
  ```
- **iOS : HTTP bloqué** : dans `ios/Runner/Info.plist`, ajoutez une exception pour votre domaine (ou pour localhost en dev) sous `NSAppTransportSecurity`.

## Licence

Utilisation dans le cadre du projet Efficasante.
