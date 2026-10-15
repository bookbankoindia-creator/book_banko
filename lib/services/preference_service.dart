import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class PreferenceService {
  static const String _keyBoard = 'book_banko_selected_board';
  static const String _keyMedium = 'book_banko_selected_medium';
  static const String _keyMediumName = 'book_banko_selected_medium_name';
  static const String _keyActiveStandard = 'book_banko_active_standard';
  static const String _keyMyStandards = 'book_banko_my_standards';
  static const String _keyStreamSlug = 'book_banko_stream_slug';
  static const String _keyStreamName = 'book_banko_stream_name';
  static const String _keyIsOnboarded = 'book_banko_is_onboarded';

  /// Save the selected board code (e.g. 'GSEB', 'CBSE')
  static Future<void> saveBoard(String board) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_keyBoard, board);
    } catch (e) {
      debugPrint('PreferenceService saveBoard error: $e');
    }
  }

  /// Save the selected medium code and display name (e.g. 'gujarati', 'Gujarati Medium')
  static Future<void> saveMedium(String code, String name) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_keyMedium, code);
      await prefs.setString(_keyMediumName, name);
    } catch (e) {
      debugPrint('PreferenceService saveMedium error: $e');
    }
  }

  /// Save the active standard number (e.g. 10)
  static Future<void> saveActiveStandard(int standardNumber) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt(_keyActiveStandard, standardNumber);
    } catch (e) {
      debugPrint('PreferenceService saveActiveStandard error: $e');
    }
  }

  /// Save the list of enrolled / shortcut standards
  static Future<void> saveMyStandards(List<int> standards) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setStringList(
        _keyMyStandards,
        standards.map((e) => e.toString()).toList(),
      );
    } catch (e) {
      debugPrint('PreferenceService saveMyStandards error: $e');
    }
  }

  /// Save the academic stream for 11th / 12th standards
  static Future<void> saveStream({String? slug, String? name}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (slug != null && slug.isNotEmpty) {
        await prefs.setString(_keyStreamSlug, slug);
      } else {
        await prefs.remove(_keyStreamSlug);
      }
      if (name != null && name.isNotEmpty) {
        await prefs.setString(_keyStreamName, name);
      } else {
        await prefs.remove(_keyStreamName);
      }
    } catch (e) {
      debugPrint('PreferenceService saveStream error: $e');
    }
  }

  /// Mark onboarding/initial selection as completed or reset
  static Future<void> setOnboarded(bool value) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_keyIsOnboarded, value);
    } catch (e) {
      debugPrint('PreferenceService setOnboarded error: $e');
    }
  }

  /// Returns true if user has already selected board, medium, and standard
  static Future<bool> isOnboarded() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final onboarded = prefs.getBool(_keyIsOnboarded) ?? false;
      final board = prefs.getString(_keyBoard);
      final medium = prefs.getString(_keyMedium);
      final standard = prefs.getInt(_keyActiveStandard);

      return onboarded &&
          board != null &&
          board.isNotEmpty &&
          medium != null &&
          medium.isNotEmpty &&
          standard != null &&
          standard > 0;
    } catch (e) {
      debugPrint('PreferenceService isOnboarded error: $e');
      return false;
    }
  }

  /// Fetch all saved preferences in one call
  static Future<Map<String, dynamic>> getAllPreferences() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final board = prefs.getString(_keyBoard);
      final medium = prefs.getString(_keyMedium);
      final mediumName = prefs.getString(_keyMediumName);
      final activeStd = prefs.getInt(_keyActiveStandard);
      final myStandardsRaw = prefs.getStringList(_keyMyStandards);
      final streamSlug = prefs.getString(_keyStreamSlug);
      final streamName = prefs.getString(_keyStreamName);
      final isOnboarded = prefs.getBool(_keyIsOnboarded) ?? false;

      List<int> myStandards = [];
      if (myStandardsRaw != null && myStandardsRaw.isNotEmpty) {
        myStandards = myStandardsRaw
            .map((e) => int.tryParse(e) ?? 0)
            .where((n) => n > 0)
            .toList();
      } else if (activeStd != null && activeStd > 0) {
        myStandards = [activeStd];
      }

      final hasValidPreferences = isOnboarded &&
          board != null &&
          board.isNotEmpty &&
          medium != null &&
          medium.isNotEmpty &&
          activeStd != null &&
          activeStd > 0;

      return {
        'board': board,
        'medium': medium,
        'mediumName': mediumName,
        'activeStandard': activeStd,
        'myStandards': myStandards,
        'streamSlug': streamSlug,
        'streamName': streamName,
        'isOnboarded': hasValidPreferences,
      };
    } catch (e) {
      debugPrint('PreferenceService getAllPreferences error: $e');
      return {
        'isOnboarded': false,
      };
    }
  }

  /// Clear all saved preferences
  static Future<void> clearAll() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_keyBoard);
      await prefs.remove(_keyMedium);
      await prefs.remove(_keyMediumName);
      await prefs.remove(_keyActiveStandard);
      await prefs.remove(_keyMyStandards);
      await prefs.remove(_keyStreamSlug);
      await prefs.remove(_keyStreamName);
      await prefs.remove(_keyIsOnboarded);
    } catch (e) {
      debugPrint('PreferenceService clearAll error: $e');
    }
  }
}
