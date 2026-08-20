import 'package:flutter/foundation.dart';

import 'auth_gateway.dart';
import 'auth_models.dart';
import 'models.dart';
import 'provider_auth_launcher.dart';
import 'secure_session_store.dart';

enum MobileAuthState {
  bootstrapping,
  signedOut,
  authenticated,
  offlineAuthenticated,
  verificationRequired,
  configurationRequired,
  error,
}

enum AuthEntryMode { login, register, recovery, reset, verify }

typedef AuthSessionActivated = Future<void> Function(
  String? previousAccountId,
  String currentAccountId,
);
typedef AuthSessionEndGuard = Future<bool> Function();
typedef AuthExplicitSessionEnded = Future<void> Function();

class MobileAuthController extends ChangeNotifier {
  MobileAuthController({
    required this.gateway,
    required this.credentialStore,
    required this.tokenProvider,
    required this.providerLauncher,
    required this.apiConfigured,
    this.onSessionActivated,
    this.canEndSession,
    this.onExplicitSessionEnded,
    ModrikLocale initialLocale = ModrikLocale.en,
  }) : locale = initialLocale;

  final AuthGateway gateway;
  final AuthCredentialStore credentialStore;
  final MutableBearerToken tokenProvider;
  final ProviderAuthLauncher providerLauncher;
  final bool apiConfigured;
  final AuthSessionActivated? onSessionActivated;
  final AuthSessionEndGuard? canEndSession;
  final AuthExplicitSessionEnded? onExplicitSessionEnded;

  MobileAuthState state = MobileAuthState.bootstrapping;
  AuthEntryMode entryMode = AuthEntryMode.login;
  ModrikLocale locale;
  StoredAuthCredential? credential;
  Session? identitySession;
  List<AuthSessionInfo> sessions = const [];
  bool isBusy = false;
  bool accountPanelOpen = false;
  String? messageCode;
  String? _lastAccountId;

  AuthAccount? get account => credential?.account;
  bool get isAuthenticated =>
      state == MobileAuthState.authenticated ||
      state == MobileAuthState.offlineAuthenticated;
  bool get hasCredential => credential != null;
  bool get isOfflineAuthenticated => state == MobileAuthState.offlineAuthenticated;
  bool get requiresVerification => state == MobileAuthState.verificationRequired;
  bool get canEnterLearning => isAuthenticated;

  Future<void> bootstrap() async {
    state = MobileAuthState.bootstrapping;
    messageCode = null;
    notifyListeners();

    if (!apiConfigured) {
      state = MobileAuthState.configurationRequired;
      messageCode = 'api_not_configured';
      notifyListeners();
      return;
    }

    StoredAuthCredential? stored;
    try {
      stored = await credentialStore.read();
    } on AuthStorageFailure catch (failure) {
      state = MobileAuthState.error;
      messageCode = failure.code;
      notifyListeners();
      return;
    }

    if (stored == null) {
      state = MobileAuthState.signedOut;
      entryMode = AuthEntryMode.login;
      notifyListeners();
      return;
    }

    credential = stored;
    _lastAccountId = stored.account.id;
    tokenProvider.set(stored.accessToken);

    try {
      identitySession = await gateway.currentSession();
      locale = identitySession!.locale;
      if (stored.account.needsEmailVerification) {
        state = MobileAuthState.verificationRequired;
        entryMode = AuthEntryMode.verify;
        messageCode = 'verification_required';
      } else {
        state = MobileAuthState.authenticated;
        await onSessionActivated?.call(stored.account.id, stored.account.id);
      }
    } on AuthFailure catch (failure) {
      if (failure.isAuthenticationRequired) {
        await _clearRejectedCredential();
        state = MobileAuthState.signedOut;
        entryMode = AuthEntryMode.login;
        messageCode = 'session_expired';
      } else if (failure.isNetwork) {
        if (stored.account.needsEmailVerification) {
          state = MobileAuthState.verificationRequired;
          entryMode = AuthEntryMode.verify;
          messageCode = 'verification_offline';
        } else {
          state = MobileAuthState.offlineAuthenticated;
          messageCode = 'offline_saved_session';
          await onSessionActivated?.call(stored.account.id, stored.account.id);
        }
      } else {
        state = MobileAuthState.error;
        messageCode = failure.code;
      }
    }
    notifyListeners();
  }

  void setLocale(ModrikLocale next) {
    if (locale == next) return;
    locale = next;
    notifyListeners();
  }

  void setEntryMode(AuthEntryMode next) {
    if (entryMode == next) return;
    entryMode = next;
    messageCode = null;
    notifyListeners();
  }

  Future<void> retryBootstrap() => bootstrap();

  Future<void> register({
    required String name,
    required String email,
    required String password,
  }) =>
      _runBusy(() async {
        final grant = await gateway.register(
          name: name.trim(),
          email: email.trim(),
          password: password,
        );
        await _activateGrant(grant);
        if (state == MobileAuthState.verificationRequired) {
          messageCode = 'verification_required';
        } else {
          messageCode = 'registration_complete';
        }
      });

