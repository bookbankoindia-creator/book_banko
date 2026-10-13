import 'dart:async';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';
import '../theme/app_theme.dart';
import 'api_services.dart';

/// Centralized In-House Custom Ad Engine for Book Banko.
/// 100% self-hosted, dynamic ads with video & image support, customizable skip timers,
/// and full admin panel control.
class AdService {
  static final AdService instance = AdService._internal();

  AdService._internal();

  bool _isInitialized = false;
  bool _adsEnabled = true;
  String _layout = 'modal'; // 'modal' or 'fullscreen'
  String _mediaType = 'image'; // 'image' or 'video'
  String _title = 'Best JEE & NEET Preparation Materials';
  String _subTitle =
      'Top question bank, chapter-wise test series & expert video solutions!';
  String _badgeText = 'SPECIAL PROMOTION';
  List<String> _pills = ['Verified', 'Top Ranks', 'High Yield'];
  String _imageUrl = '';
  String _videoUrl = '';
  String _targetUrl = '';
  String _buttonText = 'Explore Questions Now 🚀';
  int _skipSeconds = 3;
  bool _requireComplete = false;
  bool _autoClose = false;

  bool get isAdsEnabled => _adsEnabled;
  String get layout => _layout;
  String get mediaType => _mediaType;
  String get title => _title;
  String get subTitle => _subTitle;
  String get badgeText => _badgeText;
  List<String> get pills => _pills;
  String get imageUrl => _imageUrl;
  String get videoUrl => _videoUrl;
  String get targetUrl => _targetUrl;
  String get buttonText => _buttonText;
  int get skipSeconds => _skipSeconds;
  bool get requireComplete => _requireComplete;
  bool get autoClose => _autoClose;

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
      if (config != null && config['custom_ad'] is Map) {
        final customAd = Map<String, dynamic>.from(config['custom_ad']);
        _adsEnabled = customAd['enabled'] == true;
        _layout = customAd['layout']?.toString() ?? 'modal';
        _mediaType = customAd['media_type']?.toString() ?? 'image';

        if (customAd['title'] != null &&
            customAd['title'].toString().trim().isNotEmpty) {
          _title = customAd['title'].toString().trim();
        }
        if (customAd['sub_title'] != null &&
            customAd['sub_title'].toString().trim().isNotEmpty) {
          _subTitle = customAd['sub_title'].toString().trim();
        }
        if (customAd['badge_text'] != null &&
            customAd['badge_text'].toString().trim().isNotEmpty) {
          _badgeText = customAd['badge_text'].toString().trim();
        }
        if (customAd['pills'] != null) {
          final raw = customAd['pills'].toString();
          _pills = raw
              .split(',')
              .map((p) => p.trim())
              .where((p) => p.isNotEmpty)
              .toList();
          if (_pills.isEmpty) {
            _pills = ['Verified', 'Top Ranks', 'High Yield'];
          }
        }
        if (customAd['image_url'] != null) {
          _imageUrl = customAd['image_url'].toString().trim();
        }
        if (customAd['video_url'] != null) {
          _videoUrl = customAd['video_url'].toString().trim();
        }
        if (customAd['target_url'] != null) {
          _targetUrl = customAd['target_url'].toString().trim();
        }
        if (customAd['button_text'] != null &&
            customAd['button_text'].toString().trim().isNotEmpty) {
          _buttonText = customAd['button_text'].toString().trim();
        }
        if (customAd['skip_seconds'] != null) {
          _skipSeconds = int.tryParse(customAd['skip_seconds'].toString()) ?? 3;
        }
        _requireComplete = customAd['require_complete'] == true;
        _autoClose = customAd['auto_close'] == true;

        debugPrint(
            '[AdService] Synced Custom Ad: enabled=$_adsEnabled, layout=$_layout, media=$_mediaType, skip=${_skipSeconds}s');
      }
    } catch (e) {
      debugPrint('[AdService] Error syncing backend ad config: $e');
    }
  }

  /// No-op placeholder for backwards compatibility
  void loadInterstitialAd() {}

  /// Legacy helper method for backwards compatibility
  void showInterstitialAd({required VoidCallback onDismissed}) {
    onDismissed();
  }

  /// Displays the dynamic In-House Custom Ad.
  void showCustomAd(
    BuildContext context, {
    required String destinationUrl,
    required VoidCallback onDismissed,
  }) {
    if (!_adsEnabled) {
      debugPrint('[AdService] Custom ads disabled by admin. Proceeding.');
      onDismissed();
      return;
    }

    final effectiveTargetUrl =
        _targetUrl.trim().isNotEmpty ? _targetUrl.trim() : destinationUrl.trim();

    showDialog(
      context: context,
      barrierDismissible: false, // Guarded by countdown timer
      barrierColor: Colors.black.withValues(alpha: 0.70),
      builder: (BuildContext dialogContext) {
        return CustomAdDialog(
          layout: _layout,
          mediaType: _mediaType,
          title: _title,
          subTitle: _subTitle,
          badgeText: _badgeText,
          pills: _pills,
          imageUrl: _imageUrl,
          videoUrl: _videoUrl,
          targetUrl: effectiveTargetUrl,
          buttonText: _buttonText,
          skipSeconds: _skipSeconds,
          requireComplete: _requireComplete,
          autoClose: _autoClose,
          onClose: () {
            Navigator.of(dialogContext).pop();
            onDismissed();
          },
        );
      },
    );
  }
}

