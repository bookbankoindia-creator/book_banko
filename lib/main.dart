import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'models/app_state.dart';
import 'theme/app_theme.dart';
import 'screens/splash_screen.dart';
import 'screens/login_screen.dart';
import 'screens/select_board_screen.dart';
import 'screens/select_medium_screen.dart';
import 'screens/select_standard_screen.dart';
import 'screens/select_stream_screen.dart';
import 'screens/home_screen.dart';
import 'screens/standard_dashboard_screen.dart';
import 'screens/textbooks_screen.dart';
import 'screens/chapters_screen.dart';
import 'screens/pdf_reader_screen.dart';
import 'screens/extra_material_screen.dart';
import 'screens/study_material_screen.dart';
import 'screens/competitive_exams_screen.dart';
import 'screens/higher_education_screen.dart';
import 'screens/ai_course_standards_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/signup_email_screen.dart';
import 'screens/otp_verification_screen.dart';
import 'screens/register_details_screen.dart';
import 'screens/forgot_password_screen.dart';
import 'screens/edit_profile_screen.dart';
import 'services/ad_service.dart';
import 'services/api_services.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ApiService.init();
  // Initialize AdService asynchronously without blocking app launch
  AdService.instance.initialize();
  runApp(
    ChangeNotifierProvider(
      create: (_) => BookBankoAppState(),
      child: const BookBankoApp(),
    ),
  );
}

class BookBankoApp extends StatelessWidget {
  const BookBankoApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Book Banko',
      debugShowCheckedModeBanner: false,
      theme: BookBankoTheme.lightTheme,
      initialRoute: '/',
      routes: {
        '/': (context) => const SplashScreen(),
        '/login': (context) => const LoginScreen(),
        '/select_board': (context) => const SelectBoardScreen(),
        '/select_medium': (context) => const SelectMediumScreen(),
        '/select_standard': (context) => const SelectStandardScreen(),
        '/select_stream': (context) => const SelectStreamScreen(),
        '/home': (context) => const HomeScreen(),
        '/standard_dashboard': (context) => const StandardDashboardScreen(),
        '/textbooks': (context) => const TextbooksScreen(),
        '/chapters': (context) => const ChaptersScreen(),
        '/pdf_reader': (context) => const PdfReaderScreen(),
        '/extra_material': (context) => const ExtraMaterialScreen(),
        '/study_material': (context) => const StudyMaterialScreen(),
        '/competitive_exams': (context) => const CompetitiveExamsScreen(),
        '/higher_education': (context) => const HigherEducationScreen(),
        '/ai_course_standards': (context) => const AiCourseStandardsScreen(),
        '/profile': (context) => const ProfileScreen(),
        '/signup_email': (context) => const SignupEmailScreen(),
        '/otp_verification': (context) => const OtpVerificationScreen(),
        '/register_details': (context) => const RegisterDetailsScreen(),
        '/forgot_password': (context) => const ForgotPasswordScreen(),
        '/edit_profile': (context) => const EditProfileScreen(),
      },
    );
  }
}
