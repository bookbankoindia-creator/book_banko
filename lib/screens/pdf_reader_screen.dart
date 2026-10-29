import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:syncfusion_flutter_pdf/pdf.dart' as sf_pdf;
import 'package:syncfusion_flutter_pdfviewer/pdfviewer.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_config.dart';
import '../models/app_state.dart';
import '../models/data_models.dart';
import '../services/ad_service.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';

enum ReadingMode { light, sepia, dark }

class PdfReaderScreen extends StatefulWidget {
  const PdfReaderScreen({super.key});

  @override
  State<PdfReaderScreen> createState() => _PdfReaderScreenState();
}

class _PdfReaderScreenState extends State<PdfReaderScreen> {
  // In-memory cache for fast instantaneous PDF reopening
  static final Map<String, Uint8List> _enhancedPdfCache = {};

  late PdfViewerController _pdfViewerController;
  Uint8List? _pdfBytes;
  bool _isLoading = true;
  String? _errorMessage;
  int _currentPage = 1;
  int _totalPages = 0;
  String _currentLoadedUrl = '';
  ReadingMode _readingMode = ReadingMode.light;
  bool _showControls = true;

  @override
  void initState() {
    super.initState();
    _pdfViewerController = PdfViewerController();
    AdService.instance.loadInterstitialAd();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final appState = Provider.of<BookBankoAppState>(context, listen: false);
    final chapter = appState.selectedChapter ?? ChapterModel.mathChapters[0];

    if (_currentLoadedUrl != chapter.pdfUrl) {
      _currentLoadedUrl = chapter.pdfUrl;
      _loadPdf(chapter.pdfUrl, chapter, appState);
    }
  }

