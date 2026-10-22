import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/logo_widget.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _handleSplashNavigation();
  }

  Future<void> _handleSplashNavigation() async {
    final startTime = DateTime.now();

    // Load saved preferences from SharedPreferences into BookBankoAppState
    final appState = Provider.of<BookBankoAppState>(context, listen: false);
    final hasSavedPreferences = await appState.loadSavedPreferences();

    // Warm-up backend connection & pre-fetch data in the background so clicks are 0ms instant!
    ApiService.prefetchAppData(
      boardCode: appState.selectedBoard,
      mediumCode: appState.selectedMedium,
      standardNumber: appState.activeStandardNumber,
      streamSlug: appState.selectedStreamSlug,
    );

    // Smooth duration for beautiful entrance (1.5 seconds)
    const minSplashDuration = Duration(milliseconds: 1500);
    final elapsed = DateTime.now().difference(startTime);
    if (elapsed < minSplashDuration) {
      await Future.delayed(minSplashDuration - elapsed);
    }

    if (!mounted) return;

    // If user has not completed student login / registration, navigate to /login
    if (!appState.isLoggedIn) {
      Navigator.pushReplacementNamed(context, '/login');
    } else if (hasSavedPreferences && appState.isOnboarded) {
      // Returning registered student: navigate to home
      Navigator.pushReplacementNamed(context, '/home');
    } else {
      // Registered student who needs to select board
      Navigator.pushReplacementNamed(context, '/select_board');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFF8FAFC), Color(0xFFEFF6FF), Color(0xFFE0E7FF)],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              const Spacer(),
              // Animated Logo & Branding Entrance
              TweenAnimationBuilder<double>(
                tween: Tween<double>(begin: 0.0, end: 1.0),
                duration: const Duration(milliseconds: 900),
                curve: Curves.easeOutCubic,
                builder: (context, value, child) {
                  return Opacity(
                    opacity: value,
                    child: Transform.scale(
                      scale: 0.85 + (0.15 * value),
                      child: child,
                    ),
                  );
                },
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const BookBankoLogo(size: 110),
                    const SizedBox(height: 24),
                    Text(
                      'Book Banko',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 32,
                        fontWeight: FontWeight.w800,
                        color: AppColors.primaryDark,
                        letterSpacing: -0.6,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'Your Smart Digital Library',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primaryBlue,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              // Subtle Loading Line
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 60),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: const LinearProgressIndicator(
                    minHeight: 3,
                    backgroundColor: Color(0xFFCBD5E1),
                    valueColor: AlwaysStoppedAnimation<Color>(
                      AppColors.primaryBlue,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              // Footer Badge
              Text(
                'GSEB & CBSE • Textbooks & Study Materials',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                  letterSpacing: 0.2,
                ),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
