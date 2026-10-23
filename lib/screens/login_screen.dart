import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../services/api_services.dart';
import '../services/supabase_auth_service.dart';
import '../theme/app_theme.dart';
import '../widgets/logo_widget.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  // Tab Controller: 0 = Sign In, 1 = Sign Up / Register
  int _selectedTab = 0;
  bool _isEditMode = false;

  final _signInFormKey = GlobalKey<FormState>();
  final _signUpFormKey = GlobalKey<FormState>();

  // Sign In Controllers
  final _signInLoginController = TextEditingController();
  final _signInPasswordController = TextEditingController();
  bool _signInObscurePassword = true;

  // Sign Up Controllers
  final _usernameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _schoolController = TextEditingController();
  final _cityController = TextEditingController();
  final _districtController = TextEditingController();
  final _stateController = TextEditingController(text: 'Gujarat');

  // Supabase OTP States for Sign Up
  final _otpController = TextEditingController();
  bool _isSendingOtp = false;
  bool _isVerifyingOtp = false;
  bool _otpSent = false;
  bool _isEmailVerified = false;
  int _otpResendTimerSeconds = 0;
  Timer? _resendTimer;

  bool _obscurePassword = true;
  bool _isAcknowledged = false;
  bool _isLoading = false;
  String? _errorMessage;

  static const List<String> _indianStates = [
    'Gujarat',
    'Maharashtra',
    'Rajasthan',
    'Madhya Pradesh',
    'Delhi',
    'Uttar Pradesh',
    'Punjab',
    'Haryana',
    'Karnataka',
    'Tamil Nadu',
    'West Bengal',
    'Bihar',
    'Andhra Pradesh',
    'Telangana',
    'Kerala',
    'Odisha',
    'Assam',
    'Chhattisgarh',
    'Jharkhand',
    'Uttarakhand',
    'Himachal Pradesh',
    'Goa',
    'Other State',
  ];

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final appState = Provider.of<BookBankoAppState>(context, listen: false);
      final user = appState.currentUser;
      if (user != null) {
        setState(() {
          _isEditMode = true;
          _isEmailVerified = true;
        });
        _usernameController.text = user.username;
        _emailController.text = user.email;
        if (user.password != null) {
          _passwordController.text = user.password!;
        }
        _schoolController.text = user.schoolName;
        _cityController.text = user.city;
        _districtController.text = user.district;
        if (user.state.isNotEmpty) _stateController.text = user.state;
        _isAcknowledged = user.acknowledged;
      }
    });
  }

  @override
  void dispose() {
    _resendTimer?.cancel();
    _signInLoginController.dispose();
    _signInPasswordController.dispose();
    _usernameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _otpController.dispose();
    _schoolController.dispose();
    _cityController.dispose();
    _districtController.dispose();
    _stateController.dispose();
    super.dispose();
  }

  void _startResendTimer() {
    _otpResendTimerSeconds = 30;
    _resendTimer?.cancel();
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_otpResendTimerSeconds > 0) {
        setState(() {
          _otpResendTimerSeconds--;
        });
      } else {
        timer.cancel();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // SUPABASE EMAIL OTP ACTIONS
  // ---------------------------------------------------------------------------

  Future<void> _sendSignupOtp() async {
    final email = _emailController.text.trim();
    if (email.isEmpty || !email.contains('@') || !email.contains('.')) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Please enter a valid email address first.',
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600),
          ),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    setState(() {
      _isSendingOtp = true;
      _errorMessage = null;
    });

    final result = await SupabaseAuthService.sendEmailOtp(email);

    if (!mounted) return;

    setState(() {
      _isSendingOtp = false;
    });

    if (result.success) {
      setState(() {
        _otpSent = true;
        if (result.isRateLimited && result.backupOtp != null) {
          _otpController.text = result.backupOtp!;
          Clipboard.setData(ClipboardData(text: result.backupOtp!));
        }
      });
      _startResendTimer();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              Icon(
                result.isRateLimited ? Icons.info_rounded : Icons.mark_email_read_rounded,
                color: Colors.white,
                size: 20,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  result.message,
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13),
                ),
              ),
            ],
          ),
          backgroundColor: result.isRateLimited ? const Color(0xFF2563EB) : const Color(0xFF0D9488),
          behavior: SnackBarBehavior.floating,
          duration: Duration(seconds: result.isRateLimited ? 6 : 4),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.message,
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600),
          ),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _verifySignupOtp() async {
    final email = _emailController.text.trim();
    final otp = _otpController.text.trim();

    if (otp.length < 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Please enter the full 6-digit OTP code sent to your email.',
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600),
          ),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    setState(() {
      _isVerifyingOtp = true;
      _errorMessage = null;
    });

    final result = await SupabaseAuthService.verifyEmailOtp(email: email, token: otp);

    if (!mounted) return;

    setState(() {
      _isVerifyingOtp = false;
    });

    if (result.success) {
      setState(() {
        _isEmailVerified = true;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.verified_rounded, color: Colors.white, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Email verified successfully! Please complete your academic profile below.',
                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13),
                ),
              ),
            ],
          ),
          backgroundColor: const Color(0xFF10B981),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.message,
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600),
          ),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  // ---------------------------------------------------------------------------
  // SIGN IN SUBMISSION
  // ---------------------------------------------------------------------------

  Future<void> _submitSignIn() async {
    if (!_signInFormKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final login = _signInLoginController.text.trim();
    final password = _signInPasswordController.text.trim();
    final appState = Provider.of<BookBankoAppState>(context, listen: false);

    try {
      final res = await ApiService.loginStudent(login: login, password: password);

      if (!mounted) return;

      if (res['status'] == 'success' && res['user'] != null) {
        final user = res['user'];
        appState.setCurrentUser(user);

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Welcome back, ${user.username}!',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13),
                  ),
                ),
              ],
            ),
            backgroundColor: const Color(0xFF10B981),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );

        if (appState.isOnboarded) {
          Navigator.pushReplacementNamed(context, '/home');
        } else {
          Navigator.pushReplacementNamed(context, '/select_board');
        }
      } else {
        setState(() {
          _errorMessage = res['message'] ?? 'Invalid email or password.';
          _isLoading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Login error: $e';
        _isLoading = false;
      });
    }
  }

  // ---------------------------------------------------------------------------
  // SIGN UP / EDIT PROFILE SUBMISSION
  // ---------------------------------------------------------------------------

  Future<void> _submitSignUp() async {
    setState(() {
      _errorMessage = null;
    });

    if (!_signUpFormKey.currentState!.validate()) {
      return;
    }

    if (!_isEditMode && !_isEmailVerified) {
      setState(() {
        _errorMessage = 'Please verify your email address using the OTP code above before continuing.';
      });
      return;
    }

    if (!_isAcknowledged) {
      setState(() {
        _errorMessage = 'Please confirm the acknowledgement checkbox to proceed.';
      });
      return;
    }

    setState(() {
      _isLoading = true;
    });

    final appState = Provider.of<BookBankoAppState>(context, listen: false);

    try {
      final user = await ApiService.saveUser(
        username: _usernameController.text.trim(),
        email: _emailController.text.trim(),
        password: _passwordController.text.trim(),
        schoolName: _schoolController.text.trim(),
        city: _cityController.text.trim(),
        district: _districtController.text.trim(),
        state: _stateController.text.trim(),
        acknowledged: _isAcknowledged,
        board: appState.selectedBoard,
        medium: appState.selectedMedium,
        standard: appState.activeStandardNumber > 0 ? appState.activeStandardNumber : null,
      );

      appState.setCurrentUser(user);

      if (!mounted) return;

      if (_isEditMode) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Profile updated successfully!',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600),
            ),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
        Navigator.pop(context);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.school_rounded, color: Colors.white, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Welcome, ${user.username}! Let\'s choose your Education Board.',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13),
                  ),
                ),
              ],
            ),
            backgroundColor: AppColors.primary,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
        Navigator.pushReplacementNamed(context, '/select_board');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Error saving profile: $e';
        _isLoading = false;
      });
    }
  }

  // ---------------------------------------------------------------------------
  // FORGOT PASSWORD MODAL (Standard 8-character pattern)
  // ---------------------------------------------------------------------------

  void _showForgotPasswordModal() {
    final forgotEmailController = TextEditingController(text: _signInLoginController.text.contains('@') ? _signInLoginController.text.trim() : '');
    final forgotOtpController = TextEditingController();
    final forgotNewPasswordController = TextEditingController();
    bool forgotOtpSent = false;
    bool isSending = false;
    bool isResetting = false;
    bool obscureNew = true;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (modalCtx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(modalCtx).viewInsets.bottom,
              ),
              child: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                padding: const EdgeInsets.fromLTRB(22, 16, 22, 28),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Drag handle
                      Center(
                        child: Container(
                          width: 40,
                          height: 4,
                          decoration: BoxDecoration(
                            color: const Color(0xFFCBD5E1),
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Header
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF6FF),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(Icons.lock_reset_rounded, color: AppColors.primary, size: 24),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Forgot Password',
                                  style: GoogleFonts.plusJakartaSans(
                                    fontSize: 18,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                Text(
                                  'Reset your 8-character password via email verification',
                                  style: GoogleFonts.plusJakartaSans(
                                    fontSize: 12,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () => Navigator.pop(modalCtx),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                      const Divider(height: 24),

                      // Step 1: Email Input
                      _buildInputLabel('Registered Email ID', isRequired: true),
                      const SizedBox(height: 6),
                      TextFormField(
                        controller: forgotEmailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: _buildInputDecoration(
                          hint: 'Enter your registered email',
                          icon: Icons.alternate_email_rounded,
                        ),
                      ),
                      const SizedBox(height: 12),

                      if (!forgotOtpSent) ...[
                        SizedBox(
                          width: double.infinity,
                          height: 46,
                          child: ElevatedButton.icon(
                            onPressed: isSending
                                ? null
                                : () async {
                                    final email = forgotEmailController.text.trim();
                                    if (email.isEmpty || !email.contains('@')) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text('Please enter a valid email address.', style: GoogleFonts.plusJakartaSans()),
                                          backgroundColor: AppColors.error,
                                        ),
                                      );
                                      return;
                                    }
                                    setModalState(() => isSending = true);
                                    final res = await SupabaseAuthService.sendEmailOtp(email);
                                    setModalState(() => isSending = false);

                                    if (res.success) {
                                      setModalState(() {
                                        forgotOtpSent = true;
                                        if (res.isRateLimited && res.backupOtp != null) {
                                          forgotOtpController.text = res.backupOtp!;
                                          Clipboard.setData(ClipboardData(text: res.backupOtp!));
                                        }
                                      });
                                      if (context.mounted) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            content: Text(res.message, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600)),
                                            backgroundColor: res.isRateLimited ? const Color(0xFF2563EB) : const Color(0xFF0D9488),
                                            duration: Duration(seconds: res.isRateLimited ? 6 : 3),
                                          ),
                                        );
                                      }
                                    } else {
                                      if (context.mounted) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            content: Text(res.message, style: GoogleFonts.plusJakartaSans()),
                                            backgroundColor: AppColors.error,
                                          ),
                                        );
                                      }
                                    }
                                  },
                            icon: isSending
                                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : const Icon(Icons.send_rounded, size: 18),
                            label: Text(
                              isSending ? 'Sending Code...' : 'Send Verification Code (OTP)',
                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13.5),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                          ),
                        ),
                      ] else ...[
                        // Step 2: OTP and New 8-character password
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF0FDF4),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFBBF7D0)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Verification code sent to ${forgotEmailController.text.trim()}',
                                  style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w600, color: const Color(0xFF166534)),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        _buildInputLabel('6-Digit Verification Code (OTP)', isRequired: true),
                        const SizedBox(height: 6),
                        TextFormField(
                          controller: forgotOtpController,
                          keyboardType: TextInputType.number,
                          maxLength: 6,
                          buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                            LengthLimitingTextInputFormatter(6),
                          ],
                          decoration: _buildInputDecoration(
                            hint: 'Enter 6-digit code',
                            icon: Icons.pin_rounded,
                          ),
                        ),
                        const SizedBox(height: 12),

                        // Standard 8-Character Password Pattern
                        _buildInputLabel('New Password (Standard 8-Character Pattern)', isRequired: true),
                        const SizedBox(height: 6),
                        TextFormField(
                          controller: forgotNewPasswordController,
                          obscureText: obscureNew,
                          maxLength: 8,
                          buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                          inputFormatters: [
                            LengthLimitingTextInputFormatter(8),
                          ],
                          decoration: _buildInputDecoration(
                            hint: 'Enter exactly 8 characters',
                            icon: Icons.lock_outline_rounded,
                            suffixIcon: IconButton(
                              icon: Icon(obscureNew ? Icons.visibility_off_rounded : Icons.visibility_rounded, size: 20, color: AppColors.textMuted),
                              onPressed: () => setModalState(() => obscureNew = !obscureNew),
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.info_outline_rounded, size: 13, color: AppColors.textSecondary),
                            const SizedBox(width: 5),
                            Expanded(
                              child: Text(
                                'Standard 8 characters (letters, numbers & special characters can all be used)',
                                style: GoogleFonts.plusJakartaSans(fontSize: 11.5, color: AppColors.textSecondary),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 18),

                        SizedBox(
                          width: double.infinity,
                          height: 48,
                          child: ElevatedButton(
                            onPressed: isResetting
                                ? null
                                : () async {
                                    final email = forgotEmailController.text.trim();
                                    final otp = forgotOtpController.text.trim();
                                    final newPass = forgotNewPasswordController.text.trim();

                                    if (otp.length < 6) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(content: Text('Please enter the 6-digit verification code.', style: GoogleFonts.plusJakartaSans()), backgroundColor: AppColors.error),
                                      );
                                      return;
                                    }
                                    if (newPass.length != 8) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(content: Text('Password must be exactly 8 characters.', style: GoogleFonts.plusJakartaSans()), backgroundColor: AppColors.error),
                                      );
                                      return;
                                    }

                                    setModalState(() => isResetting = true);
                                    // 1. Verify OTP with Supabase
                                    final verifyRes = await SupabaseAuthService.verifyEmailOtp(email: email, token: otp);
                                    if (!verifyRes.success) {
                                      setModalState(() => isResetting = false);
                                      if (context.mounted) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(content: Text(verifyRes.message, style: GoogleFonts.plusJakartaSans()), backgroundColor: AppColors.error),
                                        );
                                      }
                                      return;
                                    }

                                    // 2. Update password in database
                                    final resetRes = await ApiService.resetPassword(email: email, newPassword: newPass);
                                    setModalState(() => isResetting = false);

                                    if (resetRes['status'] == 'success') {
                                      if (modalCtx.mounted) {
                                        Navigator.pop(modalCtx);
                                      }
                                      _signInLoginController.text = email;
                                      _signInPasswordController.text = newPass;
                                      if (context.mounted) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            content: Text('Password reset successfully! You can now Sign In.', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600)),
                                            backgroundColor: const Color(0xFF10B981),
                                          ),
                                        );
                                      }
                                    } else {
                                      if (context.mounted) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(content: Text(resetRes['message'] ?? 'Failed to update password.', style: GoogleFonts.plusJakartaSans()), backgroundColor: AppColors.error),
                                        );
                                      }
                                    }
                                  },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                            child: isResetting
                                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : Text(
                                    'Update Password & Sign In',
                                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 14, color: Colors.white),
                                  ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  // ---------------------------------------------------------------------------
  // CONFIRMATION DIALOGS: LOG OUT & DELETE ACCOUNT
  // ---------------------------------------------------------------------------

  void _confirmLogOut() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.logout_rounded, color: Color(0xFF334155), size: 22),
            ),
            const SizedBox(width: 12),
            Text(
              'Log Out?',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18),
            ),
          ],
        ),
        content: Text(
          'Are you sure you want to log out of Book Banko? You can log back in anytime with your credentials.',
          style: GoogleFonts.plusJakartaSans(fontSize: 13.5, color: AppColors.textSecondary, height: 1.4),
        ),
        actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(
              'Cancel',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, color: AppColors.textSecondary),
            ),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final appState = Provider.of<BookBankoAppState>(context, listen: false);
              await appState.logOut();
              setState(() {
                _isEditMode = false;
                _selectedTab = 0;
                _signInLoginController.clear();
                _signInPasswordController.clear();
                _usernameController.clear();
                _emailController.clear();
                _passwordController.clear();
                _otpController.clear();
                _otpSent = false;
                _isEmailVerified = false;
                _isAcknowledged = false;
              });
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Logged out successfully.', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600)),
                    backgroundColor: const Color(0xFF334155),
                  ),
                );
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF1E293B),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: Text(
              'Log Out',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  void _confirmDeleteAccount() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFFEE2E2),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 22),
            ),
            const SizedBox(width: 12),
            Text(
              'Delete Account?',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 18, color: const Color(0xFFDC2626)),
            ),
          ],
        ),
        content: Text(
          'This will permanently erase your profile, reading history, and saved preferences. This action cannot be undone.',
          style: GoogleFonts.plusJakartaSans(fontSize: 13.5, color: AppColors.textSecondary, height: 1.4),
        ),
        actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(
              'Cancel',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, color: AppColors.textSecondary),
            ),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final appState = Provider.of<BookBankoAppState>(context, listen: false);
              await appState.deleteAccount();
              setState(() {
                _isEditMode = false;
                _selectedTab = 0;
                _signInLoginController.clear();
                _signInPasswordController.clear();
                _usernameController.clear();
                _emailController.clear();
                _passwordController.clear();
                _otpController.clear();
                _otpSent = false;
                _isEmailVerified = false;
                _isAcknowledged = false;
              });
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Your account has been deleted permanently.', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600)),
                    backgroundColor: const Color(0xFFDC2626),
                  ),
                );
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: Text(
              'Delete Permanently',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // MAIN BUILD METHOD
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.canvasBg,
        body: Column(
          children: [
            // Top Header Bar
            _buildTopHeader(),

            // Content Area
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(18, 16, 18, 32),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Mode Switcher Tabs (Only if not in Edit Profile mode)
                    if (!_isEditMode) ...[
                      _buildModeTabs(),
                      const SizedBox(height: 18),
                    ],

                    if (_errorMessage != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFFECACA)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 20),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.error,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Render Selected Mode Form
                    if (_isEditMode || _selectedTab == 1)
                      _buildSignUpOrEditForm()
                    else
                      _buildSignInForm(),

                    // Edit Profile Bottom Action Buttons: Log Out & Delete Account
                    if (_isEditMode) ...[
                      const SizedBox(height: 24),
                      const Divider(color: Color(0xFFE2E8F0)),
                      const SizedBox(height: 12),

                      // Neutral Log Out Button
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: OutlinedButton.icon(
                          onPressed: _confirmLogOut,
                          icon: const Icon(Icons.logout_rounded, size: 18, color: Color(0xFF475569)),
                          label: Text(
                            'Log Out',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: const Color(0xFF334155),
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            backgroundColor: const Color(0xFFF8FAFC),
                            side: const BorderSide(color: Color(0xFFCBD5E1), width: 1.2),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                        ),
                      ),

                      const SizedBox(height: 12),

                      // Red Delete Account Button
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: TextButton.icon(
                          onPressed: _confirmDeleteAccount,
                          icon: const Icon(Icons.delete_forever_rounded, size: 19, color: Color(0xFFDC2626)),
                          label: Text(
                            'Delete Account',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: const Color(0xFFDC2626),
                            ),
                          ),
                          style: TextButton.styleFrom(
                            backgroundColor: const Color(0xFFFEF2F2),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                              side: const BorderSide(color: Color(0xFFFECACA), width: 1.2),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // TOP HEADER WIDGET
  // ---------------------------------------------------------------------------

  Widget _buildTopHeader() {
    final canPop = Navigator.canPop(context);

    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: Color(0x1F0F172A),
            blurRadius: 20,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 22),
          child: Row(
            children: [
              if (canPop) ...[
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.white.withValues(alpha: 0.15),
                    padding: const EdgeInsets.all(8),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(width: 12),
              ],
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
                ),
                child: const BookBankoLogo(size: 28),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          'Book Banko',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                            letterSpacing: -0.4,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppColors.accentAmber,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            _isEditMode
                                ? 'EDIT PROFILE'
                                : (_selectedTab == 0 ? 'STUDENT SIGN IN' : 'NEW REGISTRATION'),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _isEditMode
                          ? 'Update educational details & settings'
                          : (_selectedTab == 0
                              ? 'Sign in to access your study curriculum'
                              : 'Verify email & register with Book Banko'),
                      style: GoogleFonts.plusJakartaSans(
                        color: Colors.white70,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // TAB SELECTOR (Sign In vs Register)
  // ---------------------------------------------------------------------------

  Widget _buildModeTabs() {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      padding: const EdgeInsets.all(4),
      child: Row(
        children: [
          Expanded(
            child: GestureDetector(
              onTap: () {
                setState(() {
                  _selectedTab = 0;
                  _errorMessage = null;
                });
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: _selectedTab == 0 ? Colors.white : Colors.transparent,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: _selectedTab == 0
                      ? [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.06),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          )
                        ]
                      : null,
                ),
                child: Center(
                  child: Text(
                    'Sign In',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: _selectedTab == 0 ? AppColors.primary : AppColors.textSecondary,
                    ),
                  ),
                ),
              ),
            ),
          ),
          Expanded(
            child: GestureDetector(
              onTap: () {
                setState(() {
                  _selectedTab = 1;
                  _errorMessage = null;
                });
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: _selectedTab == 1 ? Colors.white : Colors.transparent,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: _selectedTab == 1
                      ? [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.06),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          )
                        ]
                      : null,
                ),
                child: Center(
                  child: Text(
                    'New User Register',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: _selectedTab == 1 ? AppColors.primary : AppColors.textSecondary,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // 1. SIGN IN FORM
  // ---------------------------------------------------------------------------

  Widget _buildSignInForm() {
    return Form(
      key: _signInFormKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: AppColors.primaryBorder, width: 1.2),
              boxShadow: const [AppShadows.cardShadow],
            ),
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'WELCOME BACK',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textSecondary,
                    letterSpacing: 0.8,
                  ),
                ),
                const SizedBox(height: 16),

                // Email / Username
                _buildInputLabel('Email ID or Username', isRequired: true),
                const SizedBox(height: 6),
                TextFormField(
                  controller: _signInLoginController,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                  decoration: _buildInputDecoration(
                    hint: 'e.g. rahul@gmail.com or rahul',
                    icon: Icons.person_rounded,
                  ),
                  textInputAction: TextInputAction.next,
                  validator: (val) {
                    if (val == null || val.trim().isEmpty) {
                      return 'Please enter your email or username';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),

                // Password Input (Supports standard registered passwords)
                _buildInputLabel('Password', isRequired: true),
                const SizedBox(height: 6),
                TextFormField(
                  controller: _signInPasswordController,
                  obscureText: _signInObscurePassword,
                  maxLength: 32,
                  buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                  inputFormatters: [
                    LengthLimitingTextInputFormatter(32),
                  ],
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                  decoration: _buildInputDecoration(
                    hint: 'Enter your password (e.g. 6 to 8 characters)',
                    icon: Icons.lock_rounded,
                    suffixIcon: IconButton(
                      icon: Icon(
                        _signInObscurePassword ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                        color: AppColors.textMuted,
                        size: 20,
                      ),
                      onPressed: () {
                        setState(() {
                          _signInObscurePassword = !_signInObscurePassword;
                        });
                      },
                    ),
                  ),
                  textInputAction: TextInputAction.done,
                  validator: (val) {
                    if (val == null || val.trim().isEmpty) {
                      return 'Please enter password';
                    }
                    if (val.trim().length < 6) {
                      return 'Password must be at least 6 characters';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 4),

                // Helper text
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.only(top: 1.0),
                      child: Icon(Icons.info_outline_rounded, size: 13, color: AppColors.textSecondary),
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        'Enter your account password (at least 6 characters)',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w500,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 8),

                // Blue Forgot Password? Link (Aligned to the Right)
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: _showForgotPasswordModal,
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: Text(
                      'Forgot Password?',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 20),

                // Sign In Button
                Container(
                  width: double.infinity,
                  height: 52,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(16),
                    gradient: const LinearGradient(
                      colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)],
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF1E3A8A).withValues(alpha: 0.35),
                        blurRadius: 14,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _submitSignIn,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      foregroundColor: Colors.white,
                      shadowColor: Colors.transparent,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    ),
                    child: _isLoading
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                          )
                        : Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                'Sign In to Book Banko',
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w800,
                                  color: Colors.white,
                                ),
                              ),
                              const SizedBox(width: 8),
                              const Icon(Icons.arrow_forward_rounded, size: 18, color: Colors.white),
                            ],
                          ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // Switch to Register Link
          Center(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  'Don\'t have an account?',
                  style: GoogleFonts.plusJakartaSans(fontSize: 13, color: AppColors.textSecondary),
                ),
                TextButton(
                  onPressed: () {
                    setState(() {
                      _selectedTab = 1;
                      _errorMessage = null;
                    });
                  },
                  child: Text(
                    'Sign Up with Email OTP',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // 2. SIGN UP (WITH SUPABASE EMAIL OTP) & EDIT PROFILE FORM
  // ---------------------------------------------------------------------------

  Widget _buildSignUpOrEditForm() {
    return Form(
      key: _signUpFormKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Step 1: Email Verification Card (For New Registrations)
          if (!_isEditMode && !_isEmailVerified) ...[
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: AppColors.primaryBorder, width: 1.2),
                boxShadow: const [AppShadows.cardShadow],
              ),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEFF6FF),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.mark_email_read_rounded, color: AppColors.primary, size: 20),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'STEP 1: EMAIL VERIFICATION',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textSecondary,
                                letterSpacing: 0.7,
                              ),
                            ),
                            Text(
                              'Verify your email address with a 6-digit OTP',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 24),

                  _buildInputLabel('Email Address', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    enabled: !_otpSent,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    decoration: _buildInputDecoration(
                      hint: 'e.g. student@gmail.com',
                      icon: Icons.alternate_email_rounded,
                      suffixIcon: _otpSent
                          ? IconButton(
                              icon: const Icon(Icons.edit_rounded, size: 18, color: AppColors.primary),
                              onPressed: () {
                                setState(() {
                                  _otpSent = false;
                                  _otpController.clear();
                                });
                              },
                              tooltip: 'Change Email',
                            )
                          : null,
                    ),
                  ),

                  const SizedBox(height: 14),

                  if (!_otpSent) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton.icon(
                        onPressed: _isSendingOtp ? null : _sendSignupOtp,
                        icon: _isSendingOtp
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.send_rounded, size: 18),
                        label: Text(
                          _isSendingOtp ? 'Sending OTP Code...' : 'Send Verification Code (OTP)',
                          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 14),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                      ),
                    ),
                  ] else ...[
                    // OTP Box
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              _buildInputLabel('Enter 6-Digit OTP Code', isRequired: true),
                              if (_otpResendTimerSeconds > 0)
                                Text(
                                  'Resend in ${_otpResendTimerSeconds}s',
                                  style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textSecondary),
                                )
                              else
                                GestureDetector(
                                  onTap: _isSendingOtp ? null : _sendSignupOtp,
                                  child: Text(
                                    'Resend Code',
                                    style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary),
                                  ),
                                ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          TextFormField(
                            controller: _otpController,
                            keyboardType: TextInputType.number,
                            maxLength: 6,
                            textAlign: TextAlign.center,
                            buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                            inputFormatters: [
                              FilteringTextInputFormatter.digitsOnly,
                              LengthLimitingTextInputFormatter(6),
                            ],
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 8,
                              color: AppColors.textPrimary,
                            ),
                            decoration: _buildInputDecoration(
                              hint: '••••••',
                              icon: Icons.pin_rounded,
                            ),
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            height: 46,
                            child: ElevatedButton.icon(
                              onPressed: _isVerifyingOtp ? null : _verifySignupOtp,
                              icon: _isVerifyingOtp
                                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Icon(Icons.check_circle_rounded, size: 18),
                              label: Text(
                                _isVerifyingOtp ? 'Verifying OTP...' : 'Verify OTP & Continue',
                                style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 14),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF0D9488),
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 18),
          ],

          // Step 2 / Edit Mode: Student Information Form (Shown if email verified or in edit mode)
          if (_isEditMode || _isEmailVerified) ...[
            // Email Verified Status Indicator for new signups
            if (!_isEditMode) ...[
              Container(
                margin: const EdgeInsets.only(bottom: 16),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDF4),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFF86EFAC)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.verified_user_rounded, color: Color(0xFF16A34A), size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Email Verified: ${_emailController.text.trim()}',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: const Color(0xFF166534),
                        ),
                      ),
                    ),
                    const Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 18),
                  ],
                ),
              ),
            ],

            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: AppColors.primaryBorder, width: 1.2),
                boxShadow: const [AppShadows.cardShadow],
              ),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _isEditMode ? 'EDIT STUDENT PROFILE' : 'STEP 2: STUDENT DETAILS',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textSecondary,
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 14),

                  // 1. Username Field
                  _buildInputLabel('Student / Username', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _usernameController,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    decoration: _buildInputDecoration(
                      hint: 'e.g. Rahul Sharma',
                      icon: Icons.person_rounded,
                    ),
                    textInputAction: TextInputAction.next,
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) {
                        return 'Please enter student / username';
                      }
                      if (val.trim().length < 2) {
                        return 'Name must be at least 2 characters';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 14),

                  // 2. Email Field (Display only if in Edit Mode or verified)
                  _buildInputLabel('Email ID', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _emailController,
                    readOnly: !_isEditMode,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    decoration: _buildInputDecoration(
                      hint: 'e.g. student@gmail.com',
                      icon: Icons.alternate_email_rounded,
                      suffixIcon: const Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 20),
                    ),
                  ),
                  const SizedBox(height: 14),

                  // 3. Password Field (Standard 8-Character Pattern)
                  _buildInputLabel('Password', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _passwordController,
                    obscureText: _obscurePassword,
                    maxLength: 32,
                    buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                    inputFormatters: [
                      LengthLimitingTextInputFormatter(_isEditMode ? 32 : 8),
                    ],
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    decoration: _buildInputDecoration(
                      hint: _isEditMode ? 'Enter password (at least 6 characters)' : 'Enter 8-character password',
                      icon: Icons.lock_rounded,
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscurePassword ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                          color: AppColors.textMuted,
                          size: 20,
                        ),
                        onPressed: () {
                          setState(() {
                            _obscurePassword = !_obscurePassword;
                          });
                        },
                      ),
                    ),
                    textInputAction: TextInputAction.next,
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) {
                        return 'Please enter password';
                      }
                      if (_isEditMode) {
                        if (val.trim().length < 6) {
                          return 'Password must be at least 6 characters';
                        }
                      } else {
                        if (val.trim().length != 8) {
                          return 'Password must be standard 8 characters';
                        }
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 4),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Padding(
                        padding: EdgeInsets.only(top: 1.0),
                        child: Icon(Icons.info_outline_rounded, size: 13, color: AppColors.textSecondary),
                      ),
                      const SizedBox(width: 5),
                      Expanded(
                        child: Text(
                          'Standard 8 characters (letters, numbers & special characters can all be used)',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w500,
                            color: AppColors.textSecondary,
                            height: 1.25,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // 4. School Name Field
                  _buildInputLabel('Currently Study in Which School Name', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _schoolController,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    decoration: _buildInputDecoration(
                      hint: 'e.g. St. Xavier\'s High School',
                      icon: Icons.school_rounded,
                    ),
                    textInputAction: TextInputAction.next,
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) {
                        return 'Please enter your school name';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 14),

                  // 5. State Dropdown
                  _buildInputLabel('State', isRequired: true),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: _indianStates.contains(_stateController.text)
                        ? _stateController.text
                        : _indianStates.first,
                    decoration: _buildInputDecoration(
                      hint: 'Select State',
                      icon: Icons.map_rounded,
                    ),
                    icon: const Icon(Icons.arrow_drop_down_rounded, color: AppColors.primary, size: 26),
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    dropdownColor: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    items: _indianStates.map((st) {
                      return DropdownMenuItem<String>(
                        value: st,
                        child: Text(st, overflow: TextOverflow.ellipsis),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) {
                        _stateController.text = val;
                      }
                    },
                  ),
                  const SizedBox(height: 14),

                  // 6. District & City Row
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // District
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _buildInputLabel('District', isRequired: true),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _districtController,
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                              ),
                              decoration: _buildInputDecoration(
                                hint: 'e.g. Ahmedabad',
                                icon: Icons.location_city_rounded,
                                isCompact: true,
                              ),
                              textInputAction: TextInputAction.next,
                              validator: (val) => val == null || val.trim().isEmpty ? 'Required' : null,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),

                      // City
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _buildInputLabel('City / Town', isRequired: true),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _cityController,
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                              ),
                              decoration: _buildInputDecoration(
                                hint: 'e.g. Visnagar',
                                icon: Icons.place_rounded,
                                isCompact: true,
                              ),
                              textInputAction: TextInputAction.done,
                              validator: (val) => val == null || val.trim().isEmpty ? 'Required' : null,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 18),

            // Acknowledgment Checkbox
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: _isAcknowledged ? AppColors.primary : AppColors.primaryBorder,
                  width: 1.2,
                ),
                boxShadow: const [AppShadows.cardShadow],
              ),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: InkWell(
                onTap: () {
                  setState(() {
                    _isAcknowledged = !_isAcknowledged;
                    if (_isAcknowledged) _errorMessage = null;
                  });
                },
                borderRadius: BorderRadius.circular(12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 24,
                      height: 24,
                      child: Checkbox(
                        value: _isAcknowledged,
                        activeColor: AppColors.primary,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
                        onChanged: (val) {
                          setState(() {
                            _isAcknowledged = val ?? false;
                            if (_isAcknowledged) _errorMessage = null;
                          });
                        },
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.only(top: 2.0),
                        child: Text(
                          'I acknowledge that all the educational details provided above are true, accurate, and complete.',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: _isAcknowledged ? AppColors.textPrimary : const Color(0xFF475569),
                            height: 1.35,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 20),

            // Submit Button
            Container(
              width: double.infinity,
              height: 54,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                gradient: const LinearGradient(
                  colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)],
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                ),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF1E3A8A).withValues(alpha: 0.35),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: ElevatedButton(
                onPressed: _isLoading ? null : _submitSignUp,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  foregroundColor: Colors.white,
                  shadowColor: Colors.transparent,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
                child: _isLoading
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                      )
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            _isEditMode ? 'Save Profile Changes' : 'Continue to Choose Education Board',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(width: 8),
                          const Icon(Icons.arrow_forward_rounded, size: 19, color: Colors.white),
                        ],
                      ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // HELPER WIDGETS FOR INPUTS
  // ---------------------------------------------------------------------------

  Widget _buildInputLabel(String label, {bool isRequired = false}) {
    return Row(
      children: [
        Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        if (isRequired)
          Text(
            ' *',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 13,
              fontWeight: FontWeight.w800,
              color: AppColors.error,
            ),
          ),
      ],
    );
  }

  InputDecoration _buildInputDecoration({
    required String hint,
    required IconData icon,
    Widget? suffixIcon,
    bool isCompact = false,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: GoogleFonts.plusJakartaSans(
        color: AppColors.textMuted,
        fontSize: isCompact ? 13 : 13.5,
        fontWeight: FontWeight.w500,
      ),
      prefixIcon: Padding(
        padding: EdgeInsets.symmetric(horizontal: isCompact ? 8 : 12),
        child: Icon(icon, color: AppColors.primary, size: isCompact ? 18 : 20),
      ),
      prefixIconConstraints: BoxConstraints(
        minWidth: isCompact ? 36 : 44,
        minHeight: 40,
      ),
      suffixIcon: suffixIcon,
      suffixIconConstraints: const BoxConstraints(
        minWidth: 40,
        minHeight: 40,
      ),
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: EdgeInsets.symmetric(
        horizontal: isCompact ? 10 : 14,
        vertical: isCompact ? 12 : 14,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0), width: 1.2),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0), width: 1.2),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.8),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.error, width: 1.2),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.error, width: 1.8),
      ),
    );
  }
}
