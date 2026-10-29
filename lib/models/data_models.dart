import 'dart:convert';
import 'package:flutter/material.dart';

enum StreamType {
  science,
  commerce,
  arts,
}

extension StreamTypeExtension on StreamType {
  String get displayName {
    switch (this) {
      case StreamType.science:
        return 'Science';
      case StreamType.commerce:
        return 'Commerce';
      case StreamType.arts:
        return 'Arts';
    }
  }
}

class StreamModel {
  final int id;
  final int? boardId;
  final String? boardCode;
  final int? mediumId;
  final String? mediumCode;
  final int? standardId;
  final int? standardNumber;
  final String slug;
  final String name;
  final String? description;
  final String? icon;

  const StreamModel({
    required this.id,
    this.boardId,
    this.boardCode,
    this.mediumId,
    this.mediumCode,
    this.standardId,
    this.standardNumber,
    required this.slug,
    required this.name,
    this.description,
    this.icon,
  });

  factory StreamModel.fromJson(Map<String, dynamic> json) {
    return StreamModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      boardId: json['board_id'] != null ? int.tryParse(json['board_id'].toString()) : null,
      boardCode: json['board_code']?.toString(),
      mediumId: json['medium_id'] != null ? int.tryParse(json['medium_id'].toString()) : null,
      mediumCode: json['medium_code']?.toString(),
      standardId: json['standard_id'] != null ? int.tryParse(json['standard_id'].toString()) : null,
      standardNumber: json['standard_number'] != null ? int.tryParse(json['standard_number'].toString()) : null,
      slug: json['slug']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      icon: json['icon']?.toString(),
    );
  }

  static const List<StreamModel> defaultStreams = [
    StreamModel(id: 1, slug: 'sci', name: 'Science'),
    StreamModel(id: 2, slug: 'comm', name: 'Commerce'),
    StreamModel(id: 3, slug: 'arts', name: 'Arts'),
  ];
}

class BoardModel {
  final int id;
  final String code;
  final String name;
  final String? description;
  final String? icon;

  const BoardModel({
    required this.id,
    required this.code,
    required this.name,
    this.description,
    this.icon,
  });

  factory BoardModel.fromJson(Map<String, dynamic> json) {
    return BoardModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      icon: json['icon']?.toString(),
    );
  }

  static const List<BoardModel> defaultBoards = [
    BoardModel(id: 1, code: 'GSEB', name: 'Gujarat Secondary and Higher Secondary Education Board'),
    BoardModel(id: 2, code: 'CBSE', name: 'Central Board of Secondary Education'),
  ];
}

class MediumModel {
  final int id;
  final String code;
  final String name;
  final String? description;
  final String? icon;

  const MediumModel({
    required this.id,
    required this.code,
    required this.name,
    this.description,
    this.icon,
  });

  factory MediumModel.fromJson(Map<String, dynamic> json) {
    return MediumModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      icon: json['icon']?.toString(),
    );
  }

  static const List<MediumModel> defaultMediums = [
    MediumModel(id: 1, code: 'gujarati', name: 'Gujarati Medium'),
    MediumModel(id: 2, code: 'english', name: 'English Medium'),
    MediumModel(id: 3, code: 'hindi', name: 'Hindi Medium'),
  ];
}

class StandardModel {
  final int id;
  final int? boardId;
  final String? boardCode;
  final int? mediumId;
  final String? mediumCode;
  final int number;
  final String name;
  final bool requiresStream;

  const StandardModel({
    this.id = 0,
    this.boardId,
    this.boardCode,
    this.mediumId,
    this.mediumCode,
    required this.number,
    required this.name,
    this.requiresStream = false,
  });

