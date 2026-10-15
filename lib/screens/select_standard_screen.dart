import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/book_banko_button.dart';
import '../widgets/selection_tile.dart';

class SelectStandardScreen extends StatefulWidget {
  const SelectStandardScreen({super.key});

  @override
  State<SelectStandardScreen> createState() => _SelectStandardScreenState();
}

class _SelectStandardScreenState extends State<SelectStandardScreen> {
  Future<List<StandardModel>>? _standardsFuture;
  String? _lastBoard;
  String? _lastMedium;

  void _loadStandards(String boardCode, String mediumCode) {
    _lastBoard = boardCode;
    _lastMedium = mediumCode;
    _standardsFuture = ApiService.fetchStandards(
      boardCode: boardCode,
      mediumCode: mediumCode,
    );
  }

  Future<void> _refreshStandards(String boardCode, String mediumCode) async {
    setState(() {
      _loadStandards(boardCode, mediumCode);
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
      appBar: const BookBankoAppBar(
        title: 'Select Standard',
        showBackButton: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Choose Standard for ${appState.selectedBoard}',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Medium: ${appState.selectedMediumName}',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<StandardModel>>(
                  future: _standardsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: CircularProgressIndicator(color: AppColors.primaryBlue),
                      );
                    }

                    final standards = snapshot.data ?? [];
                    if (standards.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: () => _refreshStandards(appState.selectedBoard, appState.selectedMedium),
                        color: AppColors.primaryBlue,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            const SizedBox(height: 80),
                            Center(
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: const [
                                  Icon(
                                    Icons.school_outlined,
                                    size: 64,
                                    color: AppColors.textMuted,
                                  ),
                                  SizedBox(height: 16),
                                  Text(
                                    'No Standards Found',
                                    style: TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  SizedBox(height: 8),
                                  Text(
                                    'Add standards in your Admin Panel to display them here.',
                                    style: TextStyle(
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

                    // Auto-select first standard if none selected or selected standard not in list
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
                      color: AppColors.primaryBlue,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: standards.length,
                        itemBuilder: (context, index) {
                          final std = standards[index];
                          final isSelected =
                              appState.activeStandardNumber == std.number;
                          return SelectionTile(
                            title: std.name,
                            isSelected: isSelected,
                            onTap: () {
                              // Select only this standard for the home screen
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
