import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/book_banko_button.dart';
import '../widgets/selection_tile.dart';

class SelectMediumScreen extends StatefulWidget {
  const SelectMediumScreen({super.key});

  @override
  State<SelectMediumScreen> createState() => _SelectMediumScreenState();
}

class _SelectMediumScreenState extends State<SelectMediumScreen> {
  late Future<List<MediumModel>> _mediumsFuture;

  @override
  void initState() {
    super.initState();
    _loadMediums();
  }

  void _loadMediums() {
    _mediumsFuture = ApiService.fetchMediums();
  }

  Future<void> _refreshMediums() async {
    setState(() {
      _loadMediums();
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    return Scaffold(
      appBar: const BookBankoAppBar(title: 'Select Medium', showBackButton: true),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Choose Medium for ${appState.selectedBoard}',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Select your preferred language medium of study',
                style: TextStyle(
                  fontSize: 14,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: FutureBuilder<List<MediumModel>>(
                  future: _mediumsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: CircularProgressIndicator(color: AppColors.primary),
                      );
                    }

                    final mediums = snapshot.data ?? [];
                    if (mediums.isEmpty) {
                      return RefreshIndicator(
                        onRefresh: _refreshMediums,
                        color: AppColors.primary,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: const [
                            SizedBox(height: 80),
                            Center(
                              child: Text(
                                'No mediums available',
                                style: TextStyle(color: AppColors.textSecondary),
                              ),
                            ),
                          ],
                        ),
                      );
                    }

                    // Auto-select first medium if none selected or selected medium not in list
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
                          return SelectionTile(
                            title: med.name,
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
}