  factory StandardModel.fromJson(Map<String, dynamic> json) {
    return StandardModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      boardId: json['board_id'] != null ? int.tryParse(json['board_id'].toString()) : null,
      boardCode: json['board_code']?.toString(),
      mediumId: json['medium_id'] != null ? int.tryParse(json['medium_id'].toString()) : null,
      mediumCode: json['medium_code']?.toString(),
      number: int.tryParse(json['standard_number']?.toString() ?? '9') ?? 9,
      name: json['name'] as String? ?? 'Standard 9',
      requiresStream: json['requires_stream'] == true || json['requires_stream'] == 1 || json['requires_stream'] == '1',
    );
  }

  static const List<StandardModel> allStandards = [
    StandardModel(number: 6, name: 'Standard 6'),
    StandardModel(number: 7, name: 'Standard 7'),
    StandardModel(number: 8, name: 'Standard 8'),
    StandardModel(number: 9, name: 'Standard 9'),
    StandardModel(number: 10, name: 'Standard 10'),
    StandardModel(number: 11, name: 'Standard 11', requiresStream: true),
    StandardModel(number: 12, name: 'Standard 12', requiresStream: true),
  ];
}

class SubjectModel {
  final String id;
  final int databaseId;
  final String name;
  final IconData icon;
  final int chapterCount;

  const SubjectModel({
    required this.id,
    this.databaseId = 2,
    required this.name,
    required this.icon,
    this.chapterCount = 12,
  });

  factory SubjectModel.fromJson(Map<String, dynamic> json) {
    return SubjectModel(
      id: json['code']?.toString() ?? json['id']?.toString() ?? 'subj',
      databaseId: int.tryParse(json['id']?.toString() ?? '2') ?? 2,
      name: json['name']?.toString() ?? '',
      icon: _getIconForSubject(json['code']?.toString() ?? ''),
      chapterCount: int.tryParse(json['chapter_count']?.toString() ?? '0') ?? 0,
    );
  }

  static IconData _getIconForSubject(String code) {
    switch (code.toLowerCase()) {
      case 'math':
      case 'math_std':
      case 'math_basic':
      case 'math_11':
        return Icons.calculate_rounded;
      case 'sci':
      case 'sci_10':
      case 'chem_11':
        return Icons.science_rounded;
      case 'phy_11':
        return Icons.bolt_rounded;
      case 'bio_11':
        return Icons.biotech_rounded;
      case 'ss':
      case 'ss_10':
        return Icons.public_rounded;
      case 'eng':
      case 'eng_fl':
      case 'eng_11':
        return Icons.language_rounded;
      case 'guj':
      case 'guj_fl':
        return Icons.translate_rounded;
      case 'hin':
        return Icons.menu_book_rounded;
      case 'cs':
        return Icons.computer_rounded;
      default:
        return Icons.menu_book_rounded;
    }
  }

  static const List<SubjectModel> standard9Subjects = [
    SubjectModel(id: 'guj', databaseId: 1, name: 'Gujarati', icon: Icons.translate_rounded),
    SubjectModel(id: 'math', databaseId: 2, name: 'Mathematics', icon: Icons.calculate_rounded),
    SubjectModel(id: 'sci', databaseId: 3, name: 'Science & Technology', icon: Icons.science_rounded),
    SubjectModel(id: 'ss', databaseId: 4, name: 'Social Science', icon: Icons.public_rounded),
    SubjectModel(id: 'eng', databaseId: 5, name: 'English', icon: Icons.language_rounded),
    SubjectModel(id: 'hin', databaseId: 6, name: 'Hindi', icon: Icons.menu_book_rounded),
    SubjectModel(id: 'sans', databaseId: 7, name: 'Sanskrit', icon: Icons.auto_stories_rounded),
    SubjectModel(id: 'cs', databaseId: 8, name: 'Computer Studies', icon: Icons.computer_rounded),
  ];
}

class ChapterModel {
  final int id;
  final int number;
  final String title;
  final String pdfUrl;
  final int pageCount;
  final dynamic pageLinks;
  final String? moduleSlug;

  const ChapterModel({
    this.id = 0,
    required this.number,
    required this.title,
    required this.pdfUrl,
    this.pageCount = 34,
    this.pageLinks,
    this.moduleSlug,
  });