  Future<void> login({required String email, required String password}) =>
      _runBusy(() async {
        final grant = await gateway.login(
          email: email.trim(),
          password: password,
        );
        await _activateGrant(grant);
        if (state == MobileAuthState.verificationRequired) {
          messageCode = 'verification_required';
        } else {
          messageCode = 'login_complete';
        }
      });

  Future<void> resendEmailVerification() => _runBusy(() async {
        await gateway.resendEmailVerification();
        messageCode = 'verification_resent';
      });

  Future<void> verifyEmail(String token) => _runBusy(() async {
        await gateway.verifyEmail(token.trim());
        final current = credential;
        if (current != null) {
          final updated = current.copyWith(
            account: current.account.copyWith(emailVerified: true),
          );
          credential = updated;
          await credentialStore.write(updated);
          state = MobileAuthState.authenticated;
          entryMode = AuthEntryMode.login;
          messageCode = 'verification_complete';
          await onSessionActivated?.call(current.account.id, current.account.id);
        } else {
          state = MobileAuthState.signedOut;
          entryMode = AuthEntryMode.login;
          messageCode = 'verification_complete_sign_in';
        }
      });

  Future<void> requestPasswordRecovery(String email) => _runBusy(() async {
        await gateway.requestPasswordRecovery(email.trim());
        entryMode = AuthEntryMode.reset;
        messageCode = 'recovery_accepted';
      });

  Future<void> resetPassword({required String token, required String password}) =>
      _runBusy(() async {
        await gateway.resetPassword(token: token.trim(), password: password);
        await _endSessionLocally(clearLearning: true);
        entryMode = AuthEntryMode.login;
        messageCode = 'password_reset_complete';
      });

