import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:book_banko/models/app_state.dart';
import 'package:book_banko/services/preference_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  test('First launch: No preferences stored -> isOnboarded is false', () async {
    final appState = BookBankoAppState();
    final hasPrefs = await appState.loadSavedPreferences();

    expect(hasPrefs, isFalse);
    expect(appState.isOnboarded, isFalse);
  });

  test('User selects board, medium, standard and completes onboarding', () async {
    final appState = BookBankoAppState();

    appState.selectBoard('CBSE');
    appState.selectMedium('english', mediumName: 'English Medium');
    appState.setSingleStandard(10);
    await appState.completeOnboarding();

    expect(appState.isOnboarded, isTrue);
    expect(appState.selectedBoard, equals('CBSE'));
    expect(appState.selectedMedium, equals('english'));
    expect(appState.activeStandardNumber, equals(10));

    // Verify stored directly in SharedPreferences
    final isStoredOnboarded = await PreferenceService.isOnboarded();
    expect(isStoredOnboarded, isTrue);

    final prefs = await PreferenceService.getAllPreferences();
    expect(prefs['board'], equals('CBSE'));
    expect(prefs['medium'], equals('english'));
    expect(prefs['activeStandard'], equals(10));
    expect(prefs['isOnboarded'], isTrue);
  });

  test('Second launch: App loads saved preferences and isOnboarded is true', () async {
    // Simulate pre-existing saved preferences from first launch
    SharedPreferences.setMockInitialValues({
      'book_banko_selected_board': 'GSEB',
      'book_banko_selected_medium': 'gujarati',
      'book_banko_selected_medium_name': 'Gujarati Medium',
      'book_banko_active_standard': 12,
      'book_banko_my_standards': ['12'],
      'book_banko_stream_slug': 'science',
      'book_banko_stream_name': 'Science',
      'book_banko_is_onboarded': true,
    });

    final newAppState = BookBankoAppState();
    final hasPrefs = await newAppState.loadSavedPreferences();

    expect(hasPrefs, isTrue);
    expect(newAppState.isOnboarded, isTrue);
    expect(newAppState.selectedBoard, equals('GSEB'));
    expect(newAppState.selectedMedium, equals('gujarati'));
    expect(newAppState.selectedMediumName, equals('Gujarati Medium'));
    expect(newAppState.activeStandardNumber, equals(12));
    expect(newAppState.myStandards, contains(12));
    expect(newAppState.selectedStreamSlug, equals('science'));
  });
}
