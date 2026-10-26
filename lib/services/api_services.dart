import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_config.dart';
import '../models/data_models.dart';
import 'preference_service.dart';

class ApiService {
  // Reusable persistent HTTP client with connection pooling & Keep-Alive
  static final http.Client _httpClient = http.Client();

  // In-memory instant cache (0ms response time)
  static final Map<String, dynamic> _memoryCache = {};
  static final Map<String, DateTime> _cacheTimestamps = {};
  static const Duration _cacheTtl = Duration(minutes: 15);

  static SharedPreferences? _prefs;
  static const String _prefKeyActiveHost = 'book_banko_cached_active_host';

  // Candidate host endpoints (Working Neon Vercel First, then Local Dev)
  static final List<String> _candidateBaseUrls = [
    if (AppConfig.customApiUrl.isNotEmpty) AppConfig.customApiUrl,
    ...AppConfig.defaultCandidateUrls,
  ];

  static String _activeBaseUrl = '';

  static String get baseUrl {
    if (_activeBaseUrl.isNotEmpty) return _activeBaseUrl;
    if (AppConfig.customApiUrl.isNotEmpty) return AppConfig.customApiUrl;
    return 'https://bookbanko-neon.vercel.app/api';
  }

  /// Initialize persistent cache and restore working backend URL
  static Future<void> init() async {
    try {
      _prefs ??= await SharedPreferences.getInstance();
      final savedHost = _prefs?.getString(_prefKeyActiveHost);
      if (savedHost != null && savedHost.isNotEmpty) {
        _activeBaseUrl = savedHost;
      }
    } catch (e) {
      debugPrint('[ApiService] init error: $e');
    }
  }

  /// Synchronously retrieve cached data from memory or disk
  static dynamic _getFromCache(String key) {
    if (_memoryCache.containsKey(key)) {
      return _memoryCache[key];
    }
    // Try restoring from disk cache
    try {
      final diskJson = _prefs?.getString('cache_$key');
      if (diskJson != null && diskJson.isNotEmpty) {
        final decoded = json.decode(diskJson);
        return decoded;
      }
    } catch (_) {}
    return null;
  }

  /// Save data to both memory cache and disk cache
  static void _saveToCache(String key, dynamic parsedData, dynamic rawJsonData) {
    _memoryCache[key] = parsedData;
    _cacheTimestamps[key] = DateTime.now();

    // Persist to disk in background without blocking
    try {
      if (rawJsonData != null && _prefs != null) {
        _prefs!.setString('cache_$key', json.encode(rawJsonData));
      }
    } catch (_) {}
  }

  /// Helper to make HTTP GET requests with fast fallback and persistent connection
  static Future<http.Response?> _getWithFallback(String endpoint) async {
    // 1. If active base URL is already established, try it first
    if (_activeBaseUrl.isNotEmpty) {
      try {
        final uri = Uri.parse('$_activeBaseUrl/$endpoint');
        final res = await _httpClient.get(uri).timeout(const Duration(milliseconds: 3500));
        if (res.statusCode == 200) return res;
      } catch (e) {
        debugPrint('[ApiService] Active URL timeout/fail ($_activeBaseUrl/$endpoint): $e');
      }
    }

    // 2. Try candidate URLs (live working Neon host first)
    final tested = <String>{};
    if (_activeBaseUrl.isNotEmpty) tested.add(_activeBaseUrl);

    for (final base in _candidateBaseUrls) {
      if (tested.contains(base)) continue;
      tested.add(base);

      try {
        final uri = Uri.parse('$base/$endpoint');
        final res = await _httpClient.get(uri).timeout(const Duration(milliseconds: 2500));
        if (res.statusCode == 200) {
          _activeBaseUrl = base;
          _prefs?.setString(_prefKeyActiveHost, base);
          debugPrint('[ApiService] Connected to host: $base');
          return res;
        }
      } catch (_) {
        // Try next candidate
      }
    }

    debugPrint('[ApiService] All endpoints failed for $endpoint');
    return null;
  }