  factory ChapterModel.fromJson(Map<String, dynamic> json) {
    return ChapterModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      number: int.tryParse(json['chapter_number']?.toString() ?? '1') ?? 1,
      title: json['title']?.toString() ?? '',
      pdfUrl: json['full_pdf_url']?.toString() ?? json['pdf_file_path']?.toString() ?? '',
      pageCount: int.tryParse(json['page_count']?.toString() ?? '30') ?? 30,
      pageLinks: json['page_links'],
      moduleSlug: json['module_slug']?.toString() ?? 'textbooks',
    );
  }

  /// Returns the start page number configured in Admin Panel (e.g., 4, 5, 6... onwards)
  int getStartPage([int fallback = 4]) {
    if (pageLinks == null) return fallback;

    if (pageLinks is Map) {
      final map = pageLinks as Map;
      if (map['start_page'] != null) {
        final sp = int.tryParse(map['start_page'].toString());
        if (sp != null && sp >= 1) return sp;
      }
      final numKeys = map.keys
          .map((k) => int.tryParse(k.toString()))
          .whereType<int>()
          .toList();
      if (numKeys.isNotEmpty) {
        numKeys.sort();
        return numKeys.first;
      }
    } else if (pageLinks is String && pageLinks.toString().trim().isNotEmpty) {
      final str = pageLinks.toString().trim();
      if (str.startsWith('{') && str.endsWith('}')) {
        try {
          final decoded = jsonDecode(str);
          if (decoded is Map) {
            if (decoded['start_page'] != null) {
              final sp = int.tryParse(decoded['start_page'].toString());
              if (sp != null && sp >= 1) return sp;
            }
            final numKeys = decoded.keys
                .map((k) => int.tryParse(k.toString()))
                .whereType<int>()
                .toList();
            if (numKeys.isNotEmpty) {
              numKeys.sort();
              return numKeys.first;
            }
          }
        } catch (_) {}

        final match = RegExp(r'"start_page"\s*:\s*"?(\d+)"?').firstMatch(str);
        if (match != null && match.group(1) != null) {
          final sp = int.tryParse(match.group(1)!);
          if (sp != null && sp >= 1) return sp;
        }
      }
    }
    return fallback;
  }

  String? getLinkForPage(int pageNumber) {
    if (pageLinks == null) return null;
    final key = pageNumber.toString();

    if (pageLinks is Map) {
      final map = pageLinks as Map;
      if (map.containsKey(key) &&
          map[key] != null &&
          map[key].toString().trim().isNotEmpty) {
        return map[key].toString().trim();
      }
      if (map.containsKey(pageNumber) &&
          map[pageNumber] != null &&
          map[pageNumber].toString().trim().isNotEmpty) {
        return map[pageNumber].toString().trim();
      }

      final startVal = map['start_page'];
      if (startVal != null) {
        final startPage = int.tryParse(startVal.toString());
        if (startPage != null && pageNumber >= startPage) {
          if (map.containsKey('default') &&
              map['default'] != null &&
              map['default'].toString().trim().isNotEmpty) {
            return map['default'].toString().trim();
          }
        }
      }
    } else if (pageLinks is String && pageLinks.toString().trim().isNotEmpty) {
      final str = pageLinks.toString().trim();
      if (str.startsWith('{') && str.endsWith('}')) {
        try {
          final decoded = jsonDecode(str);
          if (decoded is Map) {
            if (decoded.containsKey(key) &&
                decoded[key] != null &&
                decoded[key].toString().trim().isNotEmpty) {
              return decoded[key].toString().trim();
            }
            if (decoded.containsKey(pageNumber) &&
                decoded[pageNumber] != null &&
                decoded[pageNumber].toString().trim().isNotEmpty) {
              return decoded[pageNumber].toString().trim();
            }

            final startVal = decoded['start_page'];
            if (startVal != null) {
              final startPage = int.tryParse(startVal.toString());
              if (startPage != null && pageNumber >= startPage) {
                if (decoded.containsKey('default') &&
                    decoded['default'] != null &&
                    decoded['default'].toString().trim().isNotEmpty) {
                  return decoded['default'].toString().trim();
                }
              }
            }
          }
        } catch (_) {}

        final keyPattern = '"$pageNumber"\\s*:\\s*"([^"]+)"';
        final match = RegExp(keyPattern).firstMatch(str);
        if (match != null && match.group(1) != null) {
          return match.group(1);
        }
      }
    }
    return null;
  }

  /// Returns custom container text customized from Admin Panel, or fallback default
  String getCustomLinkText([String? fallbackText]) {
    final defaultText = fallbackText ?? '👉 Click here for the best JEE & NEET Questions ↗';
    if (pageLinks == null) return defaultText;

    if (pageLinks is Map) {
      final map = pageLinks as Map;
      if (map['link_text'] != null && map['link_text'].toString().trim().isNotEmpty) {
        return map['link_text'].toString().trim();
      }
      if (map['button_text'] != null && map['button_text'].toString().trim().isNotEmpty) {
        return map['button_text'].toString().trim();
      }
    } else if (pageLinks is String && pageLinks.toString().trim().isNotEmpty) {
      final str = pageLinks.toString().trim();
      if (str.startsWith('{') && str.endsWith('}')) {
        try {
          final decoded = jsonDecode(str);
          if (decoded is Map) {
            if (decoded['link_text'] != null && decoded['link_text'].toString().trim().isNotEmpty) {
              return decoded['link_text'].toString().trim();
            }
            if (decoded['button_text'] != null && decoded['button_text'].toString().trim().isNotEmpty) {
              return decoded['button_text'].toString().trim();
            }
          }
        } catch (_) {}

        final textPattern = '"(?:link_text|button_text)"\\s*:\\s*"([^"]+)"';
        final match = RegExp(textPattern).firstMatch(str);
        if (match != null && match.group(1) != null) {
          return match.group(1)!;
        }
      }
    }
    return defaultText;
  }

  static const List<ChapterModel> mathChapters = [
    ChapterModel(number: 1, title: 'Number Systems', pdfUrl: 'ch1_number_systems.pdf', pageCount: 42),
    ChapterModel(number: 2, title: 'Polynomials', pdfUrl: 'ch2_polynomials.pdf', pageCount: 38),
    ChapterModel(number: 3, title: 'Coordinate Geometry', pdfUrl: 'ch3_coordinate_geometry.pdf', pageCount: 26),
    ChapterModel(number: 4, title: 'Linear Equations in Two Variables', pdfUrl: 'ch4_linear_equations.pdf', pageCount: 30),
    ChapterModel(number: 5, title: 'Introduction to Euclid\'s Geometry', pdfUrl: 'ch5_euclids_geometry.pdf', pageCount: 22),
    ChapterModel(number: 6, title: 'Lines and Angles', pdfUrl: 'ch6_lines_and_angles.pdf', pageCount: 35),
    ChapterModel(number: 7, title: 'Triangles', pdfUrl: 'ch7_triangles.pdf', pageCount: 40),
    ChapterModel(number: 8, title: 'Quadrilaterals', pdfUrl: 'ch8_quadrilaterals.pdf', pageCount: 32),
    ChapterModel(number: 9, title: 'Circles', pdfUrl: 'ch9_circles.pdf', pageCount: 28),
    ChapterModel(number: 10, title: 'Heron\'s Formula', pdfUrl: 'ch10_herons_formula.pdf', pageCount: 18),
    ChapterModel(number: 11, title: 'Surface Areas and Volumes', pdfUrl: 'ch11_surface_areas.pdf', pageCount: 45),
    ChapterModel(number: 12, title: 'Statistics', pdfUrl: 'ch12_statistics.pdf', pageCount: 36),
  ];
}

