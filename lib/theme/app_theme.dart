import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppColors {
  // Brand Colors - Modern Scholarly Palette
  static const Color primary = Color(0xFF1E3A8A);       // Royal Indigo Focus
  static const Color primaryDark = Color(0xFF0F172A);   // Midnight Slate
  static const Color primaryBlue = Color(0xFF2563EB);   // Vibrant Accent Blue
  static const Color primaryLight = Color(0xFF60A5FA);  // Soft Sky
  static const Color darkNavy = Color(0xFF0A192F);
  static const Color darkBlue = Color(0xFF1E40AF);
  
  // Warm Academic Accent
  static const Color accentAmber = Color(0xFFF59E0B);    // Gold / Amber
  static const Color accentAmberBg = Color(0xFFFEF3C7);  // Light Amber Tint

  // Category & Subject Color Tokens
  static const Color scienceGreen = Color(0xFF10B981);
  static const Color scienceGreenBg = Color(0xFFECFDF5);
  static const Color mathBlue = Color(0xFF2563EB);
  static const Color mathBlueBg = Color(0xFFEFF6FF);
  static const Color socialOrange = Color(0xFFF97316);
  static const Color socialOrangeBg = Color(0xFFFFF7ED);
  static const Color langPurple = Color(0xFF8B5CF6);
  static const Color langPurpleBg = Color(0xFFF5F3FF);
  static const Color examCrimson = Color(0xFFEF4444);
  static const Color examCrimsonBg = Color(0xFFFEF2F2);
  static const Color aiViolet = Color(0xFF1E3A8A);
  static const Color aiVioletBg = Color(0xFFEFF6FF);
  static const Color notesAmber = Color(0xFFD97706);
  static const Color notesAmberBg = Color(0xFFFFFBEB);
  static const Color higherEduNavy = Color(0xFF1E293B);
  static const Color higherEduBg = Color(0xFFF1F5F9);

  // Surface & Canvas (Eye-comfort slate)
  static const Color lightBlueBg = Color(0xFFF0F6FF);
  static const Color containerLightBlue = Color(0xFFEAF4FF);
  static const Color canvasBg = Color(0xFFF8FAFC);
  static const Color backgroundLight = Color(0xFFF8FAFC);
  static const Color surfaceWhite = Color(0xFFFFFFFF);
  
  // Text Slate Colors
  static const Color textPrimary = Color(0xFF0F172A);
  static const Color textSecondary = Color(0xFF64748B);
  static const Color textMuted = Color(0xFF94A3B8);
  
  // Borders & Dividers
  static const Color borderLight = Color(0xFFE2E8F0);
  static const Color borderSubtle = Color(0x1F64748B);
  static const Color primaryBorder = Color(0xFFBFDBFE);
  
  // Status Colors
  static const Color success = Color(0xFF22C55E);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFEF4444);
}

class AppShadows {
  static const BoxShadow cardShadow = BoxShadow(
    color: Color.fromRGBO(15, 23, 42, 0.06),
    blurRadius: 18,
    offset: Offset(0, 6),
    spreadRadius: -2,
  );

  static const BoxShadow subtleShadow = BoxShadow(
    color: Color.fromRGBO(0, 0, 0, 0.04),
    blurRadius: 8,
    offset: Offset(0, 2),
    spreadRadius: 0,
  );

  static const BoxShadow elevatedShadow = BoxShadow(
    color: Color.fromRGBO(30, 58, 138, 0.12),
    blurRadius: 28,
    offset: Offset(0, 10),
    spreadRadius: -4,
  );

  static const BoxShadow glowShadow = BoxShadow(
    color: Color.fromRGBO(99, 102, 241, 0.25),
    blurRadius: 20,
    offset: Offset(0, 8),
  );
}

class BookBankoTheme {
  static ThemeData get lightTheme {
    final baseFont = GoogleFonts.plusJakartaSans();

    return ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: AppColors.canvasBg,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppColors.primary,
        primary: AppColors.primary,
        secondary: AppColors.accentAmber,
        surface: AppColors.surfaceWhite,
        onSurface: AppColors.textPrimary,
        error: AppColors.error,
      ),
      fontFamily: baseFont.fontFamily,
      textTheme: TextTheme(
        headlineLarge: GoogleFonts.plusJakartaSans(
          fontSize: 30,
          fontWeight: FontWeight.w800,
          color: AppColors.textPrimary,
          letterSpacing: -0.5,
          height: 1.25,
        ),
        headlineMedium: GoogleFonts.plusJakartaSans(
          fontSize: 24,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
          letterSpacing: -0.3,
          height: 1.3,
        ),
        headlineSmall: GoogleFonts.plusJakartaSans(
          fontSize: 20,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
          letterSpacing: -0.2,
          height: 1.35,
        ),
        titleLarge: GoogleFonts.plusJakartaSans(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
        ),
        titleMedium: GoogleFonts.plusJakartaSans(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        titleSmall: GoogleFonts.plusJakartaSans(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        bodyLarge: GoogleFonts.plusJakartaSans(
          fontSize: 15,
          fontWeight: FontWeight.normal,
          color: AppColors.textPrimary,
          height: 1.5,
        ),
        bodyMedium: GoogleFonts.plusJakartaSans(
          fontSize: 13.5,
          fontWeight: FontWeight.normal,
          color: AppColors.textSecondary,
          height: 1.45,
        ),
        bodySmall: GoogleFonts.plusJakartaSans(
          fontSize: 12,
          fontWeight: FontWeight.normal,
          color: AppColors.textMuted,
        ),
        labelLarge: GoogleFonts.plusJakartaSans(
          fontSize: 14,
          fontWeight: FontWeight.w700,
          color: AppColors.surfaceWhite,
          letterSpacing: 0.1,
        ),
        labelMedium: GoogleFonts.plusJakartaSans(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.primary,
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.canvasBg,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        iconTheme: const IconThemeData(color: AppColors.textPrimary),
        titleTextStyle: GoogleFonts.plusJakartaSans(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
        ),
      ),
      cardTheme: CardThemeData(
        color: AppColors.surfaceWhite,
        elevation: 0,
        shadowColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: AppColors.borderLight, width: 1),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: GoogleFonts.plusJakartaSans(
            fontSize: 15,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFFF1F5F9),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.borderLight),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.borderLight),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.primaryBlue, width: 1.5),
        ),
        hintStyle: GoogleFonts.plusJakartaSans(
          color: AppColors.textMuted,
          fontSize: 14,
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.borderLight,
        thickness: 1,
        space: 1,
      ),
    );
  }
}
