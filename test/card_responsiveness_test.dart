import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:book_banko/widgets/book_banko_card.dart';
import 'package:book_banko/widgets/dashboard_card.dart';
import 'package:book_banko/models/app_state.dart';
import 'package:book_banko/screens/standard_dashboard_screen.dart';
import 'package:provider/provider.dart';

void main() {
  group('Card Responsiveness and Overflow Prevention Tests', () {
    final screenSizes = [
      const Size(320, 568), // Ultra-compact (iPhone SE 1st gen)
      const Size(360, 640), // Standard budget Android (Redmi / Samsung Galaxy A)
      const Size(392, 850), // Modern Android (Pixel / Samsung Galaxy S)
      const Size(412, 915), // Large Android
      const Size(430, 932), // iPhone 14/15 Pro Max
    ];

    for (final size in screenSizes) {
      testWidgets('BookBankoCard renders without overflow on ${size.width}x${size.height} screen', (tester) async {
        tester.view.physicalSize = size;
        tester.view.devicePixelRatio = 1.0;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);

        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: Padding(
                padding: const EdgeInsets.all(20.0),
                child: GridView.count(
                  crossAxisCount: 2,
                  crossAxisSpacing: 14,
                  mainAxisSpacing: 14,
                  childAspectRatio: 1.05,
                  children: [
                    BookBankoCard(
                      title: 'Competitive Exams',
                      icon: Icons.emoji_events_rounded,
                      onTap: () {},
                    ),
                    BookBankoCard(
                      title: 'Higher Education',
                      icon: Icons.account_balance_rounded,
                      onTap: () {},
                    ),
                    BookBankoCard(
                      title: 'Extra Material',
                      icon: Icons.bookmark_rounded,
                      onTap: () {},
                    ),
                    BookBankoCard(
                      title: 'Study Material',
                      icon: Icons.menu_book_rounded,
                      onTap: () {},
                    ),
                  ],
                ),
              ),
            ),
          ),
        );

        // Verify that no exceptions or overflow occurred
        expect(tester.takeException(), isNull);
        expect(find.text('Competitive Exams'), findsOneWidget);
        expect(find.text('Higher Education'), findsOneWidget);
      });

      testWidgets('DashboardCard renders without overflow on ${size.width}x${size.height} screen', (tester) async {
        tester.view.physicalSize = size;
        tester.view.devicePixelRatio = 1.0;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);

        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: Padding(
                padding: const EdgeInsets.all(20.0),
                child: GridView.count(
                  crossAxisCount: 2,
                  crossAxisSpacing: 16,
                  mainAxisSpacing: 16,
                  childAspectRatio: 1.05,
                  children: [
                    DashboardCard(
                      title: 'All Chapters & Master PDFs',
                      icon: Icons.menu_book_rounded,
                      onTap: () {},
                    ),
                    DashboardCard(
                      title: 'M.IMP Questions',
                      icon: Icons.star_rounded,
                      onTap: () {},
                    ),
                  ],
                ),
              ),
            ),
          ),
        );

        expect(tester.takeException(), isNull);
        expect(find.text('All Chapters & Master PDFs'), findsOneWidget);
        expect(find.text('M.IMP Questions'), findsOneWidget);
      });
    }

    testWidgets('StandardDashboardScreen renders all 6 options including AI Course beside M.IMP and opens Standard 6 AI Course', (tester) async {
      tester.view.physicalSize = const Size(392, 850);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final appState = BookBankoAppState();
      appState.setSingleStandard(6);
      appState.setActiveStandard(6);

      await tester.pumpWidget(
        ChangeNotifierProvider<BookBankoAppState>.value(
          value: appState,
          child: const MaterialApp(
            home: StandardDashboardScreen(),
          ),
        ),
      );

      // Verify all 6 dashboard options render properly
      expect(find.text('Textbooks'), findsOneWidget);
      expect(find.text('Old PYQs'), findsOneWidget);
      expect(find.text('Paper Sets'), findsOneWidget);
      expect(find.text('Blueprint'), findsOneWidget);
      expect(find.text('M.IMP'), findsOneWidget);
      expect(find.text('AI Course'), findsOneWidget);

      // Tap AI Course
      await tester.tap(find.text('AI Course'));
      await tester.pumpAndSettle();

      // Verify that Standard 6 AI Course screen is opened
      expect(find.textContaining('AI Course • Standard 6'), findsOneWidget);
    });
  });
}