class DashboardOption {
  final String title;
  final String slug;
  final IconData icon;
  final String route;

  const DashboardOption({
    required this.title,
    required this.slug,
    required this.icon,
    required this.route,
  });

  static const List<DashboardOption> options = [
    DashboardOption(title: 'Textbooks', slug: 'textbooks', icon: Icons.menu_book_rounded, route: '/textbooks'),
    DashboardOption(title: 'Old PYQs', slug: 'pyqs', icon: Icons.history_edu_rounded, route: '/pyqs'),
    DashboardOption(title: 'Paper Sets', slug: 'paper_sets', icon: Icons.assignment_rounded, route: '/paper_sets'),
    DashboardOption(title: 'Blueprint', slug: 'blueprint', icon: Icons.architecture_rounded, route: '/blueprint'),
    DashboardOption(title: 'M.IMP', slug: 'mimp', icon: Icons.star_rounded, route: '/mimp'),
    DashboardOption(title: 'AI Course', slug: 'ai_course', icon: Icons.psychology_rounded, route: '/ai_course_materials'),
  ];
}

class ExtraMaterialModel {
  final int id;
  final String title;
  final String? description;
  final String categorySlug;
  final String? categoryName;
  final String? boardCode;
  final String? boardName;
  final String? mediumCode;
  final String? mediumName;
  final int? standardNumber;
  final String? standardName;
  final String? subjectName;
  final String? subjectCode;
  final String pdfUrl;
  final int pageCount;
  final double fileSizeMb;
  final bool isFree;
  final int displayOrder;

