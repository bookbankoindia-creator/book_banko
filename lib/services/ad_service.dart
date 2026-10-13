import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import '../theme/app_theme.dart';
import 'api_services.dart';

/// Centralized In-House Custom Ad Engine for Book Banko.
/// Replaces third-party AdMob ads with 100% self-hosted, dynamic ads managed directly from the Admin Panel.
class AdService {
  static final AdService instance = AdService._internal();

  AdService._internal();

  bool _isInitialized = false;
  bool _adsEnabled = true;
  String _title = 'Best JEE & NEET Preparation Materials';
  String _subTitle =
      'Top question bank, chapter-wise test series & expert video solutions!';
  String _imageUrl = '';
  String _targetUrl = '';
  String _buttonText = 'Explore Questions Now 🚀';

  bool get isAdsEnabled => _adsEnabled;
  String get title => _title;
  String get subTitle => _subTitle;
  String get imageUrl => _imageUrl;
  String get targetUrl => _targetUrl;
  String get buttonText => _buttonText;

  /// Initialize and sync ad configuration from Admin Panel API
  Future<void> initialize() async {
    if (_isInitialized) return;
    _isInitialized = true;
    await syncConfigFromBackend();
  }

  /// Sync live ad configuration from Admin Panel (/backend/api/get_app_config.php)
  Future<void> syncConfigFromBackend() async {
    try {
      final config = await ApiService.getAppConfig();
      if (config != null) {
        if (config['custom_ad'] is Map) {
          final customAd = Map<String, dynamic>.from(config['custom_ad']);
          _adsEnabled = customAd['enabled'] == true;
          if (customAd['title'] != null &&
              customAd['title'].toString().trim().isNotEmpty) {
            _title = customAd['title'].toString().trim();
          }
          if (customAd['sub_title'] != null &&
              customAd['sub_title'].toString().trim().isNotEmpty) {
            _subTitle = customAd['sub_title'].toString().trim();
          }
          if (customAd['image_url'] != null) {
            _imageUrl = customAd['image_url'].toString().trim();
          }
          if (customAd['target_url'] != null) {
            _targetUrl = customAd['target_url'].toString().trim();
          }
          if (customAd['button_text'] != null &&
              customAd['button_text'].toString().trim().isNotEmpty) {
            _buttonText = customAd['button_text'].toString().trim();
          }
          debugPrint(
              '[AdService] Synced Custom Ad from Admin Panel: enabled=$_adsEnabled, title=$_title');
        } else if (config['admob_enabled'] != null) {
          _adsEnabled = config['admob_enabled'] == true;
        }
      }
    } catch (e) {
      debugPrint('[AdService] Error syncing backend ad config: $e');
    }
  }

  /// No-op placeholder to maintain backwards compatibility
  void loadInterstitialAd() {
    // Custom In-House ads are dynamic and do not require external SDK preloading
  }

  /// Legacy helper method for backwards compatibility
  void showInterstitialAd({required VoidCallback onDismissed}) {
    onDismissed();
  }

  /// Displays the full-screen dynamic In-House Custom Ad modal.
  /// When student taps (X) Close button, [onDismissed] is called.
  /// When student taps CTA button, the promotional target URL is launched (or falls back to [destinationUrl]).
  void showCustomAd(
    BuildContext context, {
    required String destinationUrl,
    required VoidCallback onDismissed,
  }) {
    if (!_adsEnabled) {
      debugPrint('[AdService] Custom ads disabled by admin. Proceeding directly.');
      onDismissed();
      return;
    }

    final effectiveTargetUrl =
        _targetUrl.trim().isNotEmpty ? _targetUrl.trim() : destinationUrl.trim();

    showDialog(
      context: context,
      barrierDismissible: true,
      barrierColor: Colors.black.withValues(alpha: 0.70),
      builder: (BuildContext dialogContext) {
        return CustomAdDialog(
          title: _title,
          subTitle: _subTitle,
          imageUrl: _imageUrl,
          targetUrl: effectiveTargetUrl,
          buttonText: _buttonText,
          onClose: () {
            Navigator.of(dialogContext).pop();
            onDismissed();
          },
        );
      },
    );
  }
}

/// Rich, animated dialog displaying the admin's custom ad
class CustomAdDialog extends StatelessWidget {
  final String title;
  final String subTitle;
  final String imageUrl;
  final String targetUrl;
  final String buttonText;
  final VoidCallback onClose;

  const CustomAdDialog({
    super.key,
    required this.title,
    required this.subTitle,
    required this.imageUrl,
    required this.targetUrl,
    required this.buttonText,
    required this.onClose,
  });

