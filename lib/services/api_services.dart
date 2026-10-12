import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../models/data_models.dart';

class ApiService {
  // Candidate host endpoints to support Physical Android Phone over Wi-Fi, Android Emulator, Web & Desktop
  static final List<String> _candidateBaseUrls = [
    if (AppConfig.customApiUrl.isNotEmpty) AppConfig.customApiUrl,
    ...AppConfig.defaultCandidateUrls,
  ];

  static String _activeBaseUrl = '';

  static String get baseUrl {
    if (AppConfig.customApiUrl.isNotEmpty) return AppConfig.customApiUrl;
    if (_activeBaseUrl.isNotEmpty) return _activeBaseUrl;
    return 'https://bookbanko.vercel.app/api';
  }

  // Helper to make HTTP GET requests across candidate URLs with fast fallback
  static Future<http.Response?> _getWithFallback(String endpoint) async {
    // If active base URL is already established, try it first
    if (_activeBaseUrl.isNotEmpty) {
      try {
        final uri = Uri.parse('$_activeBaseUrl/$endpoint');
        final res = await http.get(uri).timeout(const Duration(milliseconds: 3000));
        if (res.statusCode == 200) return res;
      } catch (e) {
        debugPrint('Active URL failed ($_activeBaseUrl/$endpoint): $e');
      }
    }

    // Try all candidate URLs
    for (final base in _candidateBaseUrls) {
      try {
        final uri = Uri.parse('$base/$endpoint');
        final res = await http.get(uri).timeout(const Duration(milliseconds: 3000));
        if (res.statusCode == 200) {
          _activeBaseUrl = base; // Cache working host URL
          debugPrint('Connected successfully to backend host: $base');
          return res;
        }
      } catch (e) {
        // Try next candidate
      }
    }
    debugPrint('All candidate endpoints failed for $endpoint');
    return null;
  }

  // 0. Fetch Boards dynamically
  static Future<List<BoardModel>> fetchBoards() async {
    try {
      final response = await _getWithFallback('get_boards.php');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          return list.map((item) => BoardModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching boards from API: $e');
    }
    return BoardModel.defaultBoards; // Fallback only on network error
  }

  // 0.05. Fetch Mediums dynamically
  static Future<List<MediumModel>> fetchMediums() async {
    try {
      final response = await _getWithFallback('get_mediums.php');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          return list.map((item) => MediumModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching mediums from API: $e');
    }
    return MediumModel.defaultMediums;
  }

  // 0.1. Fetch Standards dynamically with Board & Medium filter
  static Future<List<StandardModel>> fetchStandards({
    String? boardCode,
    int? boardId,
    String? mediumCode,
    int? mediumId,
  }) async {
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
          return list.map((item) => StandardModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching standards from API: $e');
    }
    return []; // Return only standards from Admin Panel API
  }

  // 0.2. Fetch Streams dynamically with Board, Medium & Standard filter
  static Future<List<StreamModel>> fetchStreams({
    String? boardCode,
    int? boardId,
    String? mediumCode,
    int? mediumId,
    int? standardNumber,
    int? standardId,
  }) async {
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
          return list.map((item) => StreamModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching streams from API: $e');
    }
    return [];
  }

  // 1. Fetch Subjects dynamically for a Board, Medium & Standard
  static Future<List<SubjectModel>> fetchSubjects({
    required String boardCode,
    required int standardNumber,
    String? mediumCode,
    String? streamSlug,
  }) async {
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
          return list.map((item) => SubjectModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching subjects from API: $e');
    }
    return []; // Return empty list to prevent hardcoded subjects from showing
  }

  // 2. Fetch Chapters & PDFs for a Subject
  static Future<List<ChapterModel>> fetchChapters({
    required int subjectId,
    String? moduleSlug,
  }) async {
    try {
      final moduleParam = (moduleSlug != null && moduleSlug.isNotEmpty) ? '&module_slug=$moduleSlug' : '';
      final query = 'subject_id=$subjectId$moduleParam';
      final response = await _getWithFallback('get_chapters.php?$query');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          return list.map((item) => ChapterModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching chapters from API: $e');
    }
    return [];
  }

  // 3. Fetch Extra Materials & PDFs dynamically
  static Future<List<ExtraMaterialModel>> fetchExtraMaterials({
    String categorySlug = 'extra_material',
    String? boardCode,
    String? mediumCode,
    int? standardNumber,
    int? subjectId,
    String? search,
  }) async {
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
          return list.map((item) => ExtraMaterialModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching extra materials from API: $e');
    }
    return [];
  }

  // 4. Fetch Study Material Products & Stationery (Affiliate Links)
  static Future<List<StudyProductModel>> fetchStudyProducts({
    String? search,
    String? tag,
    bool? isFallbackAd,
  }) async {
    try {
      final queryParams = <String>[];
      if (search != null && search.trim().isNotEmpty) {
        queryParams.add('search=${Uri.encodeComponent(search.trim())}');
      }
      if (tag != null && tag.trim().isNotEmpty) {
        queryParams.add('tag=${Uri.encodeComponent(tag.trim())}');
      }
      if (isFallbackAd != null) {
        queryParams.add('is_fallback_ad=${isFallbackAd ? '1' : '0'}');
      }

      final queryString = queryParams.isNotEmpty ? '?${queryParams.join('&')}' : '';
      final response = await _getWithFallback('get_study_products.php$queryString');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          return list.map((item) => StudyProductModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching study products from API: $e');
    }
    return [];
  }

  // 5. Fetch Competitive Exams (JEE, NEET, GATE, etc.) dynamically
  static Future<List<CompetitiveExamModel>> fetchCompetitiveExams({String? search}) async {
    try {
      final searchParam = (search != null && search.trim().isNotEmpty) ? '?search=${Uri.encodeComponent(search.trim())}' : '';
      final response = await _getWithFallback('get_competitive_exams.php$searchParam');

      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          final List list = data['data'];
          return list.map((item) => CompetitiveExamModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching competitive exams from API: $e');
    }
    return CompetitiveExamModel.defaultExams; // Safe fallback
  }

  // 6. Fetch Competitive Exam Materials & PDFs (JEE, NEET, GATE Question Papers, Formulas, Notes)
  static Future<List<CompetitiveExamMaterialModel>> fetchCompetitiveExamMaterials({
    int? examId,
    String? examSlug,
    String? materialType,
    String? search,
  }) async {
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
          return list.map((item) => CompetitiveExamMaterialModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching competitive exam materials from API: $e');
    }
    return [];
  }

  // 7. Fetch Higher Education Materials & PDFs dynamically
  static Future<List<HigherEducationMaterialModel>> fetchHigherEducationMaterials({
    String? courseName,
    String? search,
  }) async {
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
          return list.map((item) => HigherEducationMaterialModel.fromJson(item)).toList();
        }
      }
    } catch (e) {
      debugPrint('Error fetching higher education materials from API: $e');
    }
    return [];
  }

  // 8. Fetch App Config & AdMob settings dynamically
  static Future<Map<String, dynamic>?> getAppConfig() async {
    try {
      final response = await _getWithFallback('get_app_config.php');
      if (response != null && response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success' && data['data'] != null) {
          return Map<String, dynamic>.from(data['data']);
        }
      }
    } catch (e) {
      debugPrint('Error fetching app config from API: $e');
    }
    return null;
  }
}