  const ExtraMaterialModel({
    required this.id,
    required this.title,
    this.description,
    this.categorySlug = 'extra_material',
    this.categoryName,
    this.boardCode,
    this.boardName,
    this.mediumCode,
    this.mediumName,
    this.standardNumber,
    this.standardName,
    this.subjectName,
    this.subjectCode,
    required this.pdfUrl,
    this.pageCount = 1,
    this.fileSizeMb = 0.0,
    this.isFree = true,
    this.displayOrder = 1,
  });

  factory ExtraMaterialModel.fromJson(Map<String, dynamic> json) {
    return ExtraMaterialModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      categorySlug: json['category_slug']?.toString() ?? 'extra_material',
      categoryName: json['category_name']?.toString(),
      boardCode: json['board_code']?.toString(),
      boardName: json['board_name']?.toString(),
      mediumCode: json['medium_code']?.toString(),
      mediumName: json['medium_name']?.toString(),
      standardNumber: json['standard_number'] != null ? int.tryParse(json['standard_number'].toString()) : null,
      standardName: json['standard_name']?.toString(),
      subjectName: json['subject_name']?.toString(),
      subjectCode: json['subject_code']?.toString(),
      pdfUrl: json['full_pdf_url']?.toString() ?? json['pdf_file_path']?.toString() ?? '',
      pageCount: int.tryParse(json['page_count']?.toString() ?? '1') ?? 1,
      fileSizeMb: double.tryParse(json['file_size_mb']?.toString() ?? '0.0') ?? 0.0,
      isFree: json['is_free'] == null || json['is_free'] == 1 || json['is_free'] == true || json['is_free'] == '1',
      displayOrder: int.tryParse(json['display_order']?.toString() ?? '1') ?? 1,
    );
  }

  ChapterModel toChapterModel() {
    return ChapterModel(
      number: displayOrder,
      title: title,
      pdfUrl: pdfUrl,
      pageCount: pageCount,
      moduleSlug: 'extra_material',
    );
  }
}

class StudyProductModel {
  final int id;
  final String title;
  final String? description;
  final String affiliateLink;
  final String? searchTags;
  final String? imageUrl;
  final int displayOrder;
  final bool isActive;
  final bool isFallbackAd;

  const StudyProductModel({
    required this.id,
    required this.title,
    this.description,
    required this.affiliateLink,
    this.searchTags,
    this.imageUrl,
    this.displayOrder = 99,
    this.isActive = true,
    this.isFallbackAd = false,
  });

