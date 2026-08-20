import 'package:flutter/material.dart';
import 'package:modrik_design_tokens/modrik_design_tokens.dart';

import 'academic_context_reset_boundary.dart';
import 'account_security_view.dart';
import 'app_shell.dart';
import 'auth_copy.dart';
import 'auth_models.dart';
import 'mobile_auth_controller.dart';
import 'mobile_learning_controller.dart';
import 'models.dart';

class MobileAuthBoundary extends StatelessWidget {
  const MobileAuthBoundary({
    super.key,
    required this.authController,
    required this.learningController,
  });

  final MobileAuthController authController;
  final MobileLearningController learningController;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: authController,
      builder: (context, _) {
        final copy = AuthCopy(authController.locale);
        final direction = authController.locale == ModrikLocale.ar
            ? TextDirection.rtl
            : TextDirection.ltr;
        return Directionality(
          textDirection: direction,
          child: switch (authController.state) {
            MobileAuthState.bootstrapping => _AuthStateScaffold(
                controller: authController,
                copy: copy,
                icon: Icons.shield_outlined,
                title: copy.t('loading_auth'),
                loading: true,
              ),
            MobileAuthState.configurationRequired => _AuthStateScaffold(
                controller: authController,
                copy: copy,
                icon: Icons.settings_ethernet_outlined,
                title: copy.t('api_not_configured'),
                actionLabel: copy.t('retry'),
                onAction: authController.retryBootstrap,
              ),
            MobileAuthState.error => _AuthStateScaffold(
                controller: authController,
                copy: copy,
                icon: Icons.security_outlined,
                title: copy.t(authController.messageCode ?? 'unexpected_auth_error'),
                actionLabel: copy.t('retry'),
                onAction: authController.retryBootstrap,
              ),
            MobileAuthState.verificationRequired => _VerificationView(
                controller: authController,
                copy: copy,
              ),
            MobileAuthState.authenticated ||
            MobileAuthState.offlineAuthenticated =>
              authController.accountPanelOpen
                  ? AccountSecurityView(
                      controller: authController,
                      copy: copy,
                    )
                  : _AuthenticatedLearningHost(
                      authController: authController,
                      learningController: learningController,
                      copy: copy,
                    ),
            MobileAuthState.signedOut => _AuthEntryView(
                controller: authController,
                copy: copy,
              ),
          },
        );
      },
    );
  }
}

class _AuthenticatedLearningHost extends StatelessWidget {
  const _AuthenticatedLearningHost({
    required this.authController,
    required this.learningController,
    required this.copy,
  });

  final MobileAuthController authController;
  final MobileLearningController learningController;
  final AuthCopy copy;

