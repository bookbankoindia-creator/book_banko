import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/dashboard_card.dart';
import 'ai_course_materials_screen.dart';

class StandardDashboardScreen extends StatelessWidget {
  const StandardDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final activeStd = appState.activeStandardNumber;
    final isHigherSecondary = activeStd == 11 || activeStd == 12;
    final streamName = appState.selectedStreamName;

    final headerTitle = isHigherSecondary && streamName != null && streamName.isNotEmpty
        ? 'Standard $activeStd • $streamName'
        : 'Standard $activeStd';

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: BookBankoAppBar(
        title: headerTitle,
        subtitle: '${appState.selectedBoard} • ${appState.selectedMediumName}',
        showBackButton: true,
        actions: [
          if (isHigherSecondary)
            Padding(
              padding: const EdgeInsets.only(right: 8.0),
              child: TextButton.icon(
                onPressed: () {
                  Navigator.pushNamed(context, '/select_stream');
                },
                icon: const Icon(Icons.swap_horiz_rounded, color: AppColors.primary, size: 18),
                label: Text(
                  streamName ?? 'Stream',
                  style: GoogleFonts.plusJakartaSans(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                    fontSize: 12.5,
                  ),
                ),
                style: TextButton.styleFrom(
                  backgroundColor: AppColors.containerLightBlue,
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Hero Standard Status Card
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                margin: const EdgeInsets.only(bottom: 20),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFFEFF6FF), Color(0xFFDBEAFE)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.primaryBorder),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(
                        Icons.school_rounded,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Class Syllabus & Materials',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w800,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          Text(
                            '${appState.selectedBoard} Board • ${appState.selectedMediumName}${isHigherSecondary && streamName != null ? ' • $streamName' : ''}',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                              color: AppColors.primary,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (isHigherSecondary)
                      TextButton(
                        onPressed: () => Navigator.pushNamed(context, '/select_stream'),
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: Text(
                          'Switch',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primaryBlue,
                          ),
                        ),
                      ),
                  ],
                ),
              ),

              Text(
                'STUDY MODULES',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textSecondary,
                  letterSpacing: 0.8,
                ),
              ),
              const SizedBox(height: 12),

              Expanded(
                child: GridView.builder(
                  itemCount: DashboardOption.options.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 14,
                    mainAxisSpacing: 14,
                    childAspectRatio: 1.05,
                  ),
                  itemBuilder: (context, index) {
                    final item = DashboardOption.options[index];
                    return DashboardCard(
                      title: item.title,
                      icon: item.icon,
                      onTap: () {
                        if (item.slug == 'ai_course') {
                          appState.selectModule(slug: item.slug, title: item.title);
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => AiCourseMaterialsScreen(
                                standardNumber: activeStd,
                                standardName: 'Standard $activeStd',
                              ),
                            ),
                          );
                        } else {
                          appState.selectModule(slug: item.slug, title: item.title);
                          Navigator.pushNamed(context, '/textbooks');
                        }
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
