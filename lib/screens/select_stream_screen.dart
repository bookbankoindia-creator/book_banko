import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/book_banko_button.dart';
import '../widgets/selection_tile.dart';

class SelectStreamScreen extends StatefulWidget {
  const SelectStreamScreen({super.key});

  @override
  State<SelectStreamScreen> createState() => _SelectStreamScreenState();
}

class _SelectStreamScreenState extends State<SelectStreamScreen> {
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
      _streamsFuture = ApiService.fetchStreams(
        boardCode: _currentBoard!,
        mediumCode: _currentMedium!,
        standardNumber: _currentStandard!,
      );
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _loadStreams();
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final activeStd = appState.activeStandardNumber;

    return Scaffold(
      appBar: const BookBankoAppBar(title: 'Select Stream'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Select Stream for Standard $activeStd',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Choose your academic stream for ${appState.selectedBoard} (${appState.selectedMediumName}).',
                style: const TextStyle(
                  fontSize: 14,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 24),
              Expanded(
                child: FutureBuilder<List<StreamModel>>(
                  future: _streamsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: CircularProgressIndicator(color: AppColors.primaryBlue),
                      );
                    }

                    final streams = snapshot.data ?? [];

                    if (streams.isEmpty) {
                      return Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(
                              Icons.school_outlined,
                              size: 56,
                              color: AppColors.textMuted,
                            ),
                            const SizedBox(height: 12),
                            const Text(
                              'No streams found for this standard.',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'Add streams in your Admin Panel under Streams Management.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
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

                    // Auto-select first stream if current selectedStreamModel is not set or not in list
                    if (appState.selectedStreamModel == null ||
                        !streams.any((s) => s.id == appState.selectedStreamModel!.id)) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted && streams.isNotEmpty) {
                          appState.selectStreamModel(streams.first);
                        }
                      });
                    }

                    return RefreshIndicator(
                      onRefresh: _refresh,
                      color: AppColors.primaryBlue,
                      child: ListView.builder(
                        itemCount: streams.length,
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemBuilder: (context, index) {
                          final stream = streams[index];
                          final isSelected = (appState.selectedStreamModel?.id == stream.id) ||
                              (appState.selectedStreamSlug?.toLowerCase() == stream.slug.toLowerCase()) ||
                              (appState.selectedStreamSlug?.toLowerCase() == stream.name.toLowerCase());

                          return SelectionTile(
                            title: stream.name,
                            isSelected: isSelected,
                            onTap: () => appState.selectStreamModel(stream),
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
                icon: Icons.arrow_forward_rounded,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