  @override
  Widget build(BuildContext context) {
    return AcademicContextResetBoundary(
      controller: learningController,
      child: Stack(
        children: [
          MobileStudentShell(controller: learningController),
          PositionedDirectional(
            end: 16,
            bottom: 92,
            child: Semantics(
              button: true,
              label: copy.t('account'),
              child: Tooltip(
                message: copy.t('account'),
                child: SizedBox.square(
                  dimension: 52,
                  child: IconButton.filledTonal(
                    onPressed: authController.openAccountPanel,
                    icon: const Icon(Icons.manage_accounts_outlined),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AuthEntryView extends StatefulWidget {
  const _AuthEntryView({required this.controller, required this.copy});

  final MobileAuthController controller;
  final AuthCopy copy;

  @override
  State<_AuthEntryView> createState() => _AuthEntryViewState();
}

class _AuthEntryViewState extends State<_AuthEntryView> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _token = TextEditingController();
  final _newPassword = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _token.dispose();
    _newPassword.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final copy = widget.copy;
    return _AuthScaffold(
      controller: controller,
      copy: copy,
      child: AutofillGroup(
        child: FocusTraversalGroup(
          child: switch (controller.entryMode) {
            AuthEntryMode.login => _login(context, controller, copy),
            AuthEntryMode.register => _register(context, controller, copy),
            AuthEntryMode.recovery => _recovery(context, controller, copy),
            AuthEntryMode.reset => _reset(context, controller, copy),
            AuthEntryMode.verify => _login(context, controller, copy),
          },
        ),
      ),
    );
  }

  Widget _login(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _FormCard(
      title: copy.t('login_title'),
      body: copy.t('login_body'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _TextInput(
            controller: _email,
            label: copy.t('email'),
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.username, AutofillHints.email],
          ),
          const SizedBox(height: 14),
          _TextInput(
            controller: _password,
            label: copy.t('password'),
            obscureText: true,
            autofillHints: const [AutofillHints.password],
          ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.login(
                      email: _email.text,
                      password: _password.text,
                    ),
            child: Text(copy.t('sign_in')),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.setEntryMode(AuthEntryMode.register),
            child: Text(copy.t('create_account')),
          ),
          TextButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.setEntryMode(AuthEntryMode.recovery),
            child: Text(copy.t('forgot_password')),
          ),
          const SizedBox(height: 12),
          Semantics(
            header: true,
            child: Text(
              copy.t('or_continue_with'),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelLarge,
            ),
          ),
          const SizedBox(height: 10),
          _ProviderButtons(controller: controller, copy: copy),
        ],
      ),
    );
  }

  Widget _register(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _FormCard(
      title: copy.t('register_title'),
      body: copy.t('register_body'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _TextInput(
            controller: _name,
            label: copy.t('name'),
            autofillHints: const [AutofillHints.name],
          ),
          const SizedBox(height: 14),
          _TextInput(
            controller: _email,
            label: copy.t('email'),
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.email],
          ),
          const SizedBox(height: 14),
          _TextInput(
            controller: _password,
            label: copy.t('password'),
            obscureText: true,
            autofillHints: const [AutofillHints.newPassword],
          ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.register(
                      name: _name.text,
                      email: _email.text,
                      password: _password.text,
                    ),
            child: Text(copy.t('create_account')),
          ),
          TextButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.setEntryMode(AuthEntryMode.login),
            child: Text(copy.t('back_to_login')),
          ),
        ],
      ),
    );
  }

  Widget _recovery(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _FormCard(
      title: copy.t('recovery_title'),
      body: copy.t('recovery_body'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _TextInput(
            controller: _email,
            label: copy.t('email'),
            keyboardType: TextInputType.emailAddress,
            autofillHints: const [AutofillHints.email],
          ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.requestPasswordRecovery(_email.text),
            child: Text(copy.t('send_recovery')),
          ),
          TextButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.setEntryMode(AuthEntryMode.login),
            child: Text(copy.t('back_to_login')),
          ),
        ],
      ),
    );
  }

  Widget _reset(
    BuildContext context,
    MobileAuthController controller,
    AuthCopy copy,
  ) {
    return _FormCard(
      title: copy.t('reset_title'),
      body: copy.t('recovery_body'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _TextInput(
            controller: _token,
            label: copy.t('reset_token'),
            autofillHints: const [AutofillHints.oneTimeCode],
          ),
          const SizedBox(height: 14),
          _TextInput(
            controller: _newPassword,
            label: copy.t('new_password'),
            obscureText: true,
            autofillHints: const [AutofillHints.newPassword],
          ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.resetPassword(
                      token: _token.text,
                      password: _newPassword.text,
                    ),
            child: Text(copy.t('reset_password')),
          ),
          TextButton(
            onPressed: controller.isBusy
                ? null
                : () => controller.setEntryMode(AuthEntryMode.login),
            child: Text(copy.t('back_to_login')),
          ),
        ],
      ),
    );
  }
}

class _VerificationView extends StatefulWidget {
  const _VerificationView({required this.controller, required this.copy});

  final MobileAuthController controller;
  final AuthCopy copy;

  @override
  State<_VerificationView> createState() => _VerificationViewState();
}

class _VerificationViewState extends State<_VerificationView> {
  final _token = TextEditingController();

