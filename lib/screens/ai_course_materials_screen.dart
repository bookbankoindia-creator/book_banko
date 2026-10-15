import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';

class AiCourseMaterialsScreen extends StatefulWidget {
  final int standardNumber;
  final String? standardName;

  const AiCourseMaterialsScreen({
    super.key,
    required this.standardNumber,
    this.standardName,
  });

  @override
  State<AiCourseMaterialsScreen> createState() => _AiCourseMaterialsScreenState();
}

class _AiCourseMaterialsScreenState extends State<AiCourseMaterialsScreen> {
  late Future<List<AiCourseMaterialModel>> _materialsFuture;
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadMaterials();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _loadMaterials() {
    _materialsFuture = ApiService.fetchAiCourseMaterials(
      standardNumber: widget.standardNumber,
      search: _searchQuery,
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _loadMaterials();
    });
  }

  void _onSearchChanged(String query) {
    setState(() {
      _searchQuery = query;
      _loadMaterials();
    });
  }

  void _openPdf(AiCourseMaterialModel item, BookBankoAppState appState) {
    if (item.pdfUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('This lesson has lecture notes only (no PDF attached).'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    appState.selectChapter(item.toChapterModel());
    Navigator.pushNamed(context, '/pdf_reader');
  }

  Future<void> _openVideo(String url) async {
    final uri = Uri.tryParse(url);
    if (uri != null && await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not open video tutorial link.'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final displayName = widget.standardName ?? 'Standard ${widget.standardNumber}';

    return Scaffold(
      backgroundColor: AppColors.canvasBg,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'AI Course • $displayName',
              style: GoogleFonts.plusJakartaSans(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 2),
            const Text(
              'Curriculum Notes & Study Material',
              style: TextStyle(
                color: Colors.white70,
                fontSize: 11,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        centerTitle: false,
        backgroundColor: AppColors.primaryBlue,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Search Input Header
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              child: TextField(
                controller: _searchController,
                onChanged: _onSearchChanged,
                decoration: InputDecoration(
                  hintText: 'Search chapters, modules, or topics...',
                  hintStyle: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                  prefixIcon: const Icon(Icons.search, color: AppColors.primaryBlue, size: 20),
                  suffixIcon: _searchQuery.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear, size: 18, color: Colors.grey),
                          onPressed: () {
                            _searchController.clear();
                            _onSearchChanged('');
                          },
                        )
                      : null,
                  filled: true,
                  fillColor: const Color(0xFFF1F5F9),
                  contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 16),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),

            // Material List Content
            Expanded(
              child: RefreshIndicator(
                onRefresh: _refresh,
                color: AppColors.primaryBlue,
                child: FutureBuilder<List<AiCourseMaterialModel>>(
                  future: _materialsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(
                        child: CircularProgressIndicator(color: AppColors.primaryBlue),
                      );
                    }

                    final materials = snapshot.data ?? [];

                    if (materials.isEmpty) {
                      return ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          const SizedBox(height: 80),
                          Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  width: 80,
                                  height: 80,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFEEF2FF),
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.psychology_rounded,
                                    size: 40,
                                    color: Color(0xFF6366F1),
                                  ),
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  _searchQuery.isNotEmpty
                                      ? 'No matching materials found'
                                      : 'No AI Course Material for $displayName',
                                  style: GoogleFonts.plusJakartaSans(
                                    fontSize: 17,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 40),
                                  child: Text(
                                    _searchQuery.isNotEmpty
                                        ? 'Try searching with different keywords.'
                                        : 'Materials added via the Admin Panel under "AI Course Materials" will appear here.',
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      fontSize: 13,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      );
                    }

                    return ListView.separated(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: materials.length,
                      separatorBuilder: (context, index) => const SizedBox(height: 14),
                      itemBuilder: (context, index) {
                        final item = materials[index];
                        final hasPdf = item.pdfUrl.isNotEmpty;
                        final hasVideo = item.videoUrl != null && item.videoUrl!.isNotEmpty;

                        return Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: AppColors.borderLight),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.04),
                                blurRadius: 10,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Module and Tag Badges
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  if (item.moduleName != null && item.moduleName!.isNotEmpty)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFEEF2FF),
                                        borderRadius: BorderRadius.circular(6),
                                        border: Border.all(color: const Color(0xFFC7D2FE)),
                                      ),
                                      child: Text(
                                        item.moduleName!,
                                        style: const TextStyle(
                                          color: Color(0xFF4338CA),
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    )
                                  else
                                    const SizedBox.shrink(),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFECFDF5),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Text(
                                      'FREE',
                                      style: TextStyle(
                                        color: Color(0xFF059669),
                                        fontSize: 10,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ],
                              ),

                              const SizedBox(height: 10),

                              // Title
                              Text(
                                item.title,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.textPrimary,
                                  height: 1.3,
                                ),
                              ),

                              // Description
                              if (item.description != null && item.description!.isNotEmpty) ...[
                                const SizedBox(height: 6),
                                Text(
                                  item.description!,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    color: AppColors.textSecondary,
                                    height: 1.4,
                                  ),
                                ),
                              ],

                              const SizedBox(height: 12),

                              // Metadata Info Row
                              Row(
                                children: [
                                  const Icon(Icons.menu_book_rounded, size: 14, color: AppColors.textMuted),
                                  const SizedBox(width: 4),
                                  Text(
                                    '${item.pageCount} Pages',
                                    style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                                  ),
                                  if (item.fileSizeMb > 0) ...[
                                    const SizedBox(width: 12),
                                    const Icon(Icons.data_usage_rounded, size: 14, color: AppColors.textMuted),
                                    const SizedBox(width: 4),
                                    Text(
                                      '${item.fileSizeMb.toStringAsFixed(1)} MB',
                                      style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                                    ),
                                  ],
                                ],
                              ),

                              const SizedBox(height: 14),

                              // Actions Row
                              Row(
                                children: [
                                  Expanded(
                                    child: ElevatedButton.icon(
                                      onPressed: () => _openPdf(item, appState),
                                      icon: const Icon(Icons.picture_as_pdf_rounded, size: 18),
                                      label: Text(
                                        hasPdf ? 'Read PDF' : 'View Notes',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                      ),
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: AppColors.primaryBlue,
                                        foregroundColor: Colors.white,
                                        padding: const EdgeInsets.symmetric(vertical: 10),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(12),
                                        ),
                                        elevation: 0,
                                      ),
                                    ),
                                  ),
                                  if (hasVideo) ...[
                                    const SizedBox(width: 10),
                                    OutlinedButton.icon(
                                      onPressed: () => _openVideo(item.videoUrl!),
                                      icon: const Icon(Icons.play_circle_fill_rounded, size: 18, color: Colors.red),
                                      label: const Text(
                                        'Tutorial',
                                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                      ),
                                      style: OutlinedButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(12),
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ],
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