  factory StudyProductModel.fromJson(Map<String, dynamic> json) {
    return StudyProductModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      affiliateLink: json['affiliate_link']?.toString() ?? '',
      searchTags: json['search_tags']?.toString(),
      imageUrl: json['full_image_url']?.toString() ?? json['image_external_url']?.toString() ?? json['image_path']?.toString(),
      displayOrder: int.tryParse(json['display_order']?.toString() ?? '99') ?? 99,
      isActive: json['status'] == null || json['status'] == 'active',
      isFallbackAd: json['is_fallback_ad'] == 1 || json['is_fallback_ad'] == true || json['is_fallback_ad'] == '1',
    );
  }

  List<String> get tagList {
    if (searchTags == null || searchTags!.trim().isEmpty) return [];
    return searchTags!.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
  }
}

class CompetitiveExamModel {
  final int id;
  final String title;
  final String slug;
  final String examCode;
  final String category;
  final String? description;
  final String? icon;
  final String colorHex;
  final int displayOrder;
  final int materialCount;

  const CompetitiveExamModel({
    required this.id,
    required this.title,
    required this.slug,
    required this.examCode,
    this.category = 'Engineering / Medical',
    this.description,
    this.icon,
    this.colorHex = '#0061A4',
    this.displayOrder = 1,
    this.materialCount = 0,
  });

  factory CompetitiveExamModel.fromJson(Map<String, dynamic> json) {
    return CompetitiveExamModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      examCode: json['exam_code']?.toString() ?? '',
      category: json['category']?.toString() ?? 'Engineering / Medical',
      description: json['description']?.toString(),
      icon: json['icon']?.toString(),
      colorHex: json['color_hex']?.toString() ?? '#0061A4',
      displayOrder: int.tryParse(json['display_order']?.toString() ?? '1') ?? 1,
      materialCount: int.tryParse(json['material_count']?.toString() ?? '0') ?? 0,
    );
  }

  static const List<CompetitiveExamModel> defaultExams = [
    CompetitiveExamModel(
      id: 1,
      title: 'JEE (Main & Advanced)',
      slug: 'jee',
      examCode: 'JEE',
      category: 'Engineering Entrance',
      description: 'Joint Entrance Examination for IITs, NITs & Engineering Colleges.',
      colorHex: '#0061A4',
    ),
    CompetitiveExamModel(
      id: 2,
      title: 'NEET (UG)',
      slug: 'neet',
      examCode: 'NEET',
      category: 'Medical Entrance',
      description: 'National Eligibility cum Entrance Test for MBBS & BDS.',
      colorHex: '#059669',
    ),
    CompetitiveExamModel(
      id: 3,
      title: 'GATE',
      slug: 'gate',
      examCode: 'GATE',
      category: 'Postgraduate & PSU',
      description: 'Graduate Aptitude Test in Engineering for M.Tech & PSU Recruitment.',
      colorHex: '#7C3AED',
    ),
    CompetitiveExamModel(
      id: 4,
      title: 'GUJCET',
      slug: 'gujcet',
      examCode: 'GUJCET',
      category: 'Gujarat State Entrance',
      description: 'Gujarat Common Entrance Test for Engineering & Pharmacy.',
      colorHex: '#D97706',
    ),
  ];
}

class CompetitiveExamMaterialModel {
  final int id;
  final int examId;
  final String? examTitle;
  final String? examCode;
  final String? examColor;
  final String title;
  final String? subjectName;
  final String materialType;
  final String? year;
  final String? description;
  final String pdfUrl;
  final int pageCount;
  final double fileSizeMb;
  final bool isFree;
  final int displayOrder;

