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

class SelectStreamScreen extends StatefulWidget {
  const SelectStreamScreen({super.key});

  @override
  State<SelectStreamScreen> createState() => _SelectStreamScreenState();
}

class _SelectStreamScreenState extends State<SelectStreamScreen> {
  List<StreamModel>? _cachedStreams;
  Future<List<StreamModel>>? _streamsFuture;
  String? _currentBoard;
  String? _currentMedium;
  int? _currentStandard;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final appState = Provider.of<BookBankoAppState>(context);
    final board = appState.selectedBoard;
    final medium = appState.selectedMedium;
    final standard = appState.activeStandardNumber;

    if (_currentBoard != board ||
        _currentMedium != medium ||
        _currentStandard != standard ||
        _streamsFuture == null) {
      _currentBoard = board;
      _currentMedium = medium;
      _currentStandard = standard;
      _loadStreams();
    }
  }

  void _loadStreams() {
    if (_currentBoard != null && _currentMedium != null && _currentStandard != null) {
      final cached = ApiService.getCachedStreams(
        boardCode: _currentBoard!,
        mediumCode: _currentMedium!,
        standardNumber: _currentStandard!,
      );
      if (cached != null && cached.isNotEmpty) {
        _cachedStreams = cached;
      }
      _streamsFuture = ApiService.fetchStreams(
        boardCode: _currentBoard!,
        mediumCode: _currentMedium!,
        standardNumber: _currentStandard!,
      );
    }
  }

  Future<void> _refresh() async {
    setState(() {
      if (_currentBoard != null && _currentMedium != null && _currentStandard != null) {
        _streamsFuture = ApiService.fetchStreams(
          boardCode: _currentBoard!,
          mediumCode: _currentMedium!,
          standardNumber: _currentStandard!,
          forceRefresh: true,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final activeStd = appState.activeStandardNumber;

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: const BookBankoAppBar(title: 'Select Stream'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Select Stream for Standard $activeStd',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Choose your academic stream for ${appState.selectedBoard} (${appState.selectedMediumName}).',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13.5,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<StreamModel>>(
                  initialData: _cachedStreams,
                  future: _streamsFuture,
                  builder: (context, snapshot) {
                    final streams = snapshot.data ?? _cachedStreams ?? [];
                    if (streams.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const ShimmerListPlaceholder(itemCount: 3);
                    }

                    if (streams.isEmpty) {
                      return Center(
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
                              'No streams found for this standard.',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 17,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Add streams in your Admin Panel under Streams Management.',
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
                      );
                    }

                    // Auto-select first stream if current not set
                    final selectedStreamSlug = appState.selectedStreamSlug;
                    final hasValidSelection = streams.any((s) => s.slug == selectedStreamSlug);

                    if (!hasValidSelection && streams.isNotEmpty) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted) {
                          appState.selectStreamModel(streams.first);
                        }
                      });
                    }

                    return RefreshIndicator(
                      onRefresh: _refresh,
                      color: AppColors.primary,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: streams.length,
                        itemBuilder: (context, index) {
                          final stream = streams[index];
                          final isSelected = stream.slug == appState.selectedStreamSlug;
                          final icon = _getStreamIcon(stream.slug);
                          final tag = _getStreamTag(stream.slug);

                          return SelectionTile(
                            title: stream.name,
                            subtitle: stream.description ?? '${stream.name} Stream Syllabus',
                            tag: tag,
                            icon: icon,
                            isSelected: isSelected,
                            onTap: () {
                              appState.selectStreamModel(stream);
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
                text: 'Done & Go to Home',
                variant: ButtonVariant.gradient,
                icon: Icons.check_circle_rounded,
                onPressed: () async {
                  await appState.completeOnboarding();
                  if (context.mounted) {
                    Navigator.pushNamedAndRemoveUntil(
                      context,
                      '/home',
                      (route) => false,
                    );
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  static IconData _getStreamIcon(String slug) {
    final lower = slug.toLowerCase();
    if (lower.contains('sci')) return Icons.biotech_rounded;
    if (lower.contains('comm')) return Icons.trending_up_rounded;
    if (lower.contains('art')) return Icons.palette_rounded;
    return Icons.school_rounded;
  }

  static String? _getStreamTag(String slug) {
    final lower = slug.toLowerCase();
    if (lower.contains('sci')) return 'Science Group';
    if (lower.contains('comm')) return 'Commerce Group';
    if (lower.contains('art')) return 'Arts & Humanities';
    return null;
  }
}
