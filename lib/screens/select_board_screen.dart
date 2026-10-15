import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/book_banko_button.dart';
import '../widgets/selection_tile.dart';

class SelectBoardScreen extends StatefulWidget {
  const SelectBoardScreen({super.key});

  @override
  State<SelectBoardScreen> createState() => _SelectBoardScreenState();
}

class _SelectBoardScreenState extends State<SelectBoardScreen> {
  late Future<List<BoardModel>> _boardsFuture;

  @override
  void initState() {
    super.initState();
    _loadBoards();
  }

  void _loadBoards() {
    _boardsFuture = ApiService.fetchBoards();
  }

  Future<void> _refreshBoards() async {
    setState(() {
      _loadBoards();
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    return Scaffold(
      appBar: const BookBankoAppBar(title: 'Select Board', showBackButton: false),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Choose Your Education Board',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<BoardModel>>(
                  future: _boardsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: CircularProgressIndicator(color: AppColors.primary),
                      );
                    }

                    final boards = snapshot.data ?? [];
                    if (boards.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: _refreshBoards,
                        color: AppColors.primary,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: const [
                            SizedBox(height: 80),
                            Center(
                              child: Text(
                                'No boards available',
                                style: TextStyle(color: AppColors.textSecondary),
                              ),
                            ),
                          ],
                        ),
                      );
                    }

                    // Auto-select first board if none selected or selected board not in list
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
                          return SelectionTile(
                            title: board.code,
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