  @override
  void dispose() {
    _token.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final copy = widget.copy;
    return _AuthScaffold(
      controller: controller,
      copy: copy,
      child: _FormCard(
        title: copy.t('verification_title'),
        body: copy.t('verification_body'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _TextInput(
              controller: _token,
              label: copy.t('verification_token'),
              autofillHints: const [AutofillHints.oneTimeCode],
            ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: controller.isBusy
                  ? null
                  : () => controller.verifyEmail(_token.text),
              child: Text(copy.t('verify_email')),
            ),
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: controller.isBusy
                  ? null
                  : controller.resendEmailVerification,
              child: Text(copy.t('resend_verification')),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProviderButtons extends StatelessWidget {
  const _ProviderButtons({required this.controller, required this.copy});

  final MobileAuthController controller;
  final AuthCopy copy;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      alignment: WrapAlignment.center,
      children: [
        OutlinedButton.icon(
          onPressed: controller.isBusy
              ? null
              : () => controller.providerLogin(AuthProvider.google),
          icon: const Icon(Icons.account_circle_outlined),
          label: Text(copy.t('google')),
        ),
        OutlinedButton.icon(
          onPressed: controller.isBusy
              ? null
              : () => controller.providerLogin(AuthProvider.apple),
          icon: const Icon(Icons.phone_iphone_outlined),
          label: Text(copy.t('apple')),
        ),
      ],
    );
  }
}

class _AuthStateScaffold extends StatelessWidget {
  const _AuthStateScaffold({
    required this.controller,
    required this.copy,
    required this.icon,
    required this.title,
    this.loading = false,
    this.actionLabel,
    this.onAction,
  });

  final MobileAuthController controller;
  final AuthCopy copy;
  final IconData icon;
  final String title;
  final bool loading;
  final String? actionLabel;
  final Future<void> Function()? onAction;

  @override
  Widget build(BuildContext context) {
    return _AuthScaffold(
      controller: controller,
      copy: copy,
      child: Center(
        child: Semantics(
          liveRegion: true,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (loading)
                const CircularProgressIndicator()
              else
                Icon(icon, size: 52, color: ModrikColors.blue),
              const SizedBox(height: 18),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: ModrikColors.navy,
                      fontWeight: FontWeight.w800,
                    ),
              ),
              if (actionLabel != null && onAction != null) ...[
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: controller.isBusy ? null : onAction,
                  child: Text(actionLabel!),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _AuthScaffold extends StatelessWidget {
  const _AuthScaffold({
    required this.controller,
    required this.copy,
    required this.child,
  });

  final MobileAuthController controller;
  final AuthCopy copy;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Semantics(
                      header: true,
                      child: Text(
                        copy.t('brand'),
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: ModrikColors.navy,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ),
                  ),
                  Semantics(
                    label: copy.t('language'),
                    child: PopupMenuButton<ModrikLocale>(
                      tooltip: copy.t('language'),
                      initialValue: controller.locale,
                      onSelected: controller.setLocale,
                      itemBuilder: (context) => const [
                        PopupMenuItem(
                          value: ModrikLocale.ar,
                          child: Text('العربية'),
                        ),
                        PopupMenuItem(
                          value: ModrikLocale.en,
                          child: Text('English'),
                        ),
                        PopupMenuItem(
                          value: ModrikLocale.fr,
                          child: Text('Français'),
                        ),
                      ],
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(
                          minWidth: 48,
                          minHeight: 48,
                        ),
                        child: Center(
                          child: Text(
                            controller.locale.code.toUpperCase(),
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (controller.isBusy) const LinearProgressIndicator(minHeight: 2),
            if (controller.messageCode case final code?)
              _AuthMessage(code: code, copy: copy),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 560),
                    child: child,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AuthMessage extends StatelessWidget {
  const _AuthMessage({required this.code, required this.copy});

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
          color: ModrikColors.white,
          border: Border.all(color: ModrikColors.warning),
          borderRadius: BorderRadius.circular(ModrikRadii.small),
        ),
        child: Text(copy.t(code)),
      ),
    );
  }
}

class _FormCard extends StatelessWidget {
  const _FormCard({
    required this.title,
    required this.body,
    required this.child,
  });

  final String title;
  final String body;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Semantics(
              header: true,
              child: Text(
                title,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: ModrikColors.navy,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ),
            const SizedBox(height: 8),
            Text(body, style: const TextStyle(height: 1.45)),
            const SizedBox(height: 22),
            child,
          ],
        ),
      ),
    );
  }
}

class _TextInput extends StatelessWidget {
  const _TextInput({
    required this.controller,
    required this.label,
    this.obscureText = false,
    this.keyboardType,
    this.autofillHints,
  });

  final TextEditingController controller;
  final String label;
  final bool obscureText;
  final TextInputType? keyboardType;
  final Iterable<String>? autofillHints;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      textField: true,
      label: label,
      child: TextField(
        controller: controller,
        obscureText: obscureText,
        keyboardType: keyboardType,
        autofillHints: autofillHints,
        enableSuggestions: !obscureText,
        autocorrect: !obscureText,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }
}
