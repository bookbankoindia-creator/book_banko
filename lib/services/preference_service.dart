import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/data_models.dart';

class PreferenceService {
  static const String _keyBoard = 'book_banko_selected_board';
  static const String _keyMedium = 'book_banko_selected_medium';
  static const String _keyMediumName = 'book_banko_selected_medium_name';
  static const String _keyActiveStandard = 'book_banko_active_standard';
  static const String _keyMyStandards = 'book_banko_my_standards';
  static const String _keyStreamSlug = 'book_banko_stream_slug';
  static const String _keyStreamName = 'book_banko_stream_name';
  static const String _keyIsOnboarded = 'book_banko_is_onboarded';

  // User Profile / Login Keys
  static const String _keyIsLoggedIn = 'book_banko_is_logged_in';
  static const String _keyUserId = 'book_banko_user_id';
  static const String _keyUserName = 'book_banko_user_name';
  static const String _keyUserEmail = 'book_banko_user_email';
  static const String _keyUserSchool = 'book_banko_user_school';
  static const String _keyUserCity = 'book_banko_user_city';
  static const String _keyUserDistrict = 'book_banko_user_district';
  static const String _keyUserState = 'book_banko_user_state';
  static const String _keyUserAcknowledged = 'book_banko_user_acknowledged';

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

  /// Save registered student / user profile
  static Future<void> saveUserData(UserModel user) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_keyIsLoggedIn, true);
      await prefs.setInt(_keyUserId, user.id);
      await prefs.setString(_keyUserName, user.username);
      await prefs.setString(_keyUserEmail, user.email);
      await prefs.setString(_keyUserSchool, user.schoolName);
      await prefs.setString(_keyUserCity, user.city);
      await prefs.setString(_keyUserDistrict, user.district);
      await prefs.setString(_keyUserState, user.state);
      await prefs.setBool(_keyUserAcknowledged, user.acknowledged);
    } catch (e) {
      debugPrint('PreferenceService saveUserData error: $e');
    }
  }

  /// Get saved user profile
  static Future<UserModel?> getUserData() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final isLoggedIn = prefs.getBool(_keyIsLoggedIn) ?? false;
      final email = prefs.getString(_keyUserEmail);
      if (!isLoggedIn || email == null || email.isEmpty) {
        return null;
      }

      return UserModel(
        id: prefs.getInt(_keyUserId) ?? 0,
        username: prefs.getString(_keyUserName) ?? '',
        email: email,
        schoolName: prefs.getString(_keyUserSchool) ?? '',
        city: prefs.getString(_keyUserCity) ?? '',
        district: prefs.getString(_keyUserDistrict) ?? '',
        state: prefs.getString(_keyUserState) ?? '',
        acknowledged: prefs.getBool(_keyUserAcknowledged) ?? true,
        board: prefs.getString(_keyBoard),
        medium: prefs.getString(_keyMedium),
        standard: prefs.getInt(_keyActiveStandard),
      );
    } catch (e) {
      debugPrint('PreferenceService getUserData error: $e');
      return null;
    }
  }

  /// Check if user has completed initial registration
  static Future<bool> isLoggedIn() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final loggedIn = prefs.getBool(_keyIsLoggedIn) ?? false;
      final email = prefs.getString(_keyUserEmail);
      return loggedIn && email != null && email.isNotEmpty;
    } catch (e) {
      return false;
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
      await prefs.remove(_keyIsLoggedIn);
      await prefs.remove(_keyUserId);
      await prefs.remove(_keyUserName);
      await prefs.remove(_keyUserEmail);
      await prefs.remove(_keyUserSchool);
      await prefs.remove(_keyUserCity);
      await prefs.remove(_keyUserDistrict);
      await prefs.remove(_keyUserState);
      await prefs.remove(_keyUserAcknowledged);
    } catch (e) {
      debugPrint('PreferenceService clearAll error: $e');
    }
  }
}
