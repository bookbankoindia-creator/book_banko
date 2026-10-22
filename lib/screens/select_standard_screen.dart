import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/book_banko_button.dart';
import '../widgets/selection_tile.dart';
import '../widgets/shimmer_loading.dart';

class SelectStandardScreen extends StatefulWidget {
  const SelectStandardScreen({super.key});

  @override
  State<SelectStandardScreen> createState() => _SelectStandardScreenState();
}

class _SelectStandardScreenState extends State<SelectStandardScreen> {
  List<StandardModel>? _cachedStandards;
  Future<List<StandardModel>>? _standardsFuture;
  String? _lastBoard;
  String? _lastMedium;

  void _loadStandards(String boardCode, String mediumCode) {
    _lastBoard = boardCode;
    _lastMedium = mediumCode;
    _cachedStandards = ApiService.getCachedStandards(
      boardCode: boardCode,
      mediumCode: mediumCode,
    );
    _standardsFuture = ApiService.fetchStandards(
      boardCode: boardCode,
      mediumCode: mediumCode,
    );
  }

  Future<void> _refreshStandards(String boardCode, String mediumCode) async {
    setState(() {
      _standardsFuture = ApiService.fetchStandards(
        boardCode: boardCode,
        mediumCode: mediumCode,
        forceRefresh: true,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    if (_standardsFuture == null ||
        _lastBoard != appState.selectedBoard ||
        _lastMedium != appState.selectedMedium) {
      _loadStandards(appState.selectedBoard, appState.selectedMedium);
    }

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: const BookBankoAppBar(
        title: 'Class / Standard',
        showBackButton: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Step Progress Pill
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      'STEP 3 OF 3',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: AppColors.primary,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                'Choose Your Standard',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${appState.selectedBoard} • ${appState.selectedMediumName}',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primaryBlue,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<StandardModel>>(
                  initialData: _cachedStandards,
                  future: _standardsFuture,
                  builder: (context, snapshot) {
                    final standards = snapshot.data ?? _cachedStandards ?? [];
                    if (standards.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const ShimmerListPlaceholder(itemCount: 5);
                    }
                    if (standards.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: () => _refreshStandards(appState.selectedBoard, appState.selectedMedium),
                        color: AppColors.primary,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            const SizedBox(height: 80),
                            Center(
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Container(
                                    width: 64,
                                    height: 64,
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFF1F5F9),
                                      shape: BoxShape.circle,
                                    ),
                                    child: const Icon(
                                      Icons.school_outlined,
                                      size: 32,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                  Text(
                                    'No Standards Found',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'Add standards in your Admin Panel to display them here.',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 13,
                                      color: AppColors.textSecondary,
                                    ),
                                    textAlign: TextAlign.center,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      );
                    }

                    // Auto-select first standard if none selected
                    if (appState.activeStandardNumber == 0 ||
                        !standards.any((s) => s.number == appState.activeStandardNumber)) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted && standards.isNotEmpty) {
                          appState.setSingleStandard(standards.first.number);
                        }
                      });
                    }

                    return RefreshIndicator(
                      onRefresh: () => _refreshStandards(appState.selectedBoard, appState.selectedMedium),
                      color: AppColors.primary,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: standards.length,
                        itemBuilder: (context, index) {
                          final std = standards[index];
                          final isSelected = appState.activeStandardNumber == std.number;
                          final isBoardExam = std.number == 10 || std.number == 12;

                          return SelectionTile(
                            title: std.name,
                            tag: isBoardExam ? 'Board Exam' : null,
                            icon: Icons.menu_book_rounded,
                            isSelected: isSelected,
                            onTap: () {
                              appState.setSingleStandard(std.number);
                            },
                          );
                        },
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 12),
              BookBankoButton(
                text: 'Continue',
                variant: ButtonVariant.gradient,
                icon: Icons.check_circle_rounded,
                onPressed: () async {
                  final activeStd = appState.activeStandardNumber;
                  if (activeStd == 11 || activeStd == 12) {
                    Navigator.pushNamed(context, '/select_stream');
                  } else {
                    await appState.completeOnboarding();
                    if (context.mounted) {
                      Navigator.pushNamedAndRemoveUntil(
                        context,
                        '/home',
                        (route) => false,
                      );
                    }
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
