import 'models.dart';

enum AuthProvider {
  google('google'),
  apple('apple');

  const AuthProvider(this.value);
  final String value;
}

enum ProviderIntentPurpose {
  login('login'),
  link('link');

  const ProviderIntentPurpose(this.value);
  final String value;
}

class AuthAccount {
  const AuthAccount({
    required this.id,
    required this.email,
    required this.emailVerified,
    required this.passwordEnabled,
    required this.status,
  });

  factory AuthAccount.fromJson(Map<String, dynamic> json) => AuthAccount(
        id: json['id'] as String,
        email: json['email'] as String?,
        emailVerified: json['email_verified'] as bool? ?? false,
        passwordEnabled: json['password_enabled'] as bool? ?? false,
        status: json['status'] as String? ?? 'active',
      );

  final String id;
  final String? email;
  final bool emailVerified;
  final bool passwordEnabled;
  final String status;

  bool get isActive => status == 'active';
  bool get needsEmailVerification => passwordEnabled && !emailVerified;

  AuthAccount copyWith({bool? emailVerified}) => AuthAccount(
        id: id,
        email: email,
        emailVerified: emailVerified ?? this.emailVerified,
        passwordEnabled: passwordEnabled,
        status: status,
      );

  Map<String, Object?> toJson() => {
        'id': id,
        'email': email,
        'email_verified': emailVerified,
        'password_enabled': passwordEnabled,
        'status': status,
      };
}

class AuthSessionInfo {
  const AuthSessionInfo({
    required this.id,
    required this.name,
    required this.authenticatedAt,
    required this.lastUsedAt,
    required this.expiresAt,
    required this.createdAt,
    required this.isCurrent,
  });

  factory AuthSessionInfo.fromJson(Map<String, dynamic> json) => AuthSessionInfo(
        id: json['id'] as String,
        name: json['name'] as String?,
        authenticatedAt: DateTime.parse(json['authenticated_at'] as String),
        lastUsedAt: DateTime.parse(json['last_used_at'] as String),
        expiresAt: DateTime.parse(json['expires_at'] as String),
        createdAt: json['created_at'] is String
            ? DateTime.parse(json['created_at'] as String)
            : null,
        isCurrent: json['is_current'] as bool? ?? false,
      );

  final String id;
  final String? name;
  final DateTime authenticatedAt;
  final DateTime lastUsedAt;
  final DateTime expiresAt;
  final DateTime? createdAt;
  final bool isCurrent;

  Map<String, Object?> toJson() => {
        'id': id,
        'name': name,
        'authenticated_at': authenticatedAt.toUtc().toIso8601String(),
        'last_used_at': lastUsedAt.toUtc().toIso8601String(),
        'expires_at': expiresAt.toUtc().toIso8601String(),
        'created_at': createdAt?.toUtc().toIso8601String(),
        'is_current': isCurrent,
      };
}

class AuthSessionGrant {
  const AuthSessionGrant({
    required this.account,
    required this.accessToken,
    required this.tokenType,
    required this.session,
  });

  factory AuthSessionGrant.fromJson(Map<String, dynamic> json) => AuthSessionGrant(
        account: AuthAccount.fromJson(
          Map<String, dynamic>.from(json['account'] as Map),
        ),
        accessToken: json['access_token'] as String,
        tokenType: json['token_type'] as String? ?? 'Bearer',
        session: AuthSessionInfo.fromJson(
          Map<String, dynamic>.from(json['session'] as Map),
        ),
      );

  final AuthAccount account;
  final String accessToken;
  final String tokenType;
  final AuthSessionInfo session;
}

class ProviderIntent {
  const ProviderIntent({
    required this.provider,
    required this.purpose,
    required this.state,
    required this.nonce,
    required this.expiresAt,
  });

  factory ProviderIntent.fromJson(
    AuthProvider provider,
    ProviderIntentPurpose purpose,
    Map<String, dynamic> json,
  ) =>
      ProviderIntent(
        provider: provider,
        purpose: purpose,
        state: json['state'] as String,
        nonce: json['nonce'] as String,
        expiresAt: DateTime.parse(json['expires_at'] as String),
      );

  final AuthProvider provider;
  final ProviderIntentPurpose purpose;
  final String state;
  final String nonce;
  final DateTime expiresAt;
}

class ProviderCompletion {
  const ProviderCompletion({
    required this.provider,
    required this.linked,
    this.accountId,
    this.grant,
  });

  factory ProviderCompletion.fromJson(Map<String, dynamic> json) {
    final provider = AuthProvider.values.firstWhere(
      (candidate) => candidate.value == json['provider'],
    );
    if (json['access_token'] is String) {
      return ProviderCompletion(
        provider: provider,
        linked: false,
        grant: AuthSessionGrant.fromJson(json),
      );
    }
    return ProviderCompletion(
      provider: provider,
      linked: json['linked'] as bool? ?? false,
      accountId: json['account_id'] as String?,
    );
  }

  final AuthProvider provider;
  final bool linked;
  final String? accountId;
  final AuthSessionGrant? grant;
}

class StoredAuthCredential {
  const StoredAuthCredential({
    required this.account,
    required this.accessToken,
    required this.session,
  });

  factory StoredAuthCredential.fromGrant(AuthSessionGrant grant) =>
      StoredAuthCredential(
        account: grant.account,
        accessToken: grant.accessToken,
        session: grant.session,
      );

  factory StoredAuthCredential.fromJson(Map<String, dynamic> json) =>
      StoredAuthCredential(
        account: AuthAccount.fromJson(
          Map<String, dynamic>.from(json['account'] as Map),
        ),
        accessToken: json['access_token'] as String,
        session: AuthSessionInfo.fromJson(
          Map<String, dynamic>.from(json['session'] as Map),
        ),
      );

  final AuthAccount account;
  final String accessToken;
  final AuthSessionInfo session;

  StoredAuthCredential copyWith({AuthAccount? account, AuthSessionInfo? session}) =>
      StoredAuthCredential(
        account: account ?? this.account,
        accessToken: accessToken,
        session: session ?? this.session,
      );

  Map<String, Object?> toJson() => {
        'account': account.toJson(),
        'access_token': accessToken,
        'session': session.toJson(),
      };
}

class AuthenticatedIdentity {
  const AuthenticatedIdentity({
    required this.userId,
    required this.locale,
    required this.roles,
  });

  factory AuthenticatedIdentity.fromSession(Session session) => AuthenticatedIdentity(
        userId: session.userId,
        locale: session.locale,
        roles: session.roles,
      );

  final String userId;
  final ModrikLocale locale;
  final List<String> roles;
}