  const CompetitiveExamMaterialModel({
    required this.id,
    required this.examId,
    this.examTitle,
    this.examCode,
    this.examColor,
    required this.title,
    this.subjectName,
    this.materialType = 'Question Paper',
    this.year,
    this.description,
    required this.pdfUrl,
    this.pageCount = 1,
    this.fileSizeMb = 0.0,
    this.isFree = true,
    this.displayOrder = 1,
  });

  factory CompetitiveExamMaterialModel.fromJson(Map<String, dynamic> json) {
    return CompetitiveExamMaterialModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      examId: int.tryParse(json['exam_id']?.toString() ?? '0') ?? 0,
      examTitle: json['exam_title']?.toString(),
      examCode: json['exam_code']?.toString(),
      examColor: json['exam_color']?.toString(),
      title: json['title']?.toString() ?? '',
      subjectName: json['subject_name']?.toString(),
      materialType: json['material_type']?.toString() ?? 'Question Paper',
      year: json['year']?.toString(),
      description: json['description']?.toString(),
      pdfUrl: json['full_pdf_url']?.toString() ?? json['pdf_file_path']?.toString() ?? '',
      pageCount: int.tryParse(json['page_count']?.toString() ?? '1') ?? 1,
      fileSizeMb: double.tryParse(json['file_size_mb']?.toString() ?? '0.0') ?? 0.0,
      isFree: json['is_free'] == null || json['is_free'] == 1 || json['is_free'] == true || json['is_free'] == '1',
      displayOrder: int.tryParse(json['display_order']?.toString() ?? '1') ?? 1,
    );
  }

  ChapterModel toChapterModel() {
    return ChapterModel(
      number: displayOrder,
      title: title,
      pdfUrl: pdfUrl,
      pageCount: pageCount,
      moduleSlug: 'competitive_exams',
    );
  }
}

class HigherEducationMaterialModel {
  final int id;
  final String title;
  final String? courseName;
  final String materialType;
  final String? description;
  final String pdfUrl;
  final int pageCount;
  final double fileSizeMb;
  final bool isFree;
  final int displayOrder;

  const HigherEducationMaterialModel({
    required this.id,
    required this.title,
    this.courseName,
    this.materialType = 'PDF Material',
    this.description,
    required this.pdfUrl,
    this.pageCount = 1,
    this.fileSizeMb = 0.0,
    this.isFree = true,
    this.displayOrder = 1,
  });

  factory HigherEducationMaterialModel.fromJson(Map<String, dynamic> json) {
    return HigherEducationMaterialModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      courseName: json['course_name']?.toString(),
      materialType: json['material_type']?.toString() ?? 'PDF Material',
      description: json['description']?.toString(),
      pdfUrl: json['full_pdf_url']?.toString() ?? json['pdf_file_path']?.toString() ?? '',
      pageCount: int.tryParse(json['page_count']?.toString() ?? '1') ?? 1,
      fileSizeMb: double.tryParse(json['file_size_mb']?.toString() ?? '0.0') ?? 0.0,
      isFree: json['is_free'] == null || json['is_free'] == 1 || json['is_free'] == true || json['is_free'] == '1',
      displayOrder: int.tryParse(json['display_order']?.toString() ?? '1') ?? 1,
    );
  }

  ChapterModel toChapterModel() {
    return ChapterModel(
      number: displayOrder,
      title: title,
      pdfUrl: pdfUrl,
      pageCount: pageCount,
      moduleSlug: 'higher_education',
    );
  }
}

class AiCourseMaterialModel {
  final int id;
  final int standardNumber;
  final String? standardName;
  final String title;
  final String? moduleName;
  final String materialType;
  final String? description;
  final String pdfUrl;
  final String? videoUrl;
  final int pageCount;
  final double fileSizeMb;
  final bool isFree;
  final int displayOrder;

  const AiCourseMaterialModel({
    required this.id,
    required this.standardNumber,
    this.standardName,
    required this.title,
    this.moduleName,
    this.materialType = 'AI Course Material',
    this.description,
    required this.pdfUrl,
    this.videoUrl,
    this.pageCount = 1,
    this.fileSizeMb = 0.0,
    this.isFree = true,
    this.displayOrder = 1,
  });

