import 'package:flutter/foundation.dart';
import '../services/api_services.dart';
import '../services/preference_service.dart';
import 'data_models.dart';

class BookBankoAppState extends ChangeNotifier {
  String _selectedBoard = 'GSEB';
  String _selectedMedium = 'english';
  String _selectedMediumName = 'English Medium';
  String _selectedModuleSlug = 'textbooks';
  String _selectedModuleTitle = 'Textbooks';
  final List<int> _myStandards = [];
  int _activeStandardNumber = 0;
  StreamType? _selectedStream = StreamType.science;
  StreamModel? _selectedStreamModel;
  String? _selectedStreamSlug;
  String? _selectedStreamName;
  SubjectModel? _selectedSubject;
  ChapterModel? _selectedChapter;
  UserModel? _currentUser;

  bool _isOnboarded = false;
  bool _isPreferencesLoaded = false;

  UserModel? get currentUser => _currentUser;
  bool get isLoggedIn => _currentUser != null;

  String get selectedBoard => _selectedBoard;
  String get selectedMedium => _selectedMedium;
  String get selectedMediumName => _selectedMediumName;
  String get selectedModuleSlug => _selectedModuleSlug;
  String get selectedModuleTitle => _selectedModuleTitle;
  List<int> get myStandards => List.unmodifiable(_myStandards);
  int get activeStandardNumber => _activeStandardNumber;
  StreamType? get selectedStream => _selectedStream;
  StreamModel? get selectedStreamModel => _selectedStreamModel;
  String? get selectedStreamSlug =>
      _selectedStreamSlug ??
      (_selectedStreamModel?.slug ?? _selectedStream?.name.toLowerCase());
  String? get selectedStreamName =>
      _selectedStreamName ??
      (_selectedStreamModel?.name ?? _selectedStream?.displayName);
  SubjectModel? get selectedSubject => _selectedSubject;
  ChapterModel? get selectedChapter => _selectedChapter;
  bool get isOnboarded => _isOnboarded;
  bool get isPreferencesLoaded => _isPreferencesLoaded;

  /// Loads saved board, medium, and standard from SharedPreferences
  Future<bool> loadSavedPreferences() async {
    try {
      // Load user profile
      _currentUser = await PreferenceService.getUserData();

      final data = await PreferenceService.getAllPreferences();
      final hasCompleted = data['isOnboarded'] as bool? ?? false;

      if (hasCompleted) {
        if (data['board'] != null && (data['board'] as String).isNotEmpty) {
          _selectedBoard = data['board'] as String;
        }
        if (data['medium'] != null && (data['medium'] as String).isNotEmpty) {
          _selectedMedium = data['medium'] as String;
        }
        if (data['mediumName'] != null &&
            (data['mediumName'] as String).isNotEmpty) {
          _selectedMediumName = data['mediumName'] as String;
        } else {
          _selectedMediumName =
              '${_selectedMedium.substring(0, 1).toUpperCase()}${_selectedMedium.substring(1)} Medium';
        }

        if (data['activeStandard'] != null) {
          _activeStandardNumber = data['activeStandard'] as int;
        }

        final list = (data['myStandards'] as List<dynamic>?)?.cast<int>() ?? [];
        _myStandards.clear();
        if (_activeStandardNumber > 0) {
          _myStandards.add(_activeStandardNumber);
        } else if (list.isNotEmpty) {
          _activeStandardNumber = list.first;
          _myStandards.add(_activeStandardNumber);
        } else {
          _activeStandardNumber = 10;
          _myStandards.add(10);
        }

        if (data['streamSlug'] != null) {
          _selectedStreamSlug = data['streamSlug'] as String;
          final lower = _selectedStreamSlug!.toLowerCase();
          if (lower.contains('sci')) {
            _selectedStream = StreamType.science;
          } else if (lower.contains('comm')) {
            _selectedStream = StreamType.commerce;
          } else if (lower.contains('art')) {
            _selectedStream = StreamType.arts;
          }
        }

        if (data['streamName'] != null) {
          _selectedStreamName = data['streamName'] as String;
        }

        _isOnboarded = true;
        _isPreferencesLoaded = true;
        notifyListeners();
        return true;
      }
    } catch (e) {
      debugPrint('Error loading saved preferences into app state: $e');
    }

    _isOnboarded = false;
    _isPreferencesLoaded = true;
    notifyListeners();
    return false;
  }

  void setCurrentUser(UserModel? user) {
    _currentUser = user;
    if (user != null) {
      PreferenceService.saveUserData(user);
    }
    notifyListeners();
  }

  /// Log out user and clear stored preferences
  Future<void> logOut() async {
    _currentUser = null;
    _isOnboarded = false;
    await PreferenceService.clearAll();
    notifyListeners();
  }

  /// Permanently delete student account and clear local storage
  Future<bool> deleteAccount({String? email}) async {
    String? targetEmail = email?.trim() ?? _currentUser?.email;
    int? targetId = _currentUser?.id;

    if (targetEmail == null || targetEmail.isEmpty) {
      final saved = await PreferenceService.getUserData();
      targetEmail = saved?.email;
      targetId ??= saved?.id;
    }

    if (targetEmail != null && targetEmail.isNotEmpty) {
      await ApiService.deleteUser(
        userId: targetId,
        email: targetEmail,
      );
    }
    await logOut();
    return true;
  }