  /// Helper to make HTTP POST requests with fast candidate fallback and persistent connection
  static Future<http.Response?> _postWithFallback(
    String endpoint,
    Map<String, dynamic> body, {
    Duration timeout = const Duration(seconds: 6),
  }) async {
    final bodyJson = json.encode(body);
    final headers = {'Content-Type': 'application/json'};

    // 1. If active base URL is already established, try it first
    if (_activeBaseUrl.isNotEmpty) {
      try {
        final uri = Uri.parse('$_activeBaseUrl/$endpoint');
        final res = await _httpClient.post(uri, headers: headers, body: bodyJson).timeout(timeout);
        if (res.statusCode == 200 || res.statusCode == 201) return res;
      } catch (e) {
        debugPrint('[ApiService] Active URL timeout/fail ($_activeBaseUrl/$endpoint): $e');
      }
    }

    // 2. Try candidate URLs (live working Neon host first)
    final tested = <String>{};
    if (_activeBaseUrl.isNotEmpty) tested.add(_activeBaseUrl);

    for (final base in _candidateBaseUrls) {
      if (tested.contains(base)) continue;
      tested.add(base);

      try {
        final uri = Uri.parse('$base/$endpoint');
        final res = await _httpClient.post(uri, headers: headers, body: bodyJson).timeout(const Duration(seconds: 5));
        if (res.statusCode == 200 || res.statusCode == 201) {
          _activeBaseUrl = base;
          _prefs?.setString(_prefKeyActiveHost, base);
          debugPrint('[ApiService] Connected to host for POST: $base');
          return res;
        }
      } catch (_) {
        // Try next candidate
      }
    }

    debugPrint('[ApiService] All endpoints failed for POST $endpoint');
    return null;
  }

  // ===========================================================================
  // 0. BOARDS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static List<BoardModel>? getCachedBoards() {
    final cached = _memoryCache['boards'];
    if (cached is List<BoardModel>) return cached;
    final raw = _getFromCache('boards');
    if (raw is List) {
      final list = raw.map((item) => BoardModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache['boards'] = list;
      return list;
    }
    return null;
  }

  static Future<List<BoardModel>> fetchBoards({bool forceRefresh = false}) async {
    final cached = getCachedBoards();
    final isStale = _cacheTimestamps['boards'] == null ||
        DateTime.now().difference(_cacheTimestamps['boards']!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final response = await _getWithFallback('get_boards.php');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => BoardModel.fromJson(item)).toList();
          _saveToCache('boards', parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching boards: $e');
    }

    return cached ?? BoardModel.defaultBoards;
  }

  // ===========================================================================
  // 0.05. MEDIUMS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static List<MediumModel>? getCachedMediums() {
    final cached = _memoryCache['mediums'];
    if (cached is List<MediumModel>) return cached;
    final raw = _getFromCache('mediums');
    if (raw is List) {
      final list = raw.map((item) => MediumModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache['mediums'] = list;
      return list;
    }
    return null;
  }

  static Future<List<MediumModel>> fetchMediums({bool forceRefresh = false}) async {
    final cached = getCachedMediums();
    final isStale = _cacheTimestamps['mediums'] == null ||
        DateTime.now().difference(_cacheTimestamps['mediums']!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final response = await _getWithFallback('get_mediums.php');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => MediumModel.fromJson(item)).toList();
          _saveToCache('mediums', parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching mediums: $e');
    }

    return cached ?? MediumModel.defaultMediums;
  }

