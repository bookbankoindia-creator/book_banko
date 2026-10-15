import 'package:flutter_test/flutter_test.dart';
import 'package:book_banko/main.dart';
import 'package:book_banko/models/app_state.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('Book Banko app loads splash screen test', (WidgetTester tester) async {
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => BookBankoAppState(),
        child: const BookBankoApp(),
      ),
    );

    // Verify that Book Banko title and subtitle are rendered
    expect(find.text('Book Banko'), findsOneWidget);
    expect(find.text('Your Smart Study App'), findsOneWidget);
  });
}
