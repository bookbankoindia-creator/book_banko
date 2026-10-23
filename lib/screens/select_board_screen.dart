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

class SelectBoardScreen extends StatefulWidget {
  const SelectBoardScreen({super.key});

  @override
  State<SelectBoardScreen> createState() => _SelectBoardScreenState();
}

class _SelectBoardScreenState extends State<SelectBoardScreen> {
  List<BoardModel>? _cachedBoards;
  late Future<List<BoardModel>> _boardsFuture;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        ScaffoldMessenger.of(context).clearSnackBars();
      }
    });
    _cachedBoards = ApiService.getCachedBoards();
    _loadBoards();
  }

  void _loadBoards() {
    _cachedBoards ??= ApiService.getCachedBoards();
    _boardsFuture = ApiService.fetchBoards();
  }

  Future<void> _refreshBoards() async {
    setState(() {
      _boardsFuture = ApiService.fetchBoards(forceRefresh: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: const BookBankoAppBar(
        title: 'Education Board',
        showBackButton: false,
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
                      'STEP 1 OF 3',
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
                'Choose Your Education Board',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Select your schooling board to customize syllabus & textbooks.',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13.5,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<BoardModel>>(
                  initialData: _cachedBoards,
                  future: _boardsFuture,
                  builder: (context, snapshot) {
                    final boards = snapshot.data ?? _cachedBoards ?? [];
                    if (boards.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const ShimmerListPlaceholder(itemCount: 4);
                    }
                    if (boards.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: _refreshBoards,
                        color: AppColors.primary,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            const SizedBox(height: 80),
                            Center(
                              child: Text(
                                'No boards available right now',
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

                    // Auto-select first board if none selected
                    if (!boards.any((b) => b.code == appState.selectedBoard)) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted && boards.isNotEmpty) {
                          appState.selectBoard(boards.first.code);
                        }
                      });
                    }

                    return RefreshIndicator(
                      onRefresh: _refreshBoards,
                      color: AppColors.primary,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: boards.length,
                        itemBuilder: (context, index) {
                          final board = boards[index];
                          final isSelected = appState.selectedBoard == board.code;
                          final isGseb = board.code.toUpperCase().contains('GSEB');
                          final isCbse = board.code.toUpperCase().contains('CBSE');

                          final tag = isGseb ? 'Gujarat' : (isCbse ? 'National' : null);
                          final subtitle = board.name.isNotEmpty && board.name != board.code
                              ? board.name
                              : (isGseb
                                  ? 'Gujarat State Board of Education'
                                  : (isCbse ? 'Central Board of Secondary Education' : null));

                          return SelectionTile(
                            title: board.code,
                            subtitle: subtitle,
                            tag: tag,
                            icon: Icons.account_balance_rounded,
                            isSelected: isSelected,
                            onTap: () => appState.selectBoard(board.code),
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
                  appState.selectBoard(appState.selectedBoard);
                  Navigator.pushNamed(context, '/select_medium');
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