  // ===========================================================================
  // 0.1. STANDARDS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _standardsCacheKey(String? bCode, int? bId, String? mCode, int? mId) {
    return 'standards_${bCode ?? ''}_${bId ?? 0}_${mCode ?? ''}_${mId ?? 0}';
  }

  static List<StandardModel>? getCachedStandards({
    String? boardCode,
    int? boardId,
    String? mediumCode,
    int? mediumId,
  }) {
    final key = _standardsCacheKey(boardCode, boardId, mediumCode, mediumId);
    final cached = _memoryCache[key];
    if (cached is List<StandardModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => StandardModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<StandardModel>> fetchStandards({
    String? boardCode,
    int? boardId,
    String? mediumCode,
    int? mediumId,
    bool forceRefresh = false,
  }) async {
    final key = _standardsCacheKey(boardCode, boardId, mediumCode, mediumId);
    final cached = getCachedStandards(
      boardCode: boardCode,
      boardId: boardId,
      mediumCode: mediumCode,
      mediumId: mediumId,
    );
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[];
      if (boardCode != null && boardCode.isNotEmpty) queryParams.add('board_code=$boardCode');
      if (boardId != null && boardId > 0) queryParams.add('board_id=$boardId');
      if (mediumCode != null && mediumCode.isNotEmpty) queryParams.add('medium_code=$mediumCode');
      if (mediumId != null && mediumId > 0) queryParams.add('medium_id=$mediumId');

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_standards.php$queryString');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => StandardModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching standards: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 0.2. STREAMS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _streamsCacheKey(String? bCode, int? bId, String? mCode, int? mId, int? stdNum, int? stdId) {
    return 'streams_${bCode ?? ''}_${bId ?? 0}_${mCode ?? ''}_${mId ?? 0}_${stdNum ?? 0}_${stdId ?? 0}';
  }

  static List<StreamModel>? getCachedStreams({
    String? boardCode,
    int? boardId,
    String? mediumCode,
    int? mediumId,
    int? standardNumber,
    int? standardId,
  }) {
    final key = _streamsCacheKey(boardCode, boardId, mediumCode, mediumId, standardNumber, standardId);
    final cached = _memoryCache[key];
    if (cached is List<StreamModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => StreamModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<StreamModel>> fetchStreams({
    String? boardCode,
    int? boardId,
    String? mediumCode,
    int? mediumId,
    int? standardNumber,
    int? standardId,
    bool forceRefresh = false,
  }) async {
    final key = _streamsCacheKey(boardCode, boardId, mediumCode, mediumId, standardNumber, standardId);
    final cached = getCachedStreams(
      boardCode: boardCode,
      boardId: boardId,
      mediumCode: mediumCode,
      mediumId: mediumId,
      standardNumber: standardNumber,
      standardId: standardId,
    );
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[];
      if (boardCode != null && boardCode.isNotEmpty) queryParams.add('board_code=$boardCode');
      if (boardId != null && boardId > 0) queryParams.add('board_id=$boardId');
      if (mediumCode != null && mediumCode.isNotEmpty) queryParams.add('medium_code=$mediumCode');
      if (mediumId != null && mediumId > 0) queryParams.add('medium_id=$mediumId');
      if (standardNumber != null && standardNumber > 0) queryParams.add('standard_number=$standardNumber');
      if (standardId != null && standardId > 0) queryParams.add('standard_id=$standardId');

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_streams.php$queryString');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => StreamModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching streams: $e');
    }

    return cached ?? StreamModel.defaultStreams;
  }

  // ===========================================================================
  // 1. SUBJECTS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _subjectsCacheKey(String bCode, int stdNum, String? mCode, String? sSlug) {
    return 'subjects_${bCode}_${stdNum}_${mCode ?? ''}_${sSlug ?? ''}';
  }

  static List<SubjectModel>? getCachedSubjects({
    required String boardCode,
    required int standardNumber,
    String? mediumCode,
    String? streamSlug,
  }) {
    final key = _subjectsCacheKey(boardCode, standardNumber, mediumCode, streamSlug);
    final cached = _memoryCache[key];
    if (cached is List<SubjectModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => SubjectModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<SubjectModel>> fetchSubjects({
    required String boardCode,
    required int standardNumber,
    String? mediumCode,
    String? streamSlug,
    bool forceRefresh = false,
  }) async {
    final key = _subjectsCacheKey(boardCode, standardNumber, mediumCode, streamSlug);
    final cached = getCachedSubjects(
      boardCode: boardCode,
      standardNumber: standardNumber,
      mediumCode: mediumCode,
      streamSlug: streamSlug,
    );
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    // Immediately return cached subjects if available
    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[
        'board_code=$boardCode',
        'standard_number=$standardNumber',
      ];
      if (mediumCode != null && mediumCode.isNotEmpty) queryParams.add('medium_code=$mediumCode');
      if (streamSlug != null && streamSlug.isNotEmpty) queryParams.add('stream_slug=$streamSlug');

      final response = await _getWithFallback('get_subjects.php?${queryParams.join('&')}');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => SubjectModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching subjects: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 2. CHAPTERS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _chaptersCacheKey(int subjectId, String? moduleSlug) {
    return 'chapters_${subjectId}_${moduleSlug ?? 'textbooks'}';
  }

  static List<ChapterModel>? getCachedChapters({
    required int subjectId,
    String? moduleSlug,
  }) {
    final key = _chaptersCacheKey(subjectId, moduleSlug);
    final cached = _memoryCache[key];
    if (cached is List<ChapterModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => ChapterModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<ChapterModel>> fetchChapters({
    required int subjectId,
    String? moduleSlug,
    bool forceRefresh = false,
  }) async {
    final key = _chaptersCacheKey(subjectId, moduleSlug);
    final cached = getCachedChapters(subjectId: subjectId, moduleSlug: moduleSlug);
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    // Immediately return cached chapters if available
    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final moduleParam = (moduleSlug != null && moduleSlug.isNotEmpty) ? '&module_slug=$moduleSlug' : '';
      final query = 'subject_id=$subjectId$moduleParam';
      final response = await _getWithFallback('get_chapters.php?$query');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => ChapterModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching chapters: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 3. EXTRA MATERIALS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _extraMatCacheKey(String cat, String? b, String? m, int? std, int? sub, String? q) {
    return 'extra_mat_${cat}_${b ?? ''}_${m ?? ''}_${std ?? 0}_${sub ?? 0}_${q ?? ''}';
  }

  static List<ExtraMaterialModel>? getCachedExtraMaterials({
    String categorySlug = 'extra_material',
    String? boardCode,
    String? mediumCode,
    int? standardNumber,
    int? subjectId,
    String? search,
  }) {
    final key = _extraMatCacheKey(categorySlug, boardCode, mediumCode, standardNumber, subjectId, search);
    final cached = _memoryCache[key];
    if (cached is List<ExtraMaterialModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => ExtraMaterialModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<ExtraMaterialModel>> fetchExtraMaterials({
    String categorySlug = 'extra_material',
    String? boardCode,
    String? mediumCode,
    int? standardNumber,
    int? subjectId,
    String? search,
    bool forceRefresh = false,
  }) async {
    final key = _extraMatCacheKey(categorySlug, boardCode, mediumCode, standardNumber, subjectId, search);
    final cached = getCachedExtraMaterials(
      categorySlug: categorySlug,
      boardCode: boardCode,
      mediumCode: mediumCode,
      standardNumber: standardNumber,
      subjectId: subjectId,
      search: search,
    );
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[];
      if (categorySlug.isNotEmpty) queryParams.add('category_slug=$categorySlug');
      if (boardCode != null && boardCode.isNotEmpty) queryParams.add('board_code=$boardCode');
      if (mediumCode != null && mediumCode.isNotEmpty) queryParams.add('medium_code=$mediumCode');
      if (standardNumber != null && standardNumber > 0) queryParams.add('standard_number=$standardNumber');
      if (subjectId != null && subjectId > 0) queryParams.add('subject_id=$subjectId');
      if (search != null && search.trim().isNotEmpty) queryParams.add('search=${Uri.encodeComponent(search.trim())}');

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_extra_materials.php$queryString');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => ExtraMaterialModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching extra materials: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 4. STUDY PRODUCTS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _studyProductsCacheKey(String? search, String? tag, bool? isFallbackAd) {
    return 'study_products_${search ?? ''}_${tag ?? ''}_${isFallbackAd ?? ''}';
  }

  static List<StudyProductModel>? getCachedStudyProducts({
    String? search,
    String? tag,
    bool? isFallbackAd,
  }) {
    final key = _studyProductsCacheKey(search, tag, isFallbackAd);
    final cached = _memoryCache[key];
    if (cached is List<StudyProductModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => StudyProductModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<StudyProductModel>> fetchStudyProducts({
    String? search,
    String? tag,
    bool? isFallbackAd,
    bool forceRefresh = false,
  }) async {
    final key = _studyProductsCacheKey(search, tag, isFallbackAd);
    final cached = getCachedStudyProducts(search: search, tag: tag, isFallbackAd: isFallbackAd);
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[];
      if (search != null && search.trim().isNotEmpty) queryParams.add('search=${Uri.encodeComponent(search.trim())}');
      if (tag != null && tag.trim().isNotEmpty) queryParams.add('tag=${Uri.encodeComponent(tag.trim())}');
      if (isFallbackAd != null) queryParams.add('is_fallback_ad=${isFallbackAd ? '1' : '0'}');

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_study_products.php$queryString');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => StudyProductModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching study products: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 5. COMPETITIVE EXAMS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static List<CompetitiveExamModel>? getCachedCompetitiveExams({String? search}) {
    final key = 'comp_exams_${search ?? ''}';
    final cached = _memoryCache[key];
    if (cached is List<CompetitiveExamModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => CompetitiveExamModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<CompetitiveExamModel>> fetchCompetitiveExams({String? search, bool forceRefresh = false}) async {
    final key = 'comp_exams_${search ?? ''}';
    final cached = getCachedCompetitiveExams(search: search);
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final searchParam = (search != null && search.trim().isNotEmpty) ? '?search=${Uri.encodeComponent(search.trim())}' : '';
      final response = await _getWithFallback('get_competitive_exams.php$searchParam');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => CompetitiveExamModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching competitive exams: $e');
    }

    return cached ?? CompetitiveExamModel.defaultExams;
  }

  // ===========================================================================
  // 6. COMPETITIVE EXAM MATERIALS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _compExamMatCacheKey(int? examId, String? examSlug, String? matType, String? search) {
    return 'comp_exam_mat_${examId ?? 0}_${examSlug ?? ''}_${matType ?? ''}_${search ?? ''}';
  }

  static List<CompetitiveExamMaterialModel>? getCachedCompetitiveExamMaterials({
    int? examId,
    String? examSlug,
    String? materialType,
    String? search,
  }) {
    final key = _compExamMatCacheKey(examId, examSlug, materialType, search);
    final cached = _memoryCache[key];
    if (cached is List<CompetitiveExamMaterialModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => CompetitiveExamMaterialModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<CompetitiveExamMaterialModel>> fetchCompetitiveExamMaterials({
    int? examId,
    String? examSlug,
    String? materialType,
    String? search,
    bool forceRefresh = false,
  }) async {
    final key = _compExamMatCacheKey(examId, examSlug, materialType, search);
    final cached = getCachedCompetitiveExamMaterials(
      examId: examId,
      examSlug: examSlug,
      materialType: materialType,
      search: search,
    );
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[];
      if (examId != null && examId > 0) queryParams.add('exam_id=$examId');
      if (examSlug != null && examSlug.isNotEmpty) queryParams.add('exam_slug=$examSlug');
      if (materialType != null && materialType.isNotEmpty) queryParams.add('material_type=${Uri.encodeComponent(materialType)}');
      if (search != null && search.trim().isNotEmpty) queryParams.add('search=${Uri.encodeComponent(search.trim())}');

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_competitive_exam_materials.php$queryString');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => CompetitiveExamMaterialModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching competitive exam materials: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 7. HIGHER EDUCATION MATERIALS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _higherEduCacheKey(String? courseName, String? search) {
    return 'higher_edu_mat_${courseName ?? ''}_${search ?? ''}';
  }

  static List<HigherEducationMaterialModel>? getCachedHigherEducationMaterials({
    String? courseName,
    String? search,
  }) {
    final key = _higherEduCacheKey(courseName, search);
    final cached = _memoryCache[key];
    if (cached is List<HigherEducationMaterialModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => HigherEducationMaterialModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<HigherEducationMaterialModel>> fetchHigherEducationMaterials({
    String? courseName,
    String? search,
    bool forceRefresh = false,
  }) async {
    final key = _higherEduCacheKey(courseName, search);
    final cached = getCachedHigherEducationMaterials(courseName: courseName, search: search);
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>[];
      if (courseName != null && courseName.isNotEmpty) queryParams.add('course_name=${Uri.encodeComponent(courseName)}');
      if (search != null && search.trim().isNotEmpty) queryParams.add('search=${Uri.encodeComponent(search.trim())}');

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_higher_education_materials.php$queryString');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          final parsed = list.map((item) => HigherEducationMaterialModel.fromJson(item)).toList();
          _saveToCache(key, parsed, list);
          return parsed;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching higher education materials: $e');
    }

    return cached ?? [];
  }

  // ===========================================================================
  // 8. AI COURSE MATERIALS (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static String _aiCourseCacheKey(int stdNum, String? search) {
    return 'ai_course_mat_${stdNum}_${search ?? ''}';
  }

  static List<AiCourseMaterialModel>? getCachedAiCourseMaterials({
    required int standardNumber,
    String? search,
  }) {
    final key = _aiCourseCacheKey(standardNumber, search);
    final cached = _memoryCache[key];
    if (cached is List<AiCourseMaterialModel>) return cached;
    final raw = _getFromCache(key);
    if (raw is List) {
      final list = raw.map((item) => AiCourseMaterialModel.fromJson(Map<String, dynamic>.from(item))).toList();
      _memoryCache[key] = list;
      return list;
    }
    return null;
  }

  static Future<List<AiCourseMaterialModel>> fetchAiCourseMaterials({
    required int standardNumber,
    String? search,
    bool forceRefresh = false,
  }) async {
    final key = _aiCourseCacheKey(standardNumber, search);
    final cached = getCachedAiCourseMaterials(standardNumber: standardNumber, search: search);
    final isStale = _cacheTimestamps[key] == null ||
        DateTime.now().difference(_cacheTimestamps[key]!) > _cacheTtl;

    if (!forceRefresh && cached != null && cached.isNotEmpty && !isStale) {
      return cached;
    }

    try {
      final queryParams = <String>['standard_number=$standardNumber'];
      if (search != null && search.trim().isNotEmpty) {
        queryParams.add('search=${Uri.encodeComponent(search.trim())}');
      }

      final queryString = '?${queryParams.join('&')}';
      final response = await _getWithFallback('get_ai_course_materials.php$queryString');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          if (list.isNotEmpty) {
            final parsed = list.map((item) => AiCourseMaterialModel.fromJson(item)).toList();
            _saveToCache(key, parsed, list);
            return parsed;
          }
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching AI course materials: $e');
    }

    return cached ?? _getDefaultAiMaterialsForStandard(standardNumber);
  }

  static List<AiCourseMaterialModel> _getDefaultAiMaterialsForStandard(int std) {
    switch (std) {
      case 6:
        return const [
          AiCourseMaterialModel(
            id: 101,
            standardNumber: 6,
            title: 'Introduction to Artificial Intelligence & Smart World',
            moduleName: 'Module 1 • What is AI?',
            description: 'Discover how machines learn, everyday examples of AI like voice assistants, recommendation systems, and computer vision.',
            pdfUrl: '',
            pageCount: 16,
            fileSizeMb: 2.10,
          ),
          AiCourseMaterialModel(
            id: 102,
            standardNumber: 6,
            title: 'Human vs Machine Intelligence & Fun Logic Games',
            moduleName: 'Module 2 • Smart Machines',
            description: 'Comparing human brain vs computer processing, pattern recognition, and decision puzzles.',
            pdfUrl: '',
            pageCount: 18,
            fileSizeMb: 2.30,
          ),
        ];
      case 7:
        return const [
          AiCourseMaterialModel(
            id: 201,
            standardNumber: 7,
            title: 'Computational Thinking & AI Problem Solving',
            moduleName: 'Module 1 • Algorithms & Logic',
            description: 'Understanding algorithms, block coding, decision trees, and designing simple conversational agents.',
            pdfUrl: '',
            pageCount: 20,
            fileSizeMb: 2.45,
          ),
          AiCourseMaterialModel(
            id: 202,
            standardNumber: 7,
            title: 'How Chatbots & Virtual Assistants Work',
            moduleName: 'Module 2 • Conversational AI',
            description: 'Introduction to text prompts, keyword matching, and building your first interactive chatbot.',
            pdfUrl: '',
            pageCount: 22,
            fileSizeMb: 2.60,
          ),
        ];
      case 8:
        return const [
          AiCourseMaterialModel(
            id: 301,
            standardNumber: 8,
            title: 'Data, Sensors & Computer Perception in AI',
            moduleName: 'Module 1 • Data & Sensors',
            description: 'How machines collect and clean data, image recognition fundamentals, and ethics in automated systems.',
            pdfUrl: '',
            pageCount: 24,
            fileSizeMb: 2.80,
          ),
          AiCourseMaterialModel(
            id: 302,
            standardNumber: 8,
            title: 'Smart Automation & Intro to Robotics',
            moduleName: 'Module 2 • Robotics & Automation',
            description: 'Robotic arms, autonomous vehicles, sensor feedback loops, and smart city applications.',
            pdfUrl: '',
            pageCount: 26,
            fileSizeMb: 3.10,
          ),
        ];
      case 9:
        return const [
          AiCourseMaterialModel(
            id: 401,
            standardNumber: 9,
            title: 'Python for Artificial Intelligence & Data Science',
            moduleName: 'Module 1 • Python Coding Essentials',
            description: 'Hands-on Python syntax, variables, lists, dictionaries, functions, and working with data for machine learning.',
            pdfUrl: '',
            pageCount: 32,
            fileSizeMb: 3.20,
          ),
          AiCourseMaterialModel(
            id: 402,
            standardNumber: 9,
            title: 'Computer Vision & Natural Language Processing (NLP)',
            moduleName: 'Module 2 • Core AI Domains',
            description: 'Techniques for feature extraction, optical character recognition, text sentiment analysis, and speech processing.',
            pdfUrl: '',
            pageCount: 28,
            fileSizeMb: 2.90,
          ),
        ];
      case 10:
        return const [
          AiCourseMaterialModel(
            id: 501,
            standardNumber: 10,
            title: 'Machine Learning Foundations & Supervised Learning',
            moduleName: 'Module 1 • Machine Learning',
            description: 'Supervised learning, classification vs regression, decision tree modeling, model training and evaluation metrics.',
            pdfUrl: '',
            pageCount: 36,
            fileSizeMb: 3.60,
          ),
          AiCourseMaterialModel(
            id: 502,
            standardNumber: 10,
            title: 'Board Exam AI Revision Guide & Ethics Project Cycle',
            moduleName: 'Module 2 • Board Curriculum & Ethics',
            description: 'Complete curriculum revision notes, AI project cycle (Problem Scoping, Data Acquisition, Exploration, Modeling, Evaluation), and AI ethics.',
            pdfUrl: '',
            pageCount: 44,
            fileSizeMb: 4.10,
          ),
        ];
      case 11:
        return const [
          AiCourseMaterialModel(
            id: 601,
            standardNumber: 11,
            title: 'Applied Neural Networks & Deep Learning Architectures',
            moduleName: 'Module 1 • Deep Learning',
            description: 'Artificial Neural Networks (ANN), activation functions, perceptrons, backpropagation, and training deep models.',
            pdfUrl: '',
            pageCount: 48,
            fileSizeMb: 4.50,
          ),
        ];
      case 12:
        return const [
          AiCourseMaterialModel(
            id: 701,
            standardNumber: 12,
            title: 'Generative AI, Large Language Models & AI Capstone',
            moduleName: 'Module 1 • Generative AI & LLMs',
            description: 'Transformers, Prompt Engineering, Large Language Models (LLMs), diffusion models, and developing end-to-end AI applications.',
            pdfUrl: '',
            pageCount: 52,
            fileSizeMb: 5.20,
          ),
        ];
      default:
        return [
          AiCourseMaterialModel(
            id: 801,
            standardNumber: std,
            title: 'Standard $std Artificial Intelligence & Coding Guide',
            moduleName: 'Module 1 • AI Essentials',
            description: 'Complete syllabus guide, theory, sample coding exercises, and practice questions for Standard $std.',
            pdfUrl: '',
            pageCount: 30,
            fileSizeMb: 3.00,
          ),
        ];
    }
  }

  // ===========================================================================
  // 9. APP CONFIG & NOTICES (Instant Cached + Stale-While-Revalidate)
  // ===========================================================================

  static Map<String, dynamic>? getCachedAppConfig() {
    final cached = _memoryCache['app_config'];
    if (cached is Map<String, dynamic>) return cached;
    final raw = _getFromCache('app_config');
    if (raw is Map) {
      final map = Map<String, dynamic>.from(raw);
      _memoryCache['app_config'] = map;
      return map;
    }
    return null;
  }

  static Future<Map<String, dynamic>?> getAppConfig({bool forceRefresh = false}) async {
    final cached = getCachedAppConfig();
    if (!forceRefresh && cached != null && cached.isNotEmpty) {
      return cached;
    }

    try {
      final response = await _getWithFallback('get_app_config.php');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final map = Map<String, dynamic>.from(data['data']);
          _saveToCache('app_config', map, map);
          return map;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] Error fetching app config: $e');
    }
    return cached;
  }

  // ===========================================================================
  // 10. APP WARM-UP & PRE-FETCH ENGINE (Instant Screen Responses)
  // ===========================================================================

  /// Pre-fetches common data in the background so clicks load in 0ms!
  static Future<void> prefetchAppData({
    String? boardCode,
    String? mediumCode,
    int? standardNumber,
    String? streamSlug,
  }) async {
    try {
      await init();

      final futures = <Future>[
        fetchBoards(),
        fetchMediums(),
      ];

      if (boardCode != null && mediumCode != null) {
        futures.add(fetchStandards(boardCode: boardCode, mediumCode: mediumCode));
      }

      if (boardCode != null && standardNumber != null && standardNumber > 0) {
        futures.add(fetchSubjects(
          boardCode: boardCode,
          standardNumber: standardNumber,
          mediumCode: mediumCode,
          streamSlug: streamSlug,
        ));
      }

      // Pre-warm competitive exams and extra materials concurrently
      futures.add(fetchCompetitiveExams());
      futures.add(fetchExtraMaterials());

      await Future.wait(futures);
      debugPrint('[ApiService] App pre-fetch completed. App is hot and primed for 0ms clicks!');
    } catch (e) {
      debugPrint('[ApiService] Background pre-fetch notice: $e');
    }
  }

  /// Save or register student profile on backend
  static Future<UserModel> saveUser({
    required String username,
    required String email,
    String? password,
    required String schoolName,
    required String city,
    required String district,
    required String state,
    bool acknowledged = true,
    String? board,
    String? medium,
    int? standard,
  }) async {
    final payload = {
      'username': username.trim(),
      'email': email.trim(),
      if (password != null && password.trim().isNotEmpty) 'password': password.trim(),
      'school_name': schoolName.trim(),
      'city': city.trim(),
      'district': district.trim(),
      'state': state.trim(),
      'acknowledged': acknowledged ? 1 : 0,
      if (board != null && board.isNotEmpty) 'board': board,
      if (medium != null && medium.isNotEmpty) 'medium': medium,
      if (standard != null && standard > 0) 'standard': standard,
    };

    try {
      final response = await _postWithFallback('save_user.php', payload);

      if (response != null && (response.statusCode == 200 || response.statusCode == 201)) {
        final decoded = json.decode(response.body);
        if (decoded['status'] == 'success' && decoded['data'] != null) {
          final user = UserModel.fromJson(Map<String, dynamic>.from(decoded['data']));
          await PreferenceService.saveUserData(user);
          return user;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] saveUser network notice: $e');
    }

    // Offline / fallback: save locally so student is never blocked
    final localUser = UserModel(
      id: DateTime.now().millisecondsSinceEpoch ~/ 1000,
      username: username.trim(),
      email: email.trim(),
      password: password?.trim(),
      schoolName: schoolName.trim(),
      city: city.trim(),
      district: district.trim(),
      state: state.trim(),
      acknowledged: acknowledged,
      board: board,
      medium: medium,
      standard: standard,
      createdAt: DateTime.now(),
    );
    await PreferenceService.saveUserData(localUser);
    return localUser;
  }

  /// Delete student user account and learning data permanently
  static Future<bool> deleteUser({int? userId, required String email}) async {
    final cleanEmail = email.trim().toLowerCase();
    debugPrint('[ApiService] deleteUser initiated for: $cleanEmail (ID: $userId)');

    final payload = <String, dynamic>{
      'email': cleanEmail,
      if (userId != null && userId > 0) 'user_id': userId,
    };

    try {
      final response = await _postWithFallback('delete_user.php', payload);

      if (response != null && (response.statusCode == 200 || response.statusCode == 201)) {
        final decoded = json.decode(response.body);
        if (decoded['status'] == 'success') {
          debugPrint('[ApiService] deleteUser confirmed by backend server: ${response.body}');
          await PreferenceService.clearAll();
          return true;
        }
      }
    } catch (e) {
      debugPrint('[ApiService] deleteUser notice: $e');
    }

    // Clear locally regardless to protect user privacy
    await PreferenceService.clearAll();
    return true;
  }

  /// Reset / update user password
  static Future<Map<String, dynamic>> resetPassword({
    required String email,
    required String newPassword,
  }) async {
    try {
      final workingBase = baseUrl;
      final uri = Uri.parse('$workingBase/reset_password.php');
      final response = await _httpClient.post(
        uri,
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'email': email.trim(),
          'password': newPassword.trim(),
        }),
      ).timeout(const Duration(seconds: 8));

      if (response.statusCode == 200 || response.statusCode == 400 || response.statusCode == 404) {
        final decoded = json.decode(response.body);
        return Map<String, dynamic>.from(decoded);
      }
    } catch (e) {
      debugPrint('[ApiService] resetPassword network error: $e');
      return {'status': 'error', 'message': 'Network error: $e'};
    }
    return {'status': 'error', 'message': 'Failed to reset password. Please check your connection.'};
  }

  /// Student sign in via email/username and 8-character password
  static Future<Map<String, dynamic>> loginStudent({
    required String login,
    required String password,
  }) async {
    try {
      final workingBase = baseUrl;
      final uri = Uri.parse('$workingBase/login_student.php');
      final response = await _httpClient.post(
        uri,
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'email': login.trim(),
          'password': password.trim(),
        }),
      ).timeout(const Duration(seconds: 8));

      final decoded = json.decode(response.body);
      if (response.statusCode == 200 && decoded['status'] == 'success') {
        final user = UserModel.fromJson(Map<String, dynamic>.from(decoded['data']));
        await PreferenceService.saveUserData(user);
        return {'status': 'success', 'user': user};
      }
      return {
        'status': 'error',
        'message': decoded['message'] ?? 'Invalid credentials. Please try again.',
      };
    } catch (e) {
      debugPrint('[ApiService] loginStudent notice: $e');
      return {'status': 'error', 'message': 'Network connection issue: $e'};
    }
  }

  /// Update student active standard in database
  static Future<Map<String, dynamic>> updateStudentStandard({
    required String email,
    required int standard,
    String? stream,
  }) async {
    try {
      final workingBase = baseUrl;
      final uri = Uri.parse('$workingBase/update_standard.php');
      final payload = <String, dynamic>{
        'email': email.trim(),
        'standard': standard,
      };
      if (stream != null && stream.isNotEmpty) {
        payload['stream'] = stream;
      }

      final response = await _httpClient.post(
        uri,
        headers: {'Content-Type': 'application/json'},
        body: json.encode(payload),
      ).timeout(const Duration(seconds: 8));

      final decoded = json.decode(response.body);
      return Map<String, dynamic>.from(decoded);
    } catch (e) {
      debugPrint('[ApiService] updateStudentStandard notice: $e');
      return {'status': 'offline', 'message': 'Saved locally'};
    }
  }
}

