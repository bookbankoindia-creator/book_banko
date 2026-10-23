import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../models/app_state.dart';
import '../services/api_services.dart';
import '../theme/app_theme.dart';
import '../widgets/logo_widget.dart';

class RegisterDetailsScreen extends StatefulWidget {
  const RegisterDetailsScreen({super.key});

  @override
  State<RegisterDetailsScreen> createState() => _RegisterDetailsScreenState();
}

class _RegisterDetailsScreenState extends State<RegisterDetailsScreen> {
  final _formKey = GlobalKey<FormState>();

  String _email = '';
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _schoolController = TextEditingController();
  final _cityController = TextEditingController();
  final _districtController = TextEditingController();
  final _stateController = TextEditingController(text: 'Gujarat');

  bool _obscurePassword = true;
  bool _isAcknowledged = true;
  bool _isSaving = false;
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
  void didChangeDependencies() {
    super.didChangeDependencies();
    final args = ModalRoute.of(context)?.settings.arguments;
    if (args is Map<String, dynamic> && _email.isEmpty) {
      _email = args['email'] ?? '';
    }
  }

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    _schoolController.dispose();
    _cityController.dispose();
    _districtController.dispose();
    _stateController.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    setState(() {
      _errorMessage = null;
    });

    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (!_isAcknowledged) {
      setState(() {
        _errorMessage = 'Please confirm the acknowledgement checkbox to proceed.';
      });
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final appState = Provider.of<BookBankoAppState>(context, listen: false);

    try {
      final user = await ApiService.saveUser(
        username: _usernameController.text.trim(),
        email: _email.trim().toLowerCase(),
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

      // Navigate to choose education board
      Navigator.pushNamedAndRemoveUntil(context, '/select_board', (route) => false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Error saving profile: $e';
        _isSaving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        body: Column(
          children: [
            // Top Header Bar
            Container(
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
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
                        ),
                        child: const BookBankoLogo(size: 26),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Student Registration',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                                letterSpacing: -0.4,
                              ),
                            ),
                            Text(
                              'Step 3 of 3: Academic Profile',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: const Color(0xFF93C5FD),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Content Body
            Expanded(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 36),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Verified Email Banner
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF86EFAC)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.verified_user_rounded, color: Color(0xFF16A34A), size: 22),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Email Verified Successfully',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      color: const Color(0xFF166534),
                                    ),
                                  ),
                                  Text(
                                    _email,
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 13,
                                      color: const Color(0xFF15803D),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: const Color(0xFFDCFCE7),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Text(
                                'Verified ✓',
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF15803D),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),

                      if (_errorMessage != null) ...[
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFFECACA)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  _errorMessage!,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.error,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Student Details Card
                      Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(color: const Color(0xFFE2E8F0), width: 1.2),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x0A0F172A),
                              blurRadius: 18,
                              offset: Offset(0, 6),
                            ),
                          ],
                        ),
                        padding: const EdgeInsets.all(22),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'STUDENT INFORMATION',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textSecondary,
                                letterSpacing: 0.8,
                              ),
                            ),
                            const SizedBox(height: 16),

                            // 1. Full Name
                            _buildInputLabel('Student / Username', isRequired: true),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _usernameController,
                              style: GoogleFonts.plusJakartaSans(fontSize: 14.5, fontWeight: FontWeight.w600),
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
                            const SizedBox(height: 16),

                            // 2. Password (Standard 8-Character Pattern)
                            _buildInputLabel('Create Password', isRequired: true),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              maxLength: 8,
                              buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                              inputFormatters: [
                                LengthLimitingTextInputFormatter(8),
                              ],
                              style: GoogleFonts.plusJakartaSans(fontSize: 14.5, fontWeight: FontWeight.w600),
                              decoration: _buildInputDecoration(
                                hint: 'Enter standard 8-character password',
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
                                if (val.trim().length != 8) {
                                  return 'Password must be exactly 8 characters';
                                }
                                return null;
                              },
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
                            const SizedBox(height: 16),

                            // 3. School Name
                            _buildInputLabel('School Name', isRequired: true),
                            const SizedBox(height: 6),
                            TextFormField(
                              controller: _schoolController,
                              style: GoogleFonts.plusJakartaSans(fontSize: 14.5, fontWeight: FontWeight.w600),
                              decoration: _buildInputDecoration(
                                hint: 'e.g. Sardar Patel High School',
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
                            const SizedBox(height: 16),

                            // 4. State Dropdown
                            _buildInputLabel('State', isRequired: true),
                            const SizedBox(height: 6),
                            DropdownButtonFormField<String>(
                              initialValue: _indianStates.contains(_stateController.text) ? _stateController.text : 'Gujarat',
                              items: _indianStates.map((state) {
                                return DropdownMenuItem(
                                  value: state,
                                  child: Text(
                                    state,
                                    style: GoogleFonts.plusJakartaSans(
                                      fontSize: 14.5,
                                      fontWeight: FontWeight.w600,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                );
                              }).toList(),
                              onChanged: (val) {
                                if (val != null) {
                                  setState(() {
                                    _stateController.text = val;
                                  });
                                }
                              },
                              decoration: _buildInputDecoration(
                                hint: 'Select State',
                                icon: Icons.map_rounded,
                              ),
                            ),
                            const SizedBox(height: 16),

                            // 5. District & City
                            Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      _buildInputLabel('District', isRequired: true),
                                      const SizedBox(height: 6),
                                      TextFormField(
                                        controller: _districtController,
                                        style: GoogleFonts.plusJakartaSans(fontSize: 14.5, fontWeight: FontWeight.w600),
                                        decoration: _buildInputDecoration(
                                          hint: 'e.g. Ahmedabad',
                                          icon: Icons.location_city_rounded,
                                        ),
                                        textInputAction: TextInputAction.next,
                                        validator: (val) {
                                          if (val == null || val.trim().isEmpty) {
                                            return 'Required';
                                          }
                                          return null;
                                        },
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      _buildInputLabel('City', isRequired: true),
                                      const SizedBox(height: 6),
                                      TextFormField(
                                        controller: _cityController,
                                        style: GoogleFonts.plusJakartaSans(fontSize: 14.5, fontWeight: FontWeight.w600),
                                        decoration: _buildInputDecoration(
                                          hint: 'e.g. Visnagar',
                                          icon: Icons.place_rounded,
                                        ),
                                        textInputAction: TextInputAction.done,
                                        validator: (val) {
                                          if (val == null || val.trim().isEmpty) {
                                            return 'Required';
                                          }
                                          return null;
                                        },
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 20),

                            // 6. Acknowledgment Checkbox
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF8FAFC),
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Checkbox(
                                    value: _isAcknowledged,
                                    onChanged: (val) {
                                      setState(() {
                                        _isAcknowledged = val ?? false;
                                      });
                                    },
                                    activeColor: AppColors.primary,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                                  ),
                                  Expanded(
                                    child: GestureDetector(
                                      onTap: () {
                                        setState(() {
                                          _isAcknowledged = !_isAcknowledged;
                                        });
                                      },
                                      child: Padding(
                                        padding: const EdgeInsets.only(top: 8.0),
                                        child: Text(
                                          'I acknowledge that the educational details provided above are accurate and will be used for my student curriculum.',
                                          style: GoogleFonts.plusJakartaSans(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w500,
                                            color: AppColors.textSecondary,
                                            height: 1.4,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 22),

                            // Submit Button
                            SizedBox(
                              width: double.infinity,
                              height: 52,
                              child: Container(
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(16),
                                  gradient: const LinearGradient(
                                    colors: [Color(0xFF0F172A), Color(0xFF1E3A8A)],
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: const Color(0xFF1E3A8A).withValues(alpha: 0.35),
                                      blurRadius: 12,
                                      offset: const Offset(0, 4),
                                    ),
                                  ],
                                ),
                                child: ElevatedButton.icon(
                                  onPressed: _isSaving ? null : _handleSubmit,
                                  icon: _isSaving
                                      ? const SizedBox(
                                          width: 20,
                                          height: 20,
                                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                        )
                                      : const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20),
                                  label: Text(
                                    _isSaving ? 'Saving Profile...' : 'Continue to Choose Education Board',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 14.5,
                                      color: Colors.white,
                                    ),
                                  ),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.transparent,
                                    shadowColor: Colors.transparent,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInputLabel(String text, {bool isRequired = false}) {
    return Row(
      children: [
        Text(
          text,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        if (isRequired)
          const Text(
            ' *',
            style: TextStyle(color: AppColors.error, fontWeight: FontWeight.bold),
          ),
      ],
    );
  }

  InputDecoration _buildInputDecoration({
    required String hint,
    required IconData icon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: GoogleFonts.plusJakartaSans(
        fontSize: 14,
        color: AppColors.textMuted,
      ),
      prefixIcon: Icon(icon, color: AppColors.textMuted, size: 20),
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.8),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.error, width: 1.2),
      ),
    );
  }
}
