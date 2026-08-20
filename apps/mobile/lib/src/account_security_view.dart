import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'auth_copy.dart';
import 'auth_models.dart';
import 'mobile_auth_controller.dart';

class AccountSecurityView extends StatefulWidget {
  const AccountSecurityView({
    super.key,
    required this.controller,
    required this.copy,
  });

  final MobileAuthController controller;
  final AuthCopy copy;

  @override
  State<AccountSecurityView> createState() => _AccountSecurityViewState();
}

class _AccountSecurityViewState extends State<AccountSecurityView> {
  final _reauthPassword = TextEditingController();
  final _currentPassword = TextEditingController();
  final _newPassword = TextEditingController();
  final _deleteConfirmation = TextEditingController();

  @override
  void dispose() {
    _reauthPassword.dispose();
    _currentPassword.dispose();
    _newPassword.dispose();
    _deleteConfirmation.dispose();
    super.dispose();
  }

  bool get _networkActionsEnabled =>
      !widget.controller.isBusy && !widget.controller.isOfflineAuthenticated;

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final copy = widget.copy;
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            _header(context, controller, copy),
            if (controller.isBusy) const LinearProgressIndicator(minHeight: 2),
            if (controller.messageCode case final code?)
              _Message(code: code, copy: copy),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 36),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 680),
                    child: FocusTraversalGroup(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _summaryCard(context, controller, copy),
                          const SizedBox(height: 16),
                          _sessionsCard(context, controller, copy),
                          const SizedBox(height: 16),
                          _recentAuthCard(context, controller, copy),
                          const SizedBox(height: 16),
                          _passwordCard(context, controller, copy),
                          const SizedBox(height: 16),
                          _providersCard(context, controller, copy),
                          const SizedBox(height: 16),
                          _deleteCard(context, controller, copy),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 20, 8),
      child: Row(
        children: [
          Semantics(
            button: true,
            label: copy.t('close'),
            child: IconButton(
              tooltip: copy.t('close'),
              onPressed: controller.isBusy ? null : controller.closeAccountPanel,
              icon: const Icon(Icons.arrow_back),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                copy.t('account_title'),
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: ModrikColors.navy,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _summaryCard(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    final account = controller.account;
    return _SectionCard(
      title: copy.t('account_title'),
      body: copy.t('account_body'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (account?.email case final email?)
            SelectableText(
              email,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
          if (controller.isOfflineAuthenticated) ...[
            const SizedBox(height: 12),
            Semantics(
              liveRegion: true,
              child: Text(copy.t('auth_offline')),
            ),
          ],
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _networkActionsEnabled
                ? controller.logoutCurrentSession
                : null,
            icon: const Icon(Icons.logout),
            label: Text(copy.t('logout')),
          ),
        ],
      ),
    );
  }

  Widget _sessionsCard(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _SectionCard(
      title: copy.t('sessions'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (controller.sessions.isEmpty)
            Text(copy.t('no_sessions'))
          else
            ...controller.sessions.map(
              (session) => _SessionRow(session: session, copy: copy),
            ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              OutlinedButton(
                onPressed: _networkActionsEnabled
                    ? controller.refreshSessions
                    : null,
                child: Text(copy.t('refresh_sessions')),
              ),
              OutlinedButton(
                onPressed: _networkActionsEnabled
                    ? controller.revokeOtherSessions
                    : null,
                child: Text(copy.t('revoke_others')),
              ),
              OutlinedButton(
                onPressed: _networkActionsEnabled
                    ? controller.revokeAllSessions
                    : null,
                child: Text(copy.t('revoke_all')),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _recentAuthCard(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _SectionCard(
      title: copy.t('recent_auth_title'),
      body: copy.t('recent_auth_body'),
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SecureField(
              controller: _reauthPassword,
              label: copy.t('password'),
              autofillHints: const [AutofillHints.password],
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _networkActionsEnabled
                  ? () => controller.reauthenticate(_reauthPassword.text)
                  : null,
              child: Text(copy.t('reauthenticate')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _passwordCard(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _SectionCard(
      title: copy.t('change_password'),
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SecureField(
              controller: _currentPassword,
              label: copy.t('current_password'),
              autofillHints: const [AutofillHints.password],
            ),
            const SizedBox(height: 12),
            _SecureField(
              controller: _newPassword,
              label: copy.t('new_password'),
              autofillHints: const [AutofillHints.newPassword],
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _networkActionsEnabled
                  ? () => controller.changePassword(
                        currentPassword: _currentPassword.text,
                        newPassword: _newPassword.text,
                      )
                  : null,
              child: Text(copy.t('change_password')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _providersCard(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _SectionCard(
      title: copy.t('provider_management'),
      body: copy.t('provider_management_body'),
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          OutlinedButton.icon(
            onPressed: _networkActionsEnabled
                ? () => controller.linkProvider(AuthProvider.google)
                : null,
            icon: const Icon(Icons.add_link),
            label: Text(copy.t('link_google')),
          ),
          OutlinedButton(
            onPressed: _networkActionsEnabled
                ? () => controller.unlinkProvider(AuthProvider.google)
                : null,
            child: Text(copy.t('unlink_google')),
          ),
          OutlinedButton.icon(
            onPressed: _networkActionsEnabled
                ? () => controller.linkProvider(AuthProvider.apple)
                : null,
            icon: const Icon(Icons.add_link),
            label: Text(copy.t('link_apple')),
          ),
          OutlinedButton(
            onPressed: _networkActionsEnabled
                ? () => controller.unlinkProvider(AuthProvider.apple)
                : null,
            child: Text(copy.t('unlink_apple')),
          ),
        ],
      ),
    );
  }

  Widget _deleteCard(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _SectionCard(
      title: copy.t('delete_account_title'),
      body: copy.t('delete_account_body'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _deleteConfirmation,
            autocorrect: false,
            enableSuggestions: false,
            decoration: InputDecoration(labelText: copy.t('delete_confirmation')),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _networkActionsEnabled
                ? () => controller.deleteAccount(_deleteConfirmation.text)
                : null,
            icon: const Icon(Icons.delete_outline),
            label: Text(copy.t('delete_account')),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.child,
    this.body,
  });

  final String title;
  final String? body;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Semantics(
              header: true,
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: ModrikColors.navy,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
            if (body != null) ...[
              const SizedBox(height: 8),
              Text(body!, style: const TextStyle(height: 1.45)),
            ],
            const SizedBox(height: 16),
            child,
          ],
        ),
      ),
    );
  }
}

class _SessionRow extends StatelessWidget {
  const _SessionRow({required this.session, required this.copy});

  final AuthSessionInfo session;
  final AuthCopy copy;

  @override
  Widget build(BuildContext context) {
    final label = session.isCurrent
        ? copy.t('session_current')
        : copy.t('session_other');
    return Semantics(
      container: true,
      label: label,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              session.isCurrent
                  ? Icons.smartphone_outlined
                  : Icons.devices_other_outlined,
              color: ModrikColors.blue,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    session.name?.trim().isNotEmpty == true
                        ? '${session.name} · $label'
                        : label,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${copy.t('session_expires')}: ${session.expiresAt.toLocal()}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SecureField extends StatelessWidget {
  const _SecureField({
    required this.controller,
    required this.label,
    required this.autofillHints,
  });

  final TextEditingController controller;
  final String label;
  final Iterable<String> autofillHints;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      textField: true,
      label: label,
      child: TextField(
        controller: controller,
        obscureText: true,
        enableSuggestions: false,
        autocorrect: false,
        autofillHints: autofillHints,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.code, required this.copy});

  final String code;
  final AuthCopy copy;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        margin: const EdgeInsets.fromLTRB(20, 4, 20, 4),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          border: Border.all(color: ModrikColors.warning),
          borderRadius: BorderRadius.circular(ModrikRadii.small),
        ),
        child: Text(copy.t(code)),
      ),
    );
  }
}
