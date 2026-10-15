import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../theme/app_theme.dart';
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

    return Scaffold(
      appBar: AppBar(
        title: Text(
          isHigherSecondary && streamName != null && streamName.isNotEmpty
              ? 'Standard $activeStd - $streamName'
              : 'Standard $activeStd',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w700,
          ),
        ),
        centerTitle: false,
        backgroundColor: AppColors.primaryBlue,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios, color: Colors.white),
          onPressed: () {
            Navigator.pop(context);
          },
        ),
        actions: [
          if (isHigherSecondary)
            TextButton.icon(
              onPressed: () {
                Navigator.pushNamed(context, '/select_stream');
              },
              icon: const Icon(Icons.swap_horiz_rounded, color: Colors.white, size: 18),
              label: Text(
                streamName ?? 'Stream',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              style: TextButton.styleFrom(
                backgroundColor: Colors.white.withValues(alpha: 0.2),
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: AppColors.lightBlueBg,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.borderLight),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.school_rounded, color: AppColors.primaryBlue, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${appState.selectedBoard} • ${appState.selectedMediumName}${isHigherSecondary && streamName != null ? ' • $streamName Stream' : ''}',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primaryBlue,
                        ),
                      ),
                    ),
                    if (isHigherSecondary)
                      GestureDetector(
                        onTap: () => Navigator.pushNamed(context, '/select_stream'),
                        child: const Text(
                          'Change',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: AppColors.primaryBlue,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: GridView.builder(
                  itemCount: DashboardOption.options.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
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
