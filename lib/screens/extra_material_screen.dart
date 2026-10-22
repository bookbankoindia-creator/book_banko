import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/book_banko_app_bar.dart';
import '../widgets/shimmer_loading.dart';

class ExtraMaterialScreen extends StatefulWidget {
  final String categorySlug;
  final String categoryTitle;

  const ExtraMaterialScreen({
    super.key,
    this.categorySlug = 'extra_material',
    this.categoryTitle = 'Extra Material',
  });

  @override
  State<ExtraMaterialScreen> createState() => _ExtraMaterialScreenState();
}

class _ExtraMaterialScreenState extends State<ExtraMaterialScreen> {
  List<ExtraMaterialModel>? _cachedMaterials;
  late Future<List<ExtraMaterialModel>> _materialsFuture;

  @override
  void initState() {
    super.initState();
    _cachedMaterials = ApiService.getCachedExtraMaterials(
      categorySlug: widget.categorySlug,
    );
    _loadMaterials();
  }

  void _loadMaterials() {
    _cachedMaterials ??= ApiService.getCachedExtraMaterials(
      categorySlug: widget.categorySlug,
    );
    _materialsFuture = ApiService.fetchExtraMaterials(
      categorySlug: widget.categorySlug,
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _materialsFuture = ApiService.fetchExtraMaterials(
        categorySlug: widget.categorySlug,
        forceRefresh: true,
      );
    });
  }

  void _openPdf(ExtraMaterialModel item, BookBankoAppState appState) {
    if (item.pdfUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No PDF file attached to this material.'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    // Set chapter in AppState so PdfReaderScreen loads it seamlessly
    appState.selectChapter(item.toChapterModel());
    Navigator.pushNamed(context, '/pdf_reader');
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: BookBankoAppBar(
        title: widget.categoryTitle,
        subtitle: '${appState.selectedBoard} • Std ${appState.activeStandardNumber}',
        showBackButton: true,
      ),
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
              child: Text(
                'IMP Questions, Blueprints & Solutions',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
            // Material List Content
            Expanded(
              child: RefreshIndicator(
                onRefresh: _refresh,
                color: AppColors.primary,
                child: FutureBuilder<List<ExtraMaterialModel>>(
                  initialData: _cachedMaterials,
                  future: _materialsFuture,
                  builder: (context, snapshot) {
                    final materials = snapshot.data ?? _cachedMaterials ?? [];
                    if (materials.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                      return const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 20),
                        child: ShimmerListPlaceholder(itemCount: 6),
                      );
                    }

                    if (materials.isEmpty) {
                      return ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          const SizedBox(height: 80),
                          Center(
                            child: Padding(
                              padding: const EdgeInsets.all(24.0),
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
                                      Icons.bookmark_outline_rounded,
                                      size: 32,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                  Text(
                                    'No ${widget.categoryTitle} Uploaded Yet',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 17,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'Upload PDFs for ${widget.categoryTitle} in your Book Banko Admin Panel to display here.',
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
                          ),
                        ],
                      );
                    }

                    return ListView.builder(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                      itemCount: materials.length,
                      itemBuilder: (context, index) {
                        final item = materials[index];

                        return Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          child: Material(
                            color: Colors.transparent,
                            child: InkWell(
                              onTap: () => _openPdf(item, appState),
                              borderRadius: BorderRadius.circular(18),
                              child: Container(
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: AppColors.surfaceWhite,
                                  borderRadius: BorderRadius.circular(18),
                                  border: Border.all(color: AppColors.borderLight, width: 1.2),
                                  boxShadow: const [AppShadows.subtleShadow],
                                ),
                                child: Row(
                                  children: [
                                    // PDF Icon Badge
                                    Container(
                                      width: 46,
                                      height: 46,
                                      decoration: BoxDecoration(
                                        color: AppColors.notesAmberBg,
                                        borderRadius: BorderRadius.circular(14),
                                      ),
                                      child: const Center(
                                        child: Icon(
                                          Icons.bookmark_rounded,
                                          color: AppColors.notesAmber,
                                          size: 24,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 14),

                                    // Material Details
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            item.title,
                                            style: GoogleFonts.plusJakartaSans(
                                              fontSize: 15,
                                              fontWeight: FontWeight.w700,
                                              color: AppColors.textPrimary,
                                              height: 1.25,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Row(
                                            children: [
                                              if (item.categoryName != null) ...[
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                                  decoration: BoxDecoration(
                                                    color: const Color(0xFFF1F5F9),
                                                    borderRadius: BorderRadius.circular(4),
                                                  ),
                                                  child: Text(
                                                    item.categoryName!,
                                                    style: GoogleFonts.plusJakartaSans(
                                                      fontSize: 10,
                                                      fontWeight: FontWeight.w700,
                                                      color: AppColors.textSecondary,
                                                    ),
                                                  ),
                                                ),
                                                const SizedBox(width: 8),
                                              ],
                                              if (item.pageCount > 0)
                                                Text(
                                                  '${item.pageCount} Pages',
                                                  style: GoogleFonts.plusJakartaSans(
                                                    fontSize: 11,
                                                    color: AppColors.textMuted,
                                                  ),
                                                ),
                                              if (item.fileSizeMb > 0) ...[
                                                const SizedBox(width: 8),
                                                Text(
                                                  '• ${item.fileSizeMb.toStringAsFixed(1)} MB',
                                                  style: GoogleFonts.plusJakartaSans(
                                                    fontSize: 11,
                                                    color: AppColors.textMuted,
                                                  ),
                                                ),
                                              ],
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),

                                    // Chevron / Read Button
                                    Container(
                                      width: 32,
                                      height: 32,
                                      decoration: const BoxDecoration(
                                        color: Color(0xFFF1F5F9),
                                        shape: BoxShape.circle,
                                      ),
                                      child: const Icon(
                                        Icons.chevron_right_rounded,
                                        color: AppColors.textSecondary,
                                        size: 20,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        );
                      },
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