  void selectModule({required String slug, required String title}) {
    _selectedModuleSlug = slug;
    _selectedModuleTitle = title;
    notifyListeners();
  }

  void clearActiveStandard() {
    _myStandards.clear();
    _activeStandardNumber = 0;
    PreferenceService.saveActiveStandard(0);
    PreferenceService.saveMyStandards(_myStandards);
    notifyListeners();
  }

  void selectBoard(String board) {
    if (_selectedBoard != board) {
      _selectedBoard = board;
      clearActiveStandard();
      PreferenceService.saveBoard(board);
      if (_currentUser != null) {
        _currentUser = _currentUser!.copyWith(board: board);
        PreferenceService.saveUserData(_currentUser!);
      }
      notifyListeners();
    }
  }

  void selectMedium(String mediumCode, {String? mediumName}) {
    if (_selectedMedium != mediumCode) {
      _selectedMedium = mediumCode;
      if (mediumName != null && mediumName.isNotEmpty) {
        _selectedMediumName = mediumName;
      } else {
        _selectedMediumName =
            '${mediumCode.substring(0, 1).toUpperCase()}${mediumCode.substring(1)} Medium';
      }
      clearActiveStandard();
      PreferenceService.saveMedium(_selectedMedium, _selectedMediumName);
      if (_currentUser != null) {
        _currentUser = _currentUser!.copyWith(medium: mediumCode);
        PreferenceService.saveUserData(_currentUser!);
      }
      notifyListeners();
    }
  }

  /// Pick a single standard (e.g. from SelectStandardScreen onboarding)
  void setSingleStandard(int number) {
    _myStandards.clear();
    _myStandards.add(number);
    _activeStandardNumber = number;
    PreferenceService.saveActiveStandard(number);
    PreferenceService.saveMyStandards(_myStandards);
    if (_currentUser != null) {
      _currentUser = _currentUser!.copyWith(standard: number);
      PreferenceService.saveUserData(_currentUser!);
    }
    notifyListeners();
  }

  void setActiveStandard(int number) {
    _activeStandardNumber = number;
    _myStandards.clear();
    _myStandards.add(number);
    PreferenceService.saveMyStandards(_myStandards);
    PreferenceService.saveActiveStandard(number);
    if (_currentUser != null) {
      _currentUser = _currentUser!.copyWith(standard: number);
      PreferenceService.saveUserData(_currentUser!);
      if (_currentUser!.email.isNotEmpty) {
        ApiService.updateStudentStandard(
          email: _currentUser!.email,
          standard: number,
          stream: _selectedStreamName,
        );
      }
    }
    notifyListeners();
  }

  bool addStandard(int number) {
    setActiveStandard(number);
    return true;
  }

  void removeStandard(int number) {
    // Only single standard supported
    notifyListeners();
  }

  void toggleStandard(int number) {
    setActiveStandard(number);
  }

  void selectStreamModel(StreamModel stream) {
    _selectedStreamModel = stream;
    _selectedStreamSlug = stream.slug;
    _selectedStreamName = stream.name;
    final lower = stream.slug.toLowerCase();
    if (lower.contains('sci')) {
      _selectedStream = StreamType.science;
    } else if (lower.contains('comm')) {
      _selectedStream = StreamType.commerce;
    } else if (lower.contains('art')) {
      _selectedStream = StreamType.arts;
    }
    PreferenceService.saveStream(
      slug: _selectedStreamSlug,
      name: _selectedStreamName,
    );
    notifyListeners();
  }

  void selectStream(StreamType stream) {
    _selectedStream = stream;
    _selectedStreamSlug = stream.name.toLowerCase();
    _selectedStreamName = stream.displayName;
    PreferenceService.saveStream(
      slug: _selectedStreamSlug,
      name: _selectedStreamName,
    );
    notifyListeners();
  }

  void selectSubject(SubjectModel subject) {
    _selectedSubject = subject;
    notifyListeners();
  }

  void selectChapter(ChapterModel chapter) {
    _selectedChapter = chapter;
    notifyListeners();
  }

  /// Finalize and persist onboarding completion
  Future<void> completeOnboarding() async {
    _isOnboarded = true;
    await PreferenceService.saveBoard(_selectedBoard);
    await PreferenceService.saveMedium(_selectedMedium, _selectedMediumName);
    await PreferenceService.saveActiveStandard(_activeStandardNumber);
    await PreferenceService.saveMyStandards(_myStandards);
    if (_selectedStreamSlug != null || _selectedStreamName != null) {
      await PreferenceService.saveStream(
        slug: _selectedStreamSlug,
        name: _selectedStreamName,
      );
    }
    await PreferenceService.setOnboarded(true);
    notifyListeners();
  }

  /// Reset all stored preferences
  Future<void> resetPreferences() async {
    await PreferenceService.clearAll();
    _isOnboarded = false;
    _selectedBoard = 'GSEB';
    _selectedMedium = 'english';
    _selectedMediumName = 'English Medium';
    _activeStandardNumber = 0;
    _myStandards.clear();
    _selectedStreamSlug = null;
    _selectedStreamName = null;
    notifyListeners();
  }
}
