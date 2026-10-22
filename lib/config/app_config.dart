class AppConfig {
  // Production / Deployed Backend API Endpoint (Live Neon PostgreSQL Backend)
  static const String customApiUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://bookbanko-neon.vercel.app/api',
  );

  // Supabase Project REST & Storage URL (Mumbai ap-south-1)
  static const String supabaseUrl = String.fromEnvironment(
    'SUPABASE_URL',
    defaultValue: 'https://hdvcmoyqdpurjsmpbkjt.supabase.co',
  );

  static const String supabaseAnonKey = String.fromEnvironment(
    'SUPABASE_ANON_KEY',
    defaultValue: 'sb_publishable_E3FlFjqTNGpFBTsVDOW8dQ_W_UazdWY',
  );

  // Default candidate host endpoints (Fastest Live Working Server First)
  static const List<String> defaultCandidateUrls = [
    'https://bookbanko-neon.vercel.app/api',
    'http://10.0.2.2/Book_Banko/backend/api',
    'http://localhost/Book_Banko/backend/api',
    'http://127.0.0.1/Book_Banko/backend/api',
    'http://192.168.0.101/Book_Banko/backend/api',
    'https://bookbanko.vercel.app/api',
  ];
}