  void _showJumpToPageDialog() {
    if (_totalPages <= 1) return;
    final textController = TextEditingController(text: '$_currentPage');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.edit, color: AppColors.primaryBlue),
            SizedBox(width: 8),
            Text(
              'Go to Page',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Enter page number (1 - $_totalPages):',
              style: const TextStyle(
                fontSize: 14,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: textController,
              keyboardType: TextInputType.number,
              autofocus: true,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
              decoration: InputDecoration(
                hintText: 'e.g. 15',
                filled: true,
                fillColor: AppColors.lightBlueBg,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppColors.borderLight),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(
                    color: AppColors.primaryBlue,
                    width: 1.5,
                  ),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 12,
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text(
              'Cancel',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
          ElevatedButton(
            onPressed: () {
              final page = int.tryParse(textController.text.trim());
              if (page != null && page >= 1 && page <= _totalPages) {
                Navigator.pop(ctx);
                _pdfViewerController.jumpToPage(page);
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primaryBlue,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: const Text(
              'Go',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  String _normalizePdfUrl(String rawUrl) {
    if (rawUrl.isEmpty) return rawUrl;

    if (rawUrl.contains('supabase.co')) {
      return rawUrl;
    }

    final activeBase = ApiService.baseUrl;
    final backendRoot = activeBase.replaceAll('/api', '');

    if (rawUrl.contains('localhost/Book_Banko/backend')) {
      return rawUrl.replaceAll(
        'http://localhost/Book_Banko/backend',
        backendRoot,
      );
    }
    if (rawUrl.contains('192.168.0.102/Book_Banko/backend')) {
      return rawUrl.replaceAll(
        'http://192.168.0.102/Book_Banko/backend',
        backendRoot,
      );
    }
    if (rawUrl.contains('10.0.2.2/Book_Banko/backend')) {
      return rawUrl.replaceAll(
        'http://10.0.2.2/Book_Banko/backend',
        backendRoot,
      );
    }
    if (rawUrl.contains('127.0.0.1/Book_Banko/backend')) {
      return rawUrl.replaceAll(
        'http://127.0.0.1/Book_Banko/backend',
        backendRoot,
      );
    }
    return rawUrl;
  }

  Future<Uint8List> _flattenAndEnhancePdf(
    Uint8List rawBytes,
    ChapterModel chapter, [
    BookBankoAppState? appState,
  ]) async {
    try {
      final document = sf_pdf.PdfDocument(inputBytes: rawBytes);
      const defaultUrl =
          'https://docs.google.com/document/d/1KpHDVKmK_OJhktZPq0wOphWFlPFUJE2jIIJCiC_Fz30/edit?tab=t.0';

      // Only enable JEE & NEET question links for the "Textbooks" section
      final isTextbookSection =
          (chapter.moduleSlug == null ||
              chapter.moduleSlug == 'textbooks' ||
              chapter.moduleSlug == 'textbook') &&
          (appState?.selectedModuleSlug == 'textbooks' ||
              appState?.selectedModuleSlug == 'textbook' ||
              appState?.selectedModuleSlug == null ||
              appState?.selectedModuleSlug.isEmpty == true);

      for (int i = 0; i < document.pages.count; i++) {
        final page = document.pages[i];
        final pageNumber = i + 1;

        // 1. Add diagonal "Book Banko" watermark across center (on all pages for ALL PDF sections)
        try {
          final wmFont = sf_pdf.PdfStandardFont(
            sf_pdf.PdfFontFamily.helvetica,
            36,
            style: sf_pdf.PdfFontStyle.bold,
          );
          const wmText = 'Book Banko';
          final wmSize = wmFont.measureString(wmText);

          final gState = page.graphics.save();
          page.graphics.setTransparency(0.12);
          page.graphics.translateTransform(
            page.size.width / 2,
            page.size.height / 2,
          );
          page.graphics.rotateTransform(-32);
          page.graphics.drawString(
            wmText,
            wmFont,
            brush: sf_pdf.PdfSolidBrush(sf_pdf.PdfColor(0, 97, 164)),
            bounds: Rect.fromLTWH(
              -wmSize.width / 2,
              -wmSize.height / 2,
              wmSize.width,
              wmSize.height,
            ),
          );
          page.graphics.restore(gState);
        } catch (_) {}

        // 2. JEE & NEET Question Link: ONLY show on pages that have a specific page link configured in Admin Panel
        final pageLinkUrl = chapter.getLinkForPage(pageNumber);
        if (isTextbookSection && pageLinkUrl != null && pageLinkUrl.trim().isNotEmpty) {
          final targetPageUrl = pageLinkUrl.trim();

          if (targetPageUrl.trim().isNotEmpty) {
            try {
              // Full container dimensions at the bottom of the textbook page
              const containerMargin = 16.0;
              const containerHeight = 36.0;
              final containerWidth = page.size.width - (containerMargin * 2);
              final containerY = page.size.height - 46.0;
              final containerRect = Rect.fromLTWH(
                containerMargin,
                containerY,
                containerWidth,
                containerHeight,
              );

              // 1. Draw modern, stylish container background (soft light blue fill)
              page.graphics.drawRectangle(
                brush: sf_pdf.PdfSolidBrush(
                  sf_pdf.PdfColor(
                    239,
                    246,
                    255,
                  ), // #EFF6FF soft blue background
                ),
                bounds: containerRect,
              );

              // 2. Draw crisp container border (#2563EB blue)
              page.graphics.drawRectangle(
                pen: sf_pdf.PdfPen(
                  sf_pdf.PdfColor(37, 99, 235), // #2563EB border
                  width: 1.2,
                ),
                bounds: containerRect,
              );

              // 3. Draw centered text inside the full container (customized from Admin Panel)
              final linkText = chapter.getCustomLinkText();
              final fontSize = linkText.length > 50
                  ? 9.5
                  : (linkText.length > 38 ? 10.5 : 11.5);
              final font = sf_pdf.PdfStandardFont(
                sf_pdf.PdfFontFamily.helvetica,
                fontSize,
                style: sf_pdf.PdfFontStyle.bold,
              );
              final format = sf_pdf.PdfStringFormat(
                alignment: sf_pdf.PdfTextAlignment.center,
                lineAlignment: sf_pdf.PdfVerticalAlignment.middle,
              );

              page.graphics.drawString(
                linkText,
                font,
                brush: sf_pdf.PdfSolidBrush(
                  sf_pdf.PdfColor(29, 78, 216), // #1D4ED8 blue
                ),
                bounds: containerRect,
                format: format,
              );

              // 4. FULL CONTAINER CLICKABLE ANNOTATION:
              // Set the clickable URI annotation to cover the full container and entire footer band
              // so clicking anywhere in the container triggers and opens the link!
              final fullClickableRect = Rect.fromLTWH(
                0,
                containerY - 4,
                page.size.width,
                containerHeight + 8,
              );
              final uriAnnotation = sf_pdf.PdfUriAnnotation(
                bounds: fullClickableRect,
                uri: targetPageUrl.trim(),
              );
              page.annotations.add(uriAnnotation);
            } catch (_) {}
          }
        }
      }

      final output = await document.save();
      document.dispose();
      return Uint8List.fromList(output);
    } catch (e) {
      debugPrint('PDF enhancement warning: $e');
      return rawBytes;
    }
  }

  Future<void> _loadPdf(
    String url, [
    ChapterModel? chapter,
    BookBankoAppState? appState,
  ]) async {
    final activeChapter = chapter ?? ChapterModel.mathChapters[0];
    final cacheKey =
        '${activeChapter.title}_${activeChapter.pdfUrl}_${activeChapter.pageLinks ?? ''}_${appState?.selectedModuleSlug ?? ''}';

    // 0ms Instant Load if this chapter PDF was already processed & cached!
    if (_enhancedPdfCache.containsKey(cacheKey)) {
      setState(() {
        _pdfBytes = _enhancedPdfCache[cacheKey];
        _currentPage = 1;
        _isLoading = false;
        _errorMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      Uint8List? downloadedBytes;
      final normalizedUrl = _normalizePdfUrl(url);

      if (normalizedUrl.startsWith('http://') ||
          normalizedUrl.startsWith('https://')) {
        try {
          final response = await http
              .get(Uri.parse(normalizedUrl))
              .timeout(const Duration(seconds: 120));
          if (response.statusCode == 200 && response.bodyBytes.isNotEmpty) {
            downloadedBytes = response.bodyBytes;
          }
        } catch (e) {
          debugPrint('Primary PDF download error: $e');
        }
      }

      // Fallback 1: Direct Supabase public CDN URL
      if (downloadedBytes == null && !normalizedUrl.contains('supabase.co')) {
        final filename = url.split('/').last.split('?').first;
        if (filename.isNotEmpty) {
          final cdnUrl =
              '${AppConfig.supabaseUrl}/storage/v1/object/public/pdfs/$filename';
          try {
            final res = await http
                .get(Uri.parse(cdnUrl))
                .timeout(const Duration(seconds: 60));
            if (res.statusCode == 200 && res.bodyBytes.isNotEmpty) {
              downloadedBytes = res.bodyBytes;
            }
          } catch (_) {}
        }
      }

      // Fallback 2: Serverless uploads URL
      if (downloadedBytes == null) {
        final filename = url.split('/').last.split('?').first;
        final fallbackUrl = _normalizePdfUrl(
          '${ApiService.baseUrl.replaceAll('/api', '')}/uploads/pdfs/$filename',
        );
        try {
          final res = await http
              .get(Uri.parse(fallbackUrl))
              .timeout(const Duration(seconds: 120));
          if (res.statusCode == 200 && res.bodyBytes.isNotEmpty) {
            downloadedBytes = res.bodyBytes;
          }
        } catch (e) {
          debugPrint('Fallback PDF download error: $e');
        }
      }

      if (downloadedBytes == null || downloadedBytes.isEmpty) {
        throw Exception(
          'Could not connect to PDF cloud server. Please check your internet connection.',
        );
      }

      final activeChapter = chapter ?? ChapterModel.mathChapters[0];
      final processedBytes = await _flattenAndEnhancePdf(
        downloadedBytes,
        activeChapter,
        appState,
      );

      // Save to memory cache for 0ms instant display next time
      _enhancedPdfCache[cacheKey] = processedBytes;

      if (!mounted) return;

      setState(() {
        _pdfBytes = processedBytes;
        _currentPage = 1;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Could not load PDF document.\n$e';
      });
    }
  }

  @override
  void dispose() {
    _pdfViewerController.dispose();
    super.dispose();
  }

  Color get _viewportBgColor {
    switch (_readingMode) {
      case ReadingMode.light:
        return const Color(0xFFF1F5F9);
      case ReadingMode.sepia:
        return const Color(0xFFFBF0D9);
      case ReadingMode.dark:
        return const Color(0xFF121212);
    }
  }

  Color get _barColor {
    switch (_readingMode) {
      case ReadingMode.light:
        return AppColors.primary;
      case ReadingMode.sepia:
        return const Color(0xFF452B1E);
      case ReadingMode.dark:
        return const Color(0xFF1E1E24);
    }
  }

  @override
  Widget build(BuildContext context) {
    final appState = Provider.of<BookBankoAppState>(context);
    final chapter = appState.selectedChapter ?? ChapterModel.mathChapters[0];
    final subject = appState.selectedSubject;
    final subjectTitle = subject?.name.isNotEmpty == true
        ? subject!.name
        : chapter.title;
    final standardText = appState.activeStandardNumber > 0
        ? 'ધોરણ – ${appState.activeStandardNumber}'
        : 'ધોરણ – 10';

    return Scaffold(
      backgroundColor: _viewportBgColor,
      appBar: _showControls
          ? AppBar(
              backgroundColor: _barColor,
              elevation: 0,
              centerTitle: false,
              leading: IconButton(
                icon: const Icon(
                  Icons.arrow_back_ios_new_rounded,
                  color: Colors.white,
                  size: 20,
                ),
                onPressed: () => Navigator.pop(context),
              ),
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    subjectTitle,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.2,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 1),
                  Text(
                    (appState.selectedModuleSlug == 'textbooks' ||
                            appState.selectedModuleSlug == 'textbook')
                        ? (chapter.title.isNotEmpty &&
                                  !chapter.title.toLowerCase().contains(
                                    'chapter',
                                  )
                              ? '${chapter.title} • $standardText'
                              : 'Textbook • $standardText')
                        : 'Chapter ${chapter.number} • $standardText',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.85),
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
              actions: [
                // Eye-Comfort Reading Mode Toggle
                PopupMenuButton<ReadingMode>(
                  icon: const Icon(
                    Icons.palette_outlined,
                    color: Colors.white,
                    size: 22,
                  ),
                  tooltip: 'Reading Mode',
                  onSelected: (mode) {
                    setState(() {
                      _readingMode = mode;
                    });
                  },
                  itemBuilder: (ctx) => [
                    PopupMenuItem(
                      value: ReadingMode.light,
                      child: Row(
                        children: [
                          Icon(
                            Icons.wb_sunny_rounded,
                            color: _readingMode == ReadingMode.light
                                ? AppColors.primaryBlue
                                : Colors.grey,
                          ),
                          const SizedBox(width: 10),
                          const Text('Light Mode'),
                        ],
                      ),
                    ),
                    PopupMenuItem(
                      value: ReadingMode.sepia,
                      child: Row(
                        children: [
                          Icon(
                            Icons.menu_book_rounded,
                            color: _readingMode == ReadingMode.sepia
                                ? Colors.amber[800]
                                : Colors.grey,
                          ),
                          const SizedBox(width: 10),
                          const Text('Sepia (Eye Comfort)'),
                        ],
                      ),
                    ),
                    PopupMenuItem(
                      value: ReadingMode.dark,
                      child: Row(
                        children: [
                          Icon(
                            Icons.dark_mode_rounded,
                            color: _readingMode == ReadingMode.dark
                                ? Colors.indigo
                                : Colors.grey,
                          ),
                          const SizedBox(width: 10),
                          const Text('Dark Mode'),
                        ],
                      ),
                    ),
                  ],
                ),
                // Fullscreen toggle
                IconButton(
                  icon: const Icon(
                    Icons.fullscreen_rounded,
                    color: Colors.white,
                    size: 24,
                  ),
                  tooltip: 'Fullscreen Reading',
                  onPressed: () {
                    setState(() {
                      _showControls = false;
                    });
                  },
                ),
                const SizedBox(width: 4),
              ],
            )
          : null,
      body: Stack(
        children: [
          // Centered PDF Viewport
          GestureDetector(
            onTap: () {
              setState(() {
                _showControls = !_showControls;
              });
            },
            child: Container(
              width: double.infinity,
              height: double.infinity,
              color: _viewportBgColor,
              padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 4),
              alignment: Alignment.center,
              child: _buildPdfView(chapter, appState),
            ),
          ),

          // Floating Fullscreen Exit Button when controls are hidden
          if (!_showControls)
            Positioned(
              top: 40,
              right: 16,
              child: SafeArea(
                child: InkWell(
                  onTap: () {
                    setState(() {
                      _showControls = true;
                    });
                  },
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.6),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.fullscreen_exit_rounded,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),
                ),
              ),
            ),

          // Bottom Bar Overlay
          if (_showControls)
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: _buildBottomNavigationBar(),
            ),
        ],
      ),
    );
  }

  Widget _buildPdfView(ChapterModel chapter, BookBankoAppState appState) {
    if (_isLoading) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            CircularProgressIndicator(color: _barColor),
            const SizedBox(height: 16),
            const Text(
              'Loading PDF Document...',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      );
    }

    if (_errorMessage != null || _pdfBytes == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const Icon(
                Icons.picture_as_pdf_rounded,
                size: 64,
                color: AppColors.error,
              ),
              const SizedBox(height: 16),
              const Text(
                'Failed to Load PDF',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                _errorMessage ?? 'Unable to render the uploaded PDF file.',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: () => _loadPdf(_currentLoadedUrl, chapter, appState),
                icon: const Icon(Icons.refresh),
                label: const Text('Try Again'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 12,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    // Pure Syncfusion Flutter PDF Viewer: renders the PDF page and all hyperlinks embedded inside the PDF
    return Center(
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: SfPdfViewer.memory(
          _pdfBytes!,
          controller: _pdfViewerController,
          enableDoubleTapZooming: true,
          pageSpacing: 8,
          canShowScrollHead: false,
          canShowScrollStatus: false,
          canShowPaginationDialog: false,
          canShowHyperlinkDialog: false,
          enableHyperlinkNavigation: true,
          pageLayoutMode: PdfPageLayoutMode.single,
          scrollDirection: PdfScrollDirection.horizontal,
          onDocumentLoaded: (PdfDocumentLoadedDetails details) {
            setState(() {
              _totalPages = details.document.pages.count;
            });
          },
          onPageChanged: (PdfPageChangedDetails details) {
            setState(() {
              _currentPage = details.newPageNumber;
            });
          },
          onHyperlinkClicked: (PdfHyperlinkClickedDetails details) async {
            final rawUri = details.uri.trim();
            final uri = Uri.tryParse(rawUri);
            if (uri != null) {
              debugPrint('Hyperlink clicked: $rawUri -> Triggering Custom Ad');
              AdService.instance.showCustomAd(
                context,
                destinationUrl: rawUri,
                onDismissed: () async {
                  try {
                    final launched = await launchUrl(
                      uri,
                      mode: LaunchMode.externalApplication,
                    );
                    if (!launched) {
                      await launchUrl(uri, mode: LaunchMode.platformDefault);
                    }
                  } catch (e) {
                    debugPrint('Hyperlink launch error: $e');
                    try {
                      await launchUrl(uri, mode: LaunchMode.platformDefault);
                    } catch (_) {}
                  }
                },
              );
            }
          },
        ),
      ),
    );
  }

  Widget _buildBottomNavigationBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: _barColor,
        boxShadow: const [
          BoxShadow(
            color: Color(0x33000000),
            blurRadius: 10,
            offset: Offset(0, -3),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            // Left: Page Count Indicator (tap to jump)
            InkWell(
              onTap: _showJumpToPageDialog,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '$_currentPage / ${_totalPages > 0 ? _totalPages : 1}',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: 0.3,
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Icon(
                      Icons.edit_rounded,
                      color: Colors.white70,
                      size: 16,
                    ),
                  ],
                ),
              ),
            ),

            // Right: Previous (<) and Next (>) Page Navigation buttons
            Row(
              children: [
                IconButton(
                  icon: Icon(
                    Icons.chevron_left_rounded,
                    color: _currentPage > 1 ? Colors.white : Colors.white38,
                    size: 34,
                  ),
                  tooltip: 'Previous Page',
                  onPressed: _currentPage > 1
                      ? () {
                          _pdfViewerController.previousPage();
                        }
                      : null,
                ),
                const SizedBox(width: 8),
                IconButton(
                  icon: Icon(
                    Icons.chevron_right_rounded,
                    color: _currentPage < _totalPages
                        ? Colors.white
                        : Colors.white38,
                    size: 34,
                  ),
                  tooltip: 'Next Page',
                  onPressed: _currentPage < _totalPages
                      ? () {
                          _pdfViewerController.nextPage();
                        }
                      : null,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
