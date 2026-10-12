import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'api_services.dart';

/// Centralized Google Mobile Ads (AdMob) Service for Book Banko
class AdService {
  static final AdService instance = AdService._internal();

  AdService._internal();

  bool _isInitialized = false;
  bool _adsEnabled = true;
  String? _customInterstitialAdUnitId;

  InterstitialAd? _interstitialAd;
  bool _isAdLoading = false;
  int _retryAttempt = 0;

  // Official Google AdMob Test Ad Unit IDs
  static const String testAndroidInterstitialId =
      'ca-app-pub-3940256099942544/1033173712';
  static const String testIosInterstitialId =
      'ca-app-pub-3940256099942544/4411468910';

  String get interstitialAdUnitId {
    if (_customInterstitialAdUnitId != null &&
        _customInterstitialAdUnitId!.trim().isNotEmpty) {
      return _customInterstitialAdUnitId!.trim();
    }
    if (kIsWeb) return '';
    return Platform.isAndroid
        ? testAndroidInterstitialId
        : testIosInterstitialId;
  }

  bool get isAdsEnabled => _adsEnabled;

  /// Initialize Google Mobile Ads SDK and preload the first Interstitial Ad
  Future<void> initialize() async {
    if (_isInitialized) return;

    try {
      if (!kIsWeb) {
        await MobileAds.instance.initialize();
      }
      _isInitialized = true;
      debugPrint('[AdService] Google Mobile Ads initialized successfully');

      // Fetch dynamic configuration from Book Banko backend
      syncConfigFromBackend();

      // Preload first Interstitial Ad so it is ready immediately when clicked
      loadInterstitialAd();
    } catch (e) {
      debugPrint('[AdService] Initialization warning: $e');
    }
  }

  /// Sync live AdMob configuration and unit IDs from Admin Panel
  Future<void> syncConfigFromBackend() async {
    try {
      final config = await ApiService.getAppConfig();
      if (config != null) {
        if (config['admob_enabled'] != null) {
          _adsEnabled = config['admob_enabled'] == true;
        }
        if (config['admob_interstitial_ad_unit_id'] != null &&
            config['admob_interstitial_ad_unit_id'].toString().trim().isNotEmpty) {
          _customInterstitialAdUnitId =
              config['admob_interstitial_ad_unit_id'].toString().trim();
        }
        debugPrint(
            '[AdService] Synced config: enabled=$_adsEnabled, unitId=$interstitialAdUnitId');
      }
    } catch (e) {
      debugPrint('[AdService] Error syncing backend ad config: $e');
    }
  }

  /// Preload Interstitial Ad into memory
  void loadInterstitialAd() {
    if (kIsWeb || !_adsEnabled || _isAdLoading || _interstitialAd != null) {
      return;
    }

    _isAdLoading = true;
    final adUnitId = interstitialAdUnitId;

    debugPrint('[AdService] Preloading Interstitial Ad with unitId: $adUnitId');

    InterstitialAd.load(
      adUnitId: adUnitId,
      request: const AdRequest(),
      adLoadCallback: InterstitialAdLoadCallback(
        onAdLoaded: (InterstitialAd ad) {
          debugPrint('[AdService] Interstitial Ad preloaded and ready to show');
          _interstitialAd = ad;
          _isAdLoading = false;
          _retryAttempt = 0;
        },
        onAdFailedToLoad: (LoadAdError error) {
          debugPrint('[AdService] Interstitial Ad failed to load: ${error.message} (Code: ${error.code})');
          _interstitialAd = null;
          _isAdLoading = false;
          _retryAttempt++;
          // Exponential backoff retry up to 3 times
          if (_retryAttempt <= 3) {
            Future.delayed(Duration(seconds: 2 * _retryAttempt), () {
              loadInterstitialAd();
            });
          }
        },
      ),
    );
  }

  /// Display the Interstitial Ad, executing [onDismissed] once closed or if unavailable
  void showInterstitialAd({required VoidCallback onDismissed}) {
    if (kIsWeb || !_adsEnabled) {
      debugPrint('[AdService] Ads disabled or web platform, proceeding directly');
      onDismissed();
      return;
    }

    if (_interstitialAd == null) {
      debugPrint('[AdService] Interstitial Ad not ready yet, loading for next time and proceeding');
      loadInterstitialAd();
      onDismissed();
      return;
    }

    bool hasExecutedCallback = false;
    void executeOnce() {
      if (!hasExecutedCallback) {
        hasExecutedCallback = true;
        onDismissed();
      }
    }

    _interstitialAd!.fullScreenContentCallback = FullScreenContentCallback(
      onAdShowedFullScreenContent: (InterstitialAd ad) {
        debugPrint('[AdService] Full-screen Interstitial Ad opened');
      },
      onAdDismissedFullScreenContent: (InterstitialAd ad) {
        debugPrint('[AdService] Interstitial Ad closed by user, opening target URL');
        ad.dispose();
        _interstitialAd = null;
        loadInterstitialAd(); // Preload next ad
        executeOnce();
      },
      onAdFailedToShowFullScreenContent: (InterstitialAd ad, AdError error) {
        debugPrint('[AdService] Interstitial Ad failed to show: ${error.message}');
        ad.dispose();
        _interstitialAd = null;
        loadInterstitialAd(); // Preload next ad
        executeOnce();
      },
    );

    try {
      _interstitialAd!.show();
    } catch (e) {
      debugPrint('[AdService] Error showing ad: $e');
      _interstitialAd = null;
      loadInterstitialAd();
      executeOnce();
    }
  }
}
