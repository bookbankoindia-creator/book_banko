import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../models/app_state.dart';
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

    // Ensure splash is displayed nicely for a smooth duration (2 seconds)
    const minSplashDuration = Duration(milliseconds: 2000);
    final elapsed = DateTime.now().difference(startTime);
    if (elapsed < minSplashDuration) {
      await Future.delayed(minSplashDuration - elapsed);
    }

    if (!mounted) return;

    // Second time opening app (or whenever preferences are already stored):
    // Directly navigate to HomeScreen instead of showing Board/Medium/Standard selection again.
    if (hasSavedPreferences && appState.isOnboarded) {
      Navigator.pushReplacementNamed(context, '/home');
    } else {
      // First time opening app: guide the user through onboarding
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
            colors: [
              Color(0xFFF5F8FF),
              Color(0xFFE4EEFF),
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                const BookBankoLogo(size: 120),
                const SizedBox(height: 24),
                Text(
                  'Book Banko',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 32,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF0D254C),
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Your Smart Study App',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: const Color(0xFF3B629B),
                    letterSpacing: 0.2,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