/// Rich, interactive In-House Custom Ad Dialog / Fullscreen Interstitial
class CustomAdDialog extends StatefulWidget {
  final String layout;
  final String mediaType;
  final String title;
  final String subTitle;
  final String badgeText;
  final List<String> pills;
  final String imageUrl;
  final String videoUrl;
  final String targetUrl;
  final String buttonText;
  final int skipSeconds;
  final bool requireComplete;
  final bool autoClose;
  final VoidCallback onClose;

  const CustomAdDialog({
    super.key,
    required this.layout,
    required this.mediaType,
    required this.title,
    required this.subTitle,
    required this.badgeText,
    required this.pills,
    required this.imageUrl,
    required this.videoUrl,
    required this.targetUrl,
    required this.buttonText,
    required this.skipSeconds,
    required this.requireComplete,
    required this.autoClose,
    required this.onClose,
  });

  @override
  State<CustomAdDialog> createState() => _CustomAdDialogState();
}

class _CustomAdDialogState extends State<CustomAdDialog> {
  Timer? _countdownTimer;
  int _remainingSeconds = 0;
  bool _canClose = false;
  VideoPlayerController? _videoController;
  bool _isVideoInitialized = false;
  bool _isVideoCompleted = false;

  @override
  void initState() {
    super.initState();
    _remainingSeconds = widget.skipSeconds;
    _canClose = widget.skipSeconds <= 0 && !widget.requireComplete;

    if (_remainingSeconds > 0) {
      _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) return;
        setState(() {
          if (_remainingSeconds > 1) {
            _remainingSeconds--;
          } else {
            _remainingSeconds = 0;
            _countdownTimer?.cancel();
            if (!widget.requireComplete || _isVideoCompleted) {
              _canClose = true;
            }
            if (widget.autoClose) {
              _handleAutoClose();
            }
          }
        });
      });
    }

    // Initialize Video Player if video media type
    if (widget.mediaType == 'video' && widget.videoUrl.isNotEmpty) {
      _initVideoPlayer();
    }
  }

  Future<void> _initVideoPlayer() async {
    try {
      final uri = Uri.tryParse(widget.videoUrl);
      if (uri != null) {
        _videoController = VideoPlayerController.networkUrl(uri);
        await _videoController!.initialize();
        _videoController!.setLooping(false);
        _videoController!.addListener(_onVideoProgress);
        await _videoController!.play();
        if (mounted) {
          setState(() {
            _isVideoInitialized = true;
          });
        }
      }
    } catch (e) {
      debugPrint('[AdService] Video player init error: $e');
    }
  }

  void _onVideoProgress() {
    if (_videoController == null || !mounted) return;
    final position = _videoController!.value.position;
    final duration = _videoController!.value.duration;

    if (duration > Duration.zero && position >= duration) {
      if (!_isVideoCompleted) {
        _isVideoCompleted = true;
        if (mounted) {
          setState(() {
            if (_remainingSeconds <= 0) {
              _canClose = true;
            }
          });
        }
        if (widget.autoClose) {
          _handleAutoClose();
        }
      }
    }
  }

  void _handleAutoClose() {
    Future.delayed(const Duration(milliseconds: 500), () {
      if (mounted) {
        widget.onClose();
      }
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _videoController?.removeListener(_onVideoProgress);
    _videoController?.dispose();
    super.dispose();
  }

  Future<void> _handleCtaClick() async {
    Navigator.of(context).pop();
    if (widget.targetUrl.isNotEmpty) {
      final uri = Uri.tryParse(widget.targetUrl);
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
    final isFullscreen = widget.layout == 'fullscreen';
    final mediaQuery = MediaQuery.of(context);
    final double maxDialogWidth = 410.0;
    final double dialogWidth = isFullscreen
        ? mediaQuery.size.width
        : (mediaQuery.size.width * 0.92 > maxDialogWidth
            ? maxDialogWidth
            : mediaQuery.size.width * 0.92);

    return BackdropFilter(
      filter: ImageFilter.blur(sigmaX: 5.0, sigmaY: 5.0),
      child: Center(
        child: Material(
          color: Colors.transparent,
          child: Container(
            width: dialogWidth,
            height: isFullscreen ? mediaQuery.size.height : null,
            margin: isFullscreen
                ? EdgeInsets.zero
                : const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(isFullscreen ? 0 : 24),
              boxShadow: isFullscreen
                  ? null
                  : [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.35),
                        blurRadius: 36,
                        offset: const Offset(0, 12),
                      ),
                    ],
            ),
            clipBehavior: Clip.antiAlias,
            child: SingleChildScrollView(
              physics: const ClampingScrollPhysics(),
              child: Column(
                mainAxisSize:
                    isFullscreen ? MainAxisSize.max : MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Top Media Banner with Badge & Timer/Close button
                  _buildMediaHeader(isFullscreen),

                  // Content Body Section
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Ad Headline
                        Text(
                          widget.title,
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
                          widget.subTitle,
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w400,
                            color: AppColors.textSecondary,
                            height: 1.45,
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Feature Pills
                        if (widget.pills.isNotEmpty)
                          Wrap(
                            spacing: 8,
                            runSpacing: 6,
                            children: widget.pills
                                .map((pill) => _buildFeaturePill(pill))
                                .toList(),
                          ),
                        const SizedBox(height: 18),

                        // Primary CTA Button
                        SizedBox(
                          width: double.infinity,
                          height: 50,
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
                                  color: AppColors.primary
                                      .withValues(alpha: 0.35),
                                  blurRadius: 14,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: ElevatedButton(
                              onPressed: _handleCtaClick,
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
                                      widget.buttonText,
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
                        const SizedBox(height: 10),

                        // Secondary Skip action
                        Center(
                          child: TextButton(
                            onPressed: _canClose ? widget.onClose : null,
                            style: TextButton.styleFrom(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 12, vertical: 4),
                              minimumSize: Size.zero,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            ),
                            child: Text(
                              _canClose
                                  ? 'Continue Reading PDF'
                                  : 'Ad will be skippable in ${_remainingSeconds}s',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 12,
                                color: _canClose
                                    ? AppColors.textMuted
                                    : AppColors.primary,
                                fontWeight: FontWeight.w600,
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
      ),
    );
  }

  Widget _buildMediaHeader(bool isFullscreen) {
    final double headerHeight = isFullscreen ? 260.0 : 190.0;

    return Stack(
      children: [
        // Media Content (Video or Image)
        if (widget.mediaType == 'video' && _isVideoInitialized)
          Container(
            height: headerHeight,
            width: double.infinity,
            color: Colors.black,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Center(
                  child: AspectRatio(
                    aspectRatio: _videoController!.value.aspectRatio,
                    child: VideoPlayer(_videoController!),
                  ),
                ),
                // Play/Pause Overlay Button
                GestureDetector(
                  onTap: () {
                    setState(() {
                      if (_videoController!.value.isPlaying) {
                        _videoController!.pause();
                      } else {
                        _videoController!.play();
                      }
                    });
                  },
                  child: Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.50),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.70),
                        width: 1.5,
                      ),
                    ),
                    child: Icon(
                      _videoController!.value.isPlaying
                          ? Icons.pause_rounded
                          : Icons.play_arrow_rounded,
                      color: Colors.white,
                      size: 32,
                    ),
                  ),
                ),
                // Video Progress Bar at bottom
                Positioned(
                  bottom: 0,
                  left: 0,
                  right: 0,
                  child: VideoProgressIndicator(
                    _videoController!,
                    allowScrubbing: false,
                    colors: const VideoProgressColors(
                      playedColor: AppColors.primary,
                      bufferedColor: Colors.white24,
                      backgroundColor: Colors.black45,
                    ),
                  ),
                ),
              ],
            ),
          )
        else if (widget.imageUrl.isNotEmpty)
          Image.network(
            widget.imageUrl,
            height: headerHeight,
            width: double.infinity,
            fit: BoxFit.cover,
            loadingBuilder: (context, child, loadingProgress) {
              if (loadingProgress == null) return child;
              return Container(
                height: headerHeight,
                color: AppColors.lightBlueBg,
                child: const Center(
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                ),
              );
            },
            errorBuilder: (context, error, stackTrace) =>
                _buildFallbackHeader(headerHeight),
          )
        else
          _buildFallbackHeader(headerHeight),

        // Promotional Top Pill Badge
        Positioned(
          top: 14,
          left: 14,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.65),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.35),
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
                  widget.badgeText,
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

        // Top Right: Countdown Pill or Close (X) Button
        Positioned(
          top: 12,
          right: 12,
          child: _canClose ? _buildCloseButton() : _buildCountdownBadge(),
        ),
      ],
    );
  }

  Widget _buildCloseButton() {
    return GestureDetector(
      onTap: widget.onClose,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.95),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.25),
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
    );
  }

  Widget _buildCountdownBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.70),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.30),
          width: 0.8,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.20),
            blurRadius: 6,
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 14,
            height: 14,
            child: CircularProgressIndicator(
              value: widget.skipSeconds > 0
                  ? 1.0 - (_remainingSeconds / widget.skipSeconds)
                  : 1.0,
              strokeWidth: 2.0,
              color: Colors.amber,
              backgroundColor: Colors.white24,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            widget.requireComplete && !_isVideoCompleted
                ? 'Watch to skip'
                : 'Skip in ${_remainingSeconds}s',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFallbackHeader(double height) {
    return Container(
      height: height,
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
                  color: Colors.white.withValues(alpha: 0.30),
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

  Widget _buildFeaturePill(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.lightBlueBg,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: AppColors.primaryBorder.withValues(alpha: 0.50),
          width: 0.8,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.verified_rounded, size: 12, color: AppColors.primary),
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
