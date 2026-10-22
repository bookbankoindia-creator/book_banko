import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/app_theme.dart';

class DashboardCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final VoidCallback onTap;
  final String? badgeText;
  final Color? accentColor;
  final Color? accentBg;

  const DashboardCard({
    super.key,
    required this.title,
    required this.icon,
    required this.onTap,
    this.badgeText,
    this.accentColor,
    this.accentBg,
  });

  @override
  Widget build(BuildContext context) {
    final color = accentColor ?? _resolveColor(title);
    final bg = accentBg ?? _resolveBg(title);
    final badge = badgeText ?? _resolveBadge(title);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            color: AppColors.surfaceWhite,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.borderLight, width: 1.2),
            boxShadow: const [AppShadows.cardShadow],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (badge != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 6),
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    badge,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: color,
                    ),
                  ),
                ),
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: bg,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  icon,
                  color: color,
                  size: 24,
                ),
              ),
              const SizedBox(height: 10),
              Flexible(
                child: Text(
                  title,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                    height: 1.2,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static Color _resolveColor(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('textbook')) return AppColors.scienceGreen;
    if (lower.contains('pyq') || lower.contains('old')) return AppColors.notesAmber;
    if (lower.contains('paper')) return AppColors.mathBlue;
    if (lower.contains('blueprint')) return const Color(0xFF0D9488); // Teal
    if (lower.contains('m.imp') || lower.contains('imp')) return AppColors.examCrimson;
    if (lower.contains('ai')) return AppColors.aiViolet;
    return AppColors.primaryBlue;
  }

  static Color _resolveBg(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('textbook')) return AppColors.scienceGreenBg;
    if (lower.contains('pyq') || lower.contains('old')) return AppColors.notesAmberBg;
    if (lower.contains('paper')) return AppColors.mathBlueBg;
    if (lower.contains('blueprint')) return const Color(0xFFF0FDFA);
    if (lower.contains('m.imp') || lower.contains('imp')) return AppColors.examCrimsonBg;
    if (lower.contains('ai')) return AppColors.aiVioletBg;
    return AppColors.lightBlueBg;
  }

  static String? _resolveBadge(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('textbook')) return 'Syllabus';
    if (lower.contains('pyq')) return 'Past Papers';
    if (lower.contains('paper')) return 'Practice';
    if (lower.contains('blueprint')) return 'Format';
    if (lower.contains('m.imp')) return '⭐ High Score';
    if (lower.contains('ai')) return 'Robotics';
    return null;
  }
}