  Future<void> reauthenticate(String password) => _runBusy(() async {
        await gateway.reauthenticate(password);
        await _refreshSessionsInternal();
        messageCode = 'recent_auth_complete';
      });

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) =>
      _runBusy(() async {
        await gateway.changePassword(
          currentPassword: currentPassword,
          newPassword: newPassword,
        );
        await _refreshSessionsInternal();
        messageCode = 'password_changed';
      });

  Future<void> openAccountPanel() async {
    accountPanelOpen = true;
    messageCode = null;
    notifyListeners();
    if (state == MobileAuthState.authenticated) {
      await refreshSessions();
    }
  }

  void closeAccountPanel() {
    if (!accountPanelOpen) return;
    accountPanelOpen = false;
    messageCode = null;
    notifyListeners();
  }

  Future<void> refreshSessions() => _runBusy(() async {
        await _refreshSessionsInternal();
        messageCode = 'sessions_updated';
      });

  Future<void> revokeOtherSessions() => _runBusy(() async {
        await gateway.revokeOtherSessions();
        await _refreshSessionsInternal();
        messageCode = 'other_sessions_revoked';
      });

  Future<void> revokeAllSessions() => _runBusy(() async {
        if (!await _canEndCurrentSession()) return;
        await gateway.revokeAllSessions();
        await _endSessionLocally(clearLearning: true);
        messageCode = 'all_sessions_revoked';
      });

  Future<void> logoutCurrentSession() => _runBusy(() async {
        if (!await _canEndCurrentSession()) return;
        await gateway.revokeCurrentSession();
        await _endSessionLocally(clearLearning: true);
        messageCode = 'logout_complete';
      });

  Future<void> deleteAccount(String confirmation) => _runBusy(() async {
        if (!await _canEndCurrentSession()) return;
        await gateway.deleteAccount(confirmation.trim());
        await _endSessionLocally(clearLearning: true);
        messageCode = 'account_deleted';
      });

  Future<void> providerLogin(AuthProvider provider) => _runBusy(() async {
        final intent = await gateway.createProviderIntent(
          provider,
          ProviderIntentPurpose.login,
        );
        final authorization = await providerLauncher.authorize(provider, intent);
        final completion = await gateway.completeProviderIntent(
          provider: provider,
          state: intent.state,
          idToken: authorization.idToken,
        );
        final grant = completion.grant;
        if (grant == null) {
          throw const AuthFailure(
            status: 409,
            code: 'PROVIDER_LINK_REQUIRED',
            message: 'Explicit provider linking is required.',
            retryable: false,
          );
        }
        await _activateGrant(grant);
        messageCode = state == MobileAuthState.verificationRequired
            ? 'verification_required'
            : 'provider_login_complete';
      });

  Future<void> linkProvider(AuthProvider provider) => _runBusy(() async {
        final intent = await gateway.createProviderIntent(
          provider,
          ProviderIntentPurpose.link,
        );
        final authorization = await providerLauncher.authorize(provider, intent);
        final completion = await gateway.completeProviderIntent(
          provider: provider,
          state: intent.state,
          idToken: authorization.idToken,
        );
        if (!completion.linked) {
          throw const AuthFailure(
            status: 409,
            code: 'PROVIDER_LINK_REQUIRED',
            message: 'Provider linking did not complete.',
            retryable: false,
          );
        }
        messageCode = 'provider_linked';
      });

  Future<void> unlinkProvider(AuthProvider provider) => _runBusy(() async {
        await gateway.unlinkProvider(provider);
        messageCode = 'provider_unlinked';
      });

  Future<void> handleSessionRejected() async {
    if (state == MobileAuthState.signedOut ||
        state == MobileAuthState.configurationRequired) {
      return;
    }
    await _clearRejectedCredential();
    accountPanelOpen = false;
    state = MobileAuthState.signedOut;
    entryMode = AuthEntryMode.login;
    messageCode = 'session_expired';
    notifyListeners();
  }

  void handleEmailVerificationRequired() {
    if (credential == null) return;
    state = MobileAuthState.verificationRequired;
    entryMode = AuthEntryMode.verify;
    accountPanelOpen = false;
    messageCode = 'verification_required';
    notifyListeners();
  }

  Future<void> _activateGrant(AuthSessionGrant grant) async {
    final previousAccountId = credential?.account.id ?? _lastAccountId;
    final stored = StoredAuthCredential.fromGrant(grant);
    await credentialStore.write(stored);
    credential = stored;
    _lastAccountId = stored.account.id;
    tokenProvider.set(stored.accessToken);
    sessions = [stored.session];

    try {
      identitySession = await gateway.currentSession();
      locale = identitySession!.locale;
    } on AuthFailure catch (failure) {
      if (failure.isAuthenticationRequired) {
        await _clearRejectedCredential();
        state = MobileAuthState.signedOut;
        entryMode = AuthEntryMode.login;
        messageCode = 'session_expired';
        return;
      }
      if (!failure.isNetwork) rethrow;
    }

    if (stored.account.needsEmailVerification) {
      state = MobileAuthState.verificationRequired;
      entryMode = AuthEntryMode.verify;
      return;
    }

    state = MobileAuthState.authenticated;
    entryMode = AuthEntryMode.login;
    await onSessionActivated?.call(previousAccountId, stored.account.id);
  }

  Future<void> _refreshSessionsInternal() async {
    sessions = await gateway.listSessions();
    final current = sessions.where((session) => session.isCurrent).firstOrNull;
    final stored = credential;
    if (stored != null && current != null) {
      final updated = stored.copyWith(session: current);
      credential = updated;
      await credentialStore.write(updated);
    }
  }

  Future<bool> _canEndCurrentSession() async {
    final guard = canEndSession;
    if (guard == null || await guard()) return true;
    messageCode = 'local_sync_required_before_sign_out';
    return false;
  }

  Future<void> _endSessionLocally({required bool clearLearning}) async {
    final previous = credential?.account.id ?? _lastAccountId;
    if (previous != null) _lastAccountId = previous;
    try {
      await credentialStore.clear();
    } on AuthStorageFailure catch (failure) {
      messageCode = failure.code;
    }
    tokenProvider.clear();
    credential = null;
    identitySession = null;
    sessions = const [];
    accountPanelOpen = false;
    state = MobileAuthState.signedOut;
    entryMode = AuthEntryMode.login;
    if (clearLearning) {
      await onExplicitSessionEnded?.call();
    }
  }

  Future<void> _clearRejectedCredential() async {
    final previous = credential?.account.id;
    if (previous != null) _lastAccountId = previous;
    try {
      await credentialStore.clear();
    } on AuthStorageFailure {
      // Authentication authority has already rejected the credential. Never
      // keep using the in-memory token merely because local cleanup failed.
    }
    tokenProvider.clear();
    credential = null;
    identitySession = null;
    sessions = const [];
  }

  Future<void> _runBusy(Future<void> Function() action) async {
    if (isBusy) return;
    isBusy = true;
    messageCode = null;
    notifyListeners();
    try {
      await action();
    } on ProviderLaunchFailure catch (failure) {
      messageCode = failure.code;
    } on AuthStorageFailure catch (failure) {
      state = MobileAuthState.error;
      messageCode = failure.code;
    } on AuthFailure catch (failure) {
      await _applyFailure(failure);
    } catch (_) {
      messageCode = 'unexpected_auth_error';
    } finally {
      isBusy = false;
      notifyListeners();
    }
  }

  Future<void> _applyFailure(AuthFailure failure) async {
    if (failure.isAuthenticationRequired && credential != null) {
      await _clearRejectedCredential();
      accountPanelOpen = false;
      state = MobileAuthState.signedOut;
      entryMode = AuthEntryMode.login;
      messageCode = 'session_expired';
      return;
    }
    if (failure.isEmailVerificationRequired && credential != null) {
      state = MobileAuthState.verificationRequired;
      entryMode = AuthEntryMode.verify;
      accountPanelOpen = false;
      messageCode = 'verification_required';
      return;
    }
    if (failure.isNetwork) {
      if (credential != null && !credential!.account.needsEmailVerification) {
        state = MobileAuthState.offlineAuthenticated;
        messageCode = 'auth_offline';
      } else {
        messageCode = 'auth_offline_no_session';
      }
      return;
    }
    messageCode = failure.code;
  }
}
