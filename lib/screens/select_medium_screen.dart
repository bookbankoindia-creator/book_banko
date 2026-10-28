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

class SelectMediumScreen extends StatefulWidget {
  const SelectMediumScreen({super.key});

  @override
  State<SelectMediumScreen> createState() => _SelectMediumScreenState();
}

class _SelectMediumScreenState extends State<SelectMediumScreen> {
  List<MediumModel>? _cachedMediums;
  late Future<List<MediumModel>> _mediumsFuture;

  @override
  void initState() {
    super.initState();
    _cachedMediums = ApiService.getCachedMediums();
    _loadMediums();
  }

  void _loadMediums() {
    _cachedMediums ??= ApiService.getCachedMediums();
    _mediumsFuture = ApiService.fetchMediums(forceRefresh: true);
  }

  Future<void> _refreshMediums() async {
    setState(() {
      _mediumsFuture = ApiService.fetchMediums(forceRefresh: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: const BookBankoAppBar(
        title: 'Study Medium',
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
                      'STEP 2 OF 3',
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
                'Choose Medium of Instruction',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Selected Board: ${appState.selectedBoard}',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primaryBlue,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<MediumModel>>(
                  initialData: _cachedMediums,
                  future: _mediumsFuture,
                  builder: (context, snapshot) {
                    final mediums = snapshot.data ?? _cachedMediums ?? [];
                    if (mediums.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const ShimmerListPlaceholder(itemCount: 3);
                    }
                    if (mediums.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: _refreshMediums,
                        color: AppColors.primary,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            const SizedBox(height: 80),
                            Center(
                              child: Text(
                                'No mediums available',
                                style: GoogleFonts.plusJakartaSans(
                                  color: AppColors.textSecondary,
                                  fontSize: 14,
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }

                    // Auto-select first medium if none selected
                    if (!mediums.any((m) => m.code.toLowerCase() == appState.selectedMedium.toLowerCase())) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted && mediums.isNotEmpty) {
                          appState.selectMedium(mediums.first.code, mediumName: mediums.first.name);
                        }
                      });
                    }

                    return RefreshIndicator(
                      onRefresh: _refreshMediums,
                      color: AppColors.primary,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: mediums.length,
                        itemBuilder: (context, index) {
                          final med = mediums[index];
                          final isSelected = appState.selectedMedium.toLowerCase() == med.code.toLowerCase();
                          final nativeTag = _getNativeTag(med.code);

                          return SelectionTile(
                            title: med.name,
                            tag: nativeTag,
                            icon: Icons.translate_rounded,
                            isSelected: isSelected,
                            onTap: () => appState.selectMedium(med.code, mediumName: med.name),
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
                icon: Icons.arrow_forward_rounded,
                onPressed: () {
                  appState.selectMedium(
                    appState.selectedMedium,
                    mediumName: appState.selectedMediumName,
                  );
                  Navigator.pushNamed(context, '/select_standard');
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String? _getNativeTag(String code) {
    switch (code.toLowerCase()) {
      case 'gujarati':
        return 'ગુજરાતી';
      case 'english':
        return 'English';
      case 'hindi':
        return 'हिन्दी';
      default:
        return null;
    }
  }
}
