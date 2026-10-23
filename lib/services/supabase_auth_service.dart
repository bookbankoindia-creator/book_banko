import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';

class SupabaseAuthResult {
  final bool success;
  final String message;
  final Map<String, dynamic>? data;

  const SupabaseAuthResult({
    required this.success,
    required this.message,
    this.data,
  });
}

class SupabaseAuthService {
  static final http.Client _client = http.Client();

  static Map<String, String> get _headers => {
        'apikey': AppConfig.supabaseAnonKey,
        'Content-Type': 'application/json',
      };

  /// Send 6-digit OTP code to user's email via Supabase Auth
  static Future<SupabaseAuthResult> sendEmailOtp(String email) async {
    final cleanEmail = email.trim();
    if (cleanEmail.isEmpty || !cleanEmail.contains('@') || !cleanEmail.contains('.')) {
      return const SupabaseAuthResult(
        success: false,
        message: 'Please enter a valid email address.',
      );
    }

    try {
      final url = Uri.parse('${AppConfig.supabaseUrl}/auth/v1/otp');
      final response = await _client
          .post(
            url,
            headers: _headers,
            body: jsonEncode({
              'email': cleanEmail,
              'create_user': true,
            }),
          )
          .timeout(const Duration(seconds: 12));

      if (response.statusCode == 200 || response.statusCode == 201) {
        return SupabaseAuthResult(
          success: true,
          message: '6-digit verification code sent to $cleanEmail. Please check your inbox or spam folder.',
        );
      } else {
        String errMsg = 'Failed to send verification code. Please check your email.';
        try {
          final decoded = jsonDecode(response.body);
          if (decoded['msg'] != null) {
            errMsg = decoded['msg'];
          } else if (decoded['error_description'] != null) {
            errMsg = decoded['error_description'];
          } else if (decoded['message'] != null) {
            errMsg = decoded['message'];
          }
        } catch (_) {}
        return SupabaseAuthResult(success: false, message: errMsg);
      }
    } catch (e) {
      debugPrint('[SupabaseAuthService] sendEmailOtp network error: $e');
      return SupabaseAuthResult(
        success: false,
        message: 'Network connection error while sending OTP: $e',
      );
    }
  }

  /// Verify 6-digit OTP token entered by user
  static Future<SupabaseAuthResult> verifyEmailOtp({
    required String email,
    required String token,
  }) async {
    final cleanEmail = email.trim();
    final cleanToken = token.trim();

    if (cleanToken.length < 6) {
      return const SupabaseAuthResult(
        success: false,
        message: 'Please enter the complete 6-digit verification code.',
      );
    }

    // Try standard verification types in Supabase (email, signup, magiclink)
    final typesToTry = ['email', 'signup', 'magiclink'];

    for (final type in typesToTry) {
      try {
        final url = Uri.parse('${AppConfig.supabaseUrl}/auth/v1/verify');
        final response = await _client
            .post(
              url,
              headers: _headers,
              body: jsonEncode({
                'type': type,
                'email': cleanEmail,
                'token': cleanToken,
              }),
            )
            .timeout(const Duration(seconds: 10));

        if (response.statusCode == 200 || response.statusCode == 201) {
          final decoded = jsonDecode(response.body);
          return SupabaseAuthResult(
            success: true,
            message: 'Email verified successfully!',
            data: decoded is Map<String, dynamic> ? decoded : null,
          );
        }
      } catch (e) {
        debugPrint('[SupabaseAuthService] verify attempt ($type) notice: $e');
      }
    }

    return const SupabaseAuthResult(
      success: false,
      message: 'Invalid or expired OTP code. Please enter the correct code or tap Resend Code.',
    );
  }
}
