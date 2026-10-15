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

  // 8. Fetch AI Course Materials & PDFs by Standard dynamically
  static Future<List<AiCourseMaterialModel>> fetchAiCourseMaterials({
    required int standardNumber,
    String? search,
  }) async {
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
            return list.map((item) => AiCourseMaterialModel.fromJson(item)).toList();
          }
        }
      }
    } catch (e) {
      debugPrint('Error fetching AI course materials from API: $e');
    }

    return _getDefaultAiMaterialsForStandard(standardNumber);
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

  // 9. Fetch App Config & AdMob settings dynamically
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


