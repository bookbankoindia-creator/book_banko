import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/app_theme.dart';

class BookBankoCard extends StatelessWidget {
  final String title;
  final String? subtitle;
  final IconData icon;
  final VoidCallback onTap;
  final Color? iconColor;
  final Color? iconBgColor;
  final String? badgeText;

  const BookBankoCard({
    super.key,
    required this.title,
    this.subtitle,
    required this.icon,
    required this.onTap,
    this.iconColor,
    this.iconBgColor,
    this.badgeText,
  });

  @override
  Widget build(BuildContext context) {
    // Determine smart colors if not explicitly passed
    final resolvedIconColor = iconColor ?? _getDefaultIconColor(title);
    final resolvedIconBg = iconBgColor ?? _getDefaultBgColor(title);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surfaceWhite,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.borderLight, width: 1.2),
            boxShadow: const [AppShadows.cardShadow],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: resolvedIconBg,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      icon,
                      color: resolvedIconColor,
                      size: 24,
                    ),
                  ),
                  if (badgeText != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: resolvedIconBg,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        badgeText!,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: resolvedIconColor,
                        ),
                      ),
                    ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                      letterSpacing: -0.2,
                      height: 1.25,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      subtitle!,
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                        color: AppColors.textSecondary,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static Color _getDefaultIconColor(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('extra')) return AppColors.notesAmber;
    if (lower.contains('study')) return AppColors.scienceGreen;
    if (lower.contains('competitive') || lower.contains('exam')) return AppColors.examCrimson;
    if (lower.contains('higher')) return AppColors.higherEduNavy;
    if (lower.contains('ai')) return AppColors.aiViolet;
    return AppColors.primaryBlue;
  }

  static Color _getDefaultBgColor(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('extra')) return AppColors.notesAmberBg;
    if (lower.contains('study')) return AppColors.scienceGreenBg;
    if (lower.contains('competitive') || lower.contains('exam')) return AppColors.examCrimsonBg;
    if (lower.contains('higher')) return AppColors.higherEduBg;
    if (lower.contains('ai')) return AppColors.aiVioletBg;
    return AppColors.mathBlueBg;
  }
}
