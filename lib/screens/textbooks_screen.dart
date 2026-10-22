import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/subject_tile.dart';

class TextbooksScreen extends StatefulWidget {
  const TextbooksScreen({super.key});

  @override
  State<TextbooksScreen> createState() => _TextbooksScreenState();
}

class _TextbooksScreenState extends State<TextbooksScreen> {
  List<SubjectModel>? _cachedSubjects;
  Future<List<SubjectModel>>? _subjectsFuture;
  String? _currentBoard;
  String? _currentMedium;
  int? _currentStandard;
  String? _currentStreamSlug;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final appState = Provider.of<BookBankoAppState>(context);
    final board = appState.selectedBoard.isNotEmpty ? appState.selectedBoard : 'GSEB';
    final medium = appState.selectedMedium;
    final standard = appState.activeStandardNumber;
    final streamSlug = appState.selectedStreamSlug ?? appState.selectedStream?.displayName.toLowerCase();

    if (_currentBoard != board ||
        _currentMedium != medium ||
        _currentStandard != standard ||
        _currentStreamSlug != streamSlug ||
        _subjectsFuture == null) {
      _currentBoard = board;
      _currentMedium = medium;
      _currentStandard = standard;
      _currentStreamSlug = streamSlug;
      _loadSubjects();
    }
  }

  void _loadSubjects() {
    if (_currentBoard != null && _currentMedium != null && _currentStandard != null) {
      // 1. Immediately grab cached subjects for 0ms instant display!
      final cached = ApiService.getCachedSubjects(
        boardCode: _currentBoard!,
        standardNumber: _currentStandard!,
        mediumCode: _currentMedium!,
        streamSlug: _currentStreamSlug,
      );
      if (cached != null && cached.isNotEmpty) {
        _cachedSubjects = cached;
      }

      // 2. Fetch fresh data (stale-while-revalidate)
      _subjectsFuture = ApiService.fetchSubjects(
        boardCode: _currentBoard!,
        standardNumber: _currentStandard!,
        mediumCode: _currentMedium!,
        streamSlug: _currentStreamSlug,
      );
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _subjectsFuture = ApiService.fetchSubjects(
        boardCode: _currentBoard!,
        standardNumber: _currentStandard!,
        mediumCode: _currentMedium!,
        streamSlug: _currentStreamSlug,
        forceRefresh: true,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final activeStd = appState.activeStandardNumber;
    final board = appState.selectedBoard;
    final moduleTitle = appState.selectedModuleTitle;
    final streamName = (activeStd == 11 || activeStd == 12) ? appState.selectedStreamName : null;

    final bannerText = streamName != null && streamName.isNotEmpty
        ? '$board • ${appState.selectedMediumName} • $streamName'
        : '$board • ${appState.selectedMediumName}';

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: BookBankoAppBar(
        title: '$moduleTitle - Std $activeStd',
        subtitle: bannerText,
        showBackButton: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Select a Subject to Read',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: FutureBuilder<List<SubjectModel>>(
                  initialData: _cachedSubjects,
                  future: _subjectsFuture,
                  builder: (context, snapshot) {
                    final subjects = snapshot.data ?? _cachedSubjects ?? [];

                    if (subjects.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const ShimmerListPlaceholder(itemCount: 6);
                    }

                    if (subjects.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: _refresh,
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
                                      Icons.menu_book_outlined,
                                      size: 32,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                  Text(
                                    'No Subjects Found',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'No subjects are currently listed for Standard $activeStd ($bannerText).',
                                    textAlign: TextAlign.center,
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 13,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                  const SizedBox(height: 20),
                                  ElevatedButton.icon(
                                    onPressed: _refresh,
                                    icon: const Icon(Icons.refresh),
                                    label: const Text('Refresh'),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      );
                    }

                    return RefreshIndicator(
                      onRefresh: _refresh,
                      color: AppColors.primary,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: subjects.length,
                        itemBuilder: (context, index) {
                          final subject = subjects[index];
                          return SubjectTile(
                            title: subject.name,
                            icon: subject.icon,
                            chapterCount: subject.chapterCount > 0 ? subject.chapterCount : null,
                            onTap: () {
                              appState.selectSubject(subject);
                              // Pre-fetch chapters so ChaptersScreen opens in 0ms!
                              final subjectDbId = subject.databaseId > 0
                                  ? subject.databaseId
                                  : int.tryParse(subject.id);
                              if (subjectDbId != null && subjectDbId > 0) {
                                ApiService.fetchChapters(
                                  subjectId: subjectDbId,
                                  moduleSlug: appState.selectedModuleSlug,
                                );
                              }
                              Navigator.pushNamed(context, '/chapters');
                            },
                          );
                        },
                      ),
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
