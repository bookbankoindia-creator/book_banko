import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/shimmer_loading.dart';

class ChaptersScreen extends StatefulWidget {
  const ChaptersScreen({super.key});

  @override
  State<ChaptersScreen> createState() => _ChaptersScreenState();
}

class _ChaptersScreenState extends State<ChaptersScreen> {
  List<ChapterModel>? _cachedChapters;
  Future<List<ChapterModel>>? _chaptersFuture;
  int? _currentSubjectId;
  String? _currentModuleSlug;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final appState = Provider.of<BookBankoAppState>(context);
    final subject = appState.selectedSubject;
    final int? subjectDbId = subject != null
        ? (subject.databaseId > 0 ? subject.databaseId : int.tryParse(subject.id))
        : null;
    final moduleSlug = appState.selectedModuleSlug;

    if (_currentSubjectId != subjectDbId || _currentModuleSlug != moduleSlug || _chaptersFuture == null) {
      _currentSubjectId = subjectDbId;
      _currentModuleSlug = moduleSlug;
      _loadChapters(moduleSlug);
    }
  }

  void _loadChapters([String? moduleSlug]) {
    if (_currentSubjectId != null && _currentSubjectId! > 0) {
      final slug = moduleSlug ?? _currentModuleSlug ?? 'textbooks';
      // 1. Immediately grab cached chapters for 0ms instant display!
      final cached = ApiService.getCachedChapters(
        subjectId: _currentSubjectId!,
        moduleSlug: slug,
      );
      if (cached != null && cached.isNotEmpty) {
        _cachedChapters = cached;
      }

      // 2. Fetch fresh data (stale-while-revalidate)
      _chaptersFuture = ApiService.fetchChapters(
        subjectId: _currentSubjectId!,
        moduleSlug: slug,
      );
    } else {
      _chaptersFuture = Future.value([]);
    }
  }

  Future<void> _refresh() async {
    setState(() {
      final slug = _currentModuleSlug ?? 'textbooks';
      if (_currentSubjectId != null && _currentSubjectId! > 0) {
        _chaptersFuture = ApiService.fetchChapters(
          subjectId: _currentSubjectId!,
          moduleSlug: slug,
          forceRefresh: true,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final activeStd = appState.activeStandardNumber;
    final subject = appState.selectedSubject;
    final subjectName = subject?.name ?? 'Subject';
    final moduleTitle = appState.selectedModuleTitle;
    final moduleSlug = appState.selectedModuleSlug.toLowerCase();

    String sectionTitle = 'Chapters & Reading Material';
    if (moduleSlug == 'textbooks' || moduleSlug == 'textbook') {
      sectionTitle = 'Textbooks & Study Books';
    } else if (moduleSlug == 'blueprint') {
      sectionTitle = 'Exam Blueprints & Paper Formats';
    } else if (moduleSlug == 'pyqs') {
      sectionTitle = 'Previous Year Question Papers';
    } else if (moduleSlug == 'paper_sets') {
      sectionTitle = 'Practice Paper Sets';
    } else if (moduleSlug == 'mimp') {
      sectionTitle = 'Most Important Questions & Material';
    } else if (moduleTitle.isNotEmpty) {
      sectionTitle = '$moduleTitle Material';
    }

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: BookBankoAppBar(
        title: subjectName,
        subtitle: '$moduleTitle • Std $activeStd',
        showBackButton: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                sectionTitle,
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: FutureBuilder<List<ChapterModel>>(
                  initialData: _cachedChapters,
                  future: _chaptersFuture,
                  builder: (context, snapshot) {
                    final chapters = snapshot.data ?? _cachedChapters ?? [];

                    if (chapters.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const ShimmerListPlaceholder(itemCount: 6);
                    }

                    if (chapters.isEmpty) {
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
                                    'No $moduleTitle Uploaded Yet',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'Upload $moduleTitle PDF files for $subjectName (Std $activeStd) in Admin Panel.',
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
                        itemCount: chapters.length,
                        itemBuilder: (context, index) {
                          final chapter = chapters[index];
                          final formattedNumber = chapter.number < 10
                              ? '0${chapter.number}'
                              : '${chapter.number}';

                          final isTextbook = moduleSlug == 'textbooks' || moduleSlug == 'textbook';
                          final isBlueprint = moduleSlug == 'blueprint';
                          final isPyq = moduleSlug == 'pyqs';
                          final isPaperSet = moduleSlug == 'paper_sets';
                          final isMimp = moduleSlug == 'mimp';

                          final titleLower = chapter.title.toLowerCase();
                          final bool hasChapterWord = titleLower.contains('chapter') || 
                                                      titleLower.contains('ch-') || 
                                                      titleLower.contains('ch ') ||
                                                      titleLower.contains('ch.');
                          final bool isFullBook = isTextbook && (!hasChapterWord || chapter.number <= 1);

                          String badgeLabel;
                          if (isTextbook) {
                            badgeLabel = (hasChapterWord && chapter.number > 0)
                                ? 'CHAPTER ${chapter.number}'
                                : 'TEXTBOOK';
                          } else if (isBlueprint) {
                            badgeLabel = chapter.number > 1 ? 'BLUEPRINT ${chapter.number}' : 'BLUEPRINT';
                          } else if (isPyq) {
                            badgeLabel = chapter.number > 1 ? 'PAPER ${chapter.number}' : 'QUESTION PAPER';
                          } else if (isPaperSet) {
                            badgeLabel = chapter.number > 0 ? 'SET ${chapter.number}' : 'PAPER SET';
                          } else if (isMimp) {
                            badgeLabel = chapter.number > 0 ? 'PART ${chapter.number}' : 'M.IMP';
                          } else {
                            badgeLabel = (hasChapterWord || chapter.number > 0)
                                ? 'CHAPTER ${chapter.number}'
                                : moduleTitle.toUpperCase();
                          }

                          Widget leadingWidget;
                          if (isFullBook) {
                            leadingWidget = const Icon(
                              Icons.menu_book_rounded,
                              color: AppColors.primary,
                              size: 22,
                            );
                          } else if (isBlueprint) {
                            leadingWidget = const Icon(
                              Icons.architecture_rounded,
                              color: AppColors.primary,
                              size: 22,
                            );
                          } else if (isPyq) {
                            leadingWidget = const Icon(
                              Icons.description_rounded,
                              color: AppColors.primary,
                              size: 22,
                            );
                          } else {
                            leadingWidget = Text(
                              formattedNumber,
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: AppColors.primary,
                              ),
                            );
                          }

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            child: Material(
                              color: Colors.transparent,
                              child: InkWell(
                                onTap: () {
                                  appState.selectChapter(chapter);
                                  Navigator.pushNamed(context, '/pdf_reader');
                                },
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
                                        width: 44,
                                        height: 44,
                                        decoration: BoxDecoration(
                                          color: AppColors.containerLightBlue,
                                          borderRadius: BorderRadius.circular(14),
                                        ),
                                        child: Center(
                                          child: leadingWidget,
                                        ),
                                      ),
                                      const SizedBox(width: 16),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                              children: [
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                                  decoration: BoxDecoration(
                                                    color: const Color(0xFFF1F5F9),
                                                    borderRadius: BorderRadius.circular(4),
                                                  ),
                                                  child: Text(
                                                    badgeLabel,
                                                    style: GoogleFonts.plusJakartaSans(
                                                      fontSize: 10,
                                                      fontWeight: FontWeight.w800,
                                                      letterSpacing: 0.5,
                                                      color: AppColors.primaryBlue,
                                                    ),
                                                  ),
                                                ),
                                                if (chapter.pageCount > 0) ...[
                                                  const SizedBox(width: 8),
                                                  Text(
                                                    '${chapter.pageCount} Pages',
                                                    style: GoogleFonts.plusJakartaSans(
                                                      fontSize: 11,
                                                      fontWeight: FontWeight.w500,
                                                      color: AppColors.textMuted,
                                                    ),
                                                  ),
                                                ],
                                              ],
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              chapter.title,
                                              style: GoogleFonts.plusJakartaSans(
                                                fontSize: 15,
                                                fontWeight: FontWeight.w700,
                                                color: AppColors.textPrimary,
                                                height: 1.25,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Container(
                                        width: 32,
                                        height: 32,
                                        decoration: const BoxDecoration(
                                          color: Color(0xFFF1F5F9),
                                          shape: BoxShape.circle,
                                        ),
                                        child: const Icon(
                                          Icons.menu_book_rounded,
                                          color: AppColors.primaryBlue,
                                          size: 16,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
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
