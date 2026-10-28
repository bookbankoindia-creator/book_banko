import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/app_theme.dart';

class SubjectTile extends StatelessWidget {
  final String title;
  final IconData icon;
  final VoidCallback onTap;
  final int? chapterCount;
  final String? subtitle;

  const SubjectTile({
    super.key,
    required this.title,
    required this.icon,
    required this.onTap,
    this.chapterCount,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    final color = _resolveSubjectColor(title);
    final bg = _resolveSubjectBg(title);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(18),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: AppColors.surfaceWhite,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.borderLight, width: 1.2),
              boxShadow: const [AppShadows.subtleShadow],
            ),
            child: Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    icon,
                    color: color,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Text(
                    title,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
                Container(
                  width: 32,
                  height: 32,
                  decoration: const BoxDecoration(
                    color: Color(0xFFF1F5F9),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.chevron_right_rounded,
                    color: AppColors.textSecondary,
                    size: 20,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  static Color _resolveSubjectColor(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('sci') || lower.contains('bio') || lower.contains('chem')) return AppColors.scienceGreen;
    if (lower.contains('math') || lower.contains('phy')) return AppColors.mathBlue;
    if (lower.contains('ss') || lower.contains('social')) return AppColors.socialOrange;
    if (lower.contains('guj') || lower.contains('eng') || lower.contains('hin') || lower.contains('sans')) return AppColors.langPurple;
    if (lower.contains('cs') || lower.contains('comp')) return AppColors.aiViolet;
    return AppColors.primaryBlue;
  }

  static Color _resolveSubjectBg(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('sci') || lower.contains('bio') || lower.contains('chem')) return AppColors.scienceGreenBg;
    if (lower.contains('math') || lower.contains('phy')) return AppColors.mathBlueBg;
    if (lower.contains('ss') || lower.contains('social')) return AppColors.socialOrangeBg;
    if (lower.contains('guj') || lower.contains('eng') || lower.contains('hin') || lower.contains('sans')) return AppColors.langPurpleBg;
    if (lower.contains('cs') || lower.contains('comp')) return AppColors.aiVioletBg;
    return AppColors.lightBlueBg;
  }
}
