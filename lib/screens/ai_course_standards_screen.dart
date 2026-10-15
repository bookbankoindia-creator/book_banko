import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import 'ai_course_materials_screen.dart';

class AiCourseStandardsScreen extends StatefulWidget {
  const AiCourseStandardsScreen({super.key});

  @override
  State<AiCourseStandardsScreen> createState() => _AiCourseStandardsScreenState();
}

class _AiCourseStandardsScreenState extends State<AiCourseStandardsScreen> {
  late Future<List<StandardModel>> _standardsFuture;

  @override
  void initState() {
    super.initState();
    _loadStandards();
  }

  void _loadStandards() {
    final appState = Provider.of<BookBankoAppState>(context, listen: false);
    _standardsFuture = ApiService.fetchStandards(
      boardCode: appState.selectedBoard,
      mediumCode: appState.selectedMedium,
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _loadStandards();
    });
  }

  void _onStandardSelected(int standardNumber, String standardName) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AiCourseMaterialsScreen(
          standardNumber: standardNumber,
          standardName: standardName,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: AppBar(
        title: Text(
          'AI Course Standards',
          style: GoogleFonts.plusJakartaSans(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w700,
          ),
        ),
        centerTitle: false,
        backgroundColor: AppColors.primaryBlue,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          color: AppColors.primaryBlue,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Premium Hero Banner
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF0F172A), Color(0xFF1E293B)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF0F172A).withValues(alpha: 0.25),
                        blurRadius: 16,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 56,
                        height: 56,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF6366F1), Color(0xFF8B5CF6)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: const Icon(
                          Icons.psychology_rounded,
                          color: Colors.white,
                          size: 32,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFF6366F1).withValues(alpha: 0.3),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: const Color(0xFF818CF8).withValues(alpha: 0.4)),
                              ),
                              child: const Text(
                                'AI & CODING CURRICULUM',
                                style: TextStyle(
                                  color: Color(0xFFA5B4FC),
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Select Your Standard',
                              style: GoogleFonts.plusJakartaSans(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Choose your class to access Python, Machine Learning, and AI study PDFs.',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.8),
                                fontSize: 12,
                                height: 1.3,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                Text(
                  'AVAILABLE STANDARDS FOR AI COURSE',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textSecondary,
                    letterSpacing: 1.0,
                  ),
                ),
                const SizedBox(height: 12),

                // FutureBuilder for standards list
                FutureBuilder<List<StandardModel>>(
                  future: _standardsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: Padding(
                          padding: EdgeInsets.symmetric(vertical: 40),
                          child: CircularProgressIndicator(color: AppColors.primaryBlue),
                        ),
                      );
                    }

                    var standards = snapshot.data ?? [];
                    if (standards.isEmpty) {
                      // Fallback standards 6 to 12
                      standards = List.generate(
                        7,
                        (index) => StandardModel(
                          number: index + 6,
                          name: 'Standard ${index + 6}',
                          requiresStream: index >= 5,
                        ),
                      );
                    }

                    return ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: standards.length,
                      separatorBuilder: (context, index) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final std = standards[index];
                        final isUserStandard = std.number == appState.activeStandardNumber;

                        return Material(
                          color: Colors.transparent,
                          child: InkWell(
                            onTap: () => _onStandardSelected(std.number, std.name),
                            borderRadius: BorderRadius.circular(16),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: isUserStandard
                                      ? const Color(0xFF6366F1)
                                      : AppColors.borderLight,
                                  width: isUserStandard ? 1.8 : 1,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.04),
                                    blurRadius: 10,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    width: 48,
                                    height: 48,
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(
                                        colors: isUserStandard
                                            ? [const Color(0xFF6366F1), const Color(0xFF8B5CF6)]
                                            : [AppColors.primaryBlue, AppColors.primaryDark],
                                        begin: Alignment.topLeft,
                                        end: Alignment.bottomRight,
                                      ),
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    child: Center(
                                      child: Text(
                                        '${std.number}',
                                        style: GoogleFonts.plusJakartaSans(
                                          color: Colors.white,
                                          fontSize: 18,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Text(
                                              std.name,
                                              style: GoogleFonts.plusJakartaSans(
                                                fontSize: 16,
                                                fontWeight: FontWeight.w700,
                                                color: AppColors.textPrimary,
                                              ),
                                            ),
                                            if (isUserStandard) ...[
                                              const SizedBox(width: 8),
                                              Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                decoration: BoxDecoration(
                                                  color: const Color(0xFFEEF2FF),
                                                  borderRadius: BorderRadius.circular(6),
                                                ),
                                                child: const Text(
                                                  'My Standard',
                                                  style: TextStyle(
                                                    color: Color(0xFF4F46E5),
                                                    fontSize: 10,
                                                    fontWeight: FontWeight.w700,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          'Artificial Intelligence, Coding & Problem Solving',
                                          style: TextStyle(
                                            fontSize: 12,
                                            color: AppColors.textSecondary,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Container(
                                    width: 34,
                                    height: 34,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF1F5F9),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: const Icon(
                                      Icons.arrow_forward_ios_rounded,
                                      color: AppColors.primaryBlue,
                                      size: 14,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