  factory AiCourseMaterialModel.fromJson(Map<String, dynamic> json) {
    final stdNum = int.tryParse(json['standard_number']?.toString() ?? '0') ?? 0;
    return AiCourseMaterialModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      standardNumber: stdNum,
      standardName: json['standard_name']?.toString() ?? 'Standard $stdNum',
      title: json['title']?.toString() ?? '',
      moduleName: json['module_name']?.toString(),
      materialType: json['material_type']?.toString() ?? 'AI Course Material',
      description: json['description']?.toString(),
      pdfUrl: json['full_pdf_url']?.toString() ?? json['pdf_file_path']?.toString() ?? '',
      videoUrl: json['video_url']?.toString(),
      pageCount: int.tryParse(json['page_count']?.toString() ?? '1') ?? 1,
      fileSizeMb: double.tryParse(json['file_size_mb']?.toString() ?? '0.0') ?? 0.0,
      isFree: json['is_free'] == null || json['is_free'] == 1 || json['is_free'] == true || json['is_free'] == '1',
      displayOrder: int.tryParse(json['display_order']?.toString() ?? '1') ?? 1,
    );
  }

  ChapterModel toChapterModel() {
    return ChapterModel(
      number: displayOrder,
      title: title,
      pdfUrl: pdfUrl,
      pageCount: pageCount,
      moduleSlug: 'ai_course',
    );
  }
}

/// Registered Student / User Model
class UserModel {
  final int id;
  final String username;
  final String email;
  final String? phone;
  final String? password;
  final String schoolName;
  final String city;
  final String district;
  final String state;
  final bool acknowledged;
  final String? board;
  final String? medium;
  final int? standard;
  final DateTime? createdAt;

  const UserModel({
    required this.id,
    required this.username,
    required this.email,
    this.phone,
    this.password,
    required this.schoolName,
    required this.city,
    required this.district,
    required this.state,
    this.acknowledged = true,
    this.board,
    this.medium,
    this.standard,
    this.createdAt,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      username: json['username']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phone: json['phone']?.toString(),
      password: json['password']?.toString(),
      schoolName: json['school_name']?.toString() ?? '',
      city: json['city']?.toString() ?? '',
      district: json['district']?.toString() ?? '',
      state: json['state']?.toString() ?? '',
      acknowledged: json['acknowledged'] == null ||
          json['acknowledged'] == 1 ||
          json['acknowledged'] == true ||
          json['acknowledged'] == '1',
      board: json['board']?.toString(),
      medium: json['medium']?.toString(),
      standard: json['standard'] != null
          ? int.tryParse(json['standard'].toString())
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'email': email,
      if (phone != null && phone!.isNotEmpty) 'phone': phone,
      if (password != null && password!.isNotEmpty) 'password': password,
      'school_name': schoolName,
      'city': city,
      'district': district,
      'state': state,
      'acknowledged': acknowledged ? 1 : 0,
      'board': board,
      'medium': medium,
      'standard': standard,
      'created_at': createdAt?.toIso8601String(),
    };
  }

  UserModel copyWith({
    int? id,
    String? username,
    String? email,
    String? phone,
    String? password,
    String? schoolName,
    String? city,
    String? district,
    String? state,
    bool? acknowledged,
    String? board,
    String? medium,
    int? standard,
    DateTime? createdAt,
  }) {
    return UserModel(
      id: id ?? this.id,
      username: username ?? this.username,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      password: password ?? this.password,
      schoolName: schoolName ?? this.schoolName,
      city: city ?? this.city,
      district: district ?? this.district,
      state: state ?? this.state,
      acknowledged: acknowledged ?? this.acknowledged,
      board: board ?? this.board,
      medium: medium ?? this.medium,
      standard: standard ?? this.standard,
      createdAt: createdAt ?? this.createdAt,
    );
  }
}