  Future<void> _handleCtaClick(BuildContext context) async {
    Navigator.of(context).pop();
    if (targetUrl.isNotEmpty) {
      final uri = Uri.tryParse(targetUrl);
      if (uri != null) {
        try {
          final launched = await launchUrl(
            uri,
            mode: LaunchMode.externalApplication,
          );
          if (!launched) {
            await launchUrl(uri, mode: LaunchMode.platformDefault);
          }
        } catch (e) {
          debugPrint('[AdService] Error launching ad target URL: $e');
          try {
            await launchUrl(uri, mode: LaunchMode.platformDefault);
          } catch (_) {}
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final mediaQuery = MediaQuery.of(context);
    final double maxDialogWidth = 400.0;
    final double dialogWidth = mediaQuery.size.width * 0.90 > maxDialogWidth
        ? maxDialogWidth
        : mediaQuery.size.width * 0.90;

    return BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 4.0, sigmaY: 4.0),
      child: Center(
        child: Material(
          color: Colors.transparent,
          child: Container(
            width: dialogWidth,
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.25),
                  blurRadius: 30,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Top Header with Banner Image and Floating Close Button
                Stack(
                  children: [
                    if (imageUrl.isNotEmpty)
                      Image.network(
                        imageUrl,
                        height: 190,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        loadingBuilder: (context, child, loadingProgress) {
                          if (loadingProgress == null) return child;
                          return Container(
                            height: 190,
                            color: AppColors.lightBlueBg,
                            child: const Center(
                              child: CircularProgressIndicator(strokeWidth: 2.5),
                            ),
                          );
                        },
                        errorBuilder: (context, error, stackTrace) =>
                            _buildFallbackHeader(),
                      )
                    else
                      _buildFallbackHeader(),

                    // Promoted / Sponsored Badge
                    Positioned(
                      top: 14,
                      left: 14,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.60),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.3),
                            width: 0.8,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.star_rounded,
                              size: 13,
                              color: Colors.amber,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              'SPECIAL PROMOTION',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Floating Close Button (X)
                    Positioned(
                      top: 12,
                      right: 12,
                      child: GestureDetector(
                        onTap: onClose,
                        child: Container(
                          width: 34,
                          height: 34,
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.92),
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.18),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.close_rounded,
                            size: 20,
                            color: Colors.black87,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),

                // Content Section
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Ad Headline
                      Text(
                        title,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                          height: 1.3,
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Ad Subtitle / Description
                      Text(
                        subTitle,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w400,
                          color: AppColors.textSecondary,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Feature Pills
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          _buildFeaturePill(Icons.verified_rounded, 'Verified'),
                          _buildFeaturePill(Icons.trending_up_rounded, 'Top Ranks'),
                          _buildFeaturePill(Icons.menu_book_rounded, 'High Yield'),
                        ],
                      ),
                      const SizedBox(height: 18),

                      // Primary CTA Button
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [
                                AppColors.primary,
                                Color(0xFF0284C7),
                              ],
                              begin: Alignment.centerLeft,
                              end: Alignment.centerRight,
                            ),
                            borderRadius: BorderRadius.circular(14),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.primary.withValues(alpha: 0.35),
                                blurRadius: 14,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: ElevatedButton(
                            onPressed: () => _handleCtaClick(context),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.transparent,
                              shadowColor: Colors.transparent,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Flexible(
                                  child: Text(
                                    buttonText,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                const Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 18,
                                  color: Colors.white,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Secondary "Skip / Continue to PDF" action
                      Center(
                        child: TextButton(
                          onPressed: onClose,
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 4),
                            minimumSize: Size.zero,
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          ),
                          child: Text(
                            'Continue Reading PDF',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 12,
                              color: AppColors.textMuted,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildFallbackHeader() {
    return Container(
      height: 170,
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Color(0xFF0A2E68),
            Color(0xFF0061A4),
            Color(0xFF0284C7),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.15),
                shape: BoxShape.circle,
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.3),
                  width: 1.5,
                ),
              ),
              child: const Icon(
                Icons.school_rounded,
                size: 40,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              'JEE & NEET Prep Exclusive',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: Colors.white,
                letterSpacing: 0.3,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFeaturePill(IconData icon, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.lightBlueBg,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: AppColors.primaryBorder.withValues(alpha: 0.5),
          width: 0.8,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: AppColors.primary),
          const SizedBox(width: 4),
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: AppColors.primary,
            ),
          ),
        ],
      ),
    );
  }
}
